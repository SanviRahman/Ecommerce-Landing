<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingCharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Manage only campaign_id=NULL delivery areas for the main storefront checkout.
 * Campaign-specific shipping remains owned by CampaignController.
 */
final class HomepageShippingChargeController extends Controller
{
    private function adminOnly(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }

    public function index(): View
    {
        $this->adminOnly();

        $global = ShippingCharge::query()->whereNull('campaign_id');
        $areas = (clone $global)->orderBy('area_name')->orderBy('id')->get();
        $activeCount = (clone $global)->where('status', true)->count();

        return view('admin.homepage-shipping-charges.index', [
            'areas' => $areas,
            'activeCount' => $activeCount,
            'inactiveCount' => $areas->count() - $activeCount,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->adminOnly();
        $validated = $this->validatedArea($request);

        ShippingCharge::query()->create([
            'campaign_id' => null,
            'area_name' => $validated['area_name'],
            'delivery_charge' => (int) $validated['delivery_charge'],
            'status' => (bool) $validated['status'],
        ]);

        return redirect()->route('admin.homepage-shipping-charges.index')
            ->with('success', 'Homepage delivery area added successfully.');
    }

    public function update(Request $request, ShippingCharge $charge): RedirectResponse
    {
        $this->adminOnly();
        $this->assertGlobal($charge);
        $validated = $this->validatedArea($request, $charge);

        DB::transaction(function () use ($charge, $validated): void {
            $current = $this->lockedGlobalArea((int) $charge->id);

            $this->assertNotRemovingLastActive($current, (bool) $validated['status']);
            $current->update([
                'area_name' => $validated['area_name'],
                'delivery_charge' => (int) $validated['delivery_charge'],
                'status' => (bool) $validated['status'],
            ]);
        });

        return redirect()->route('admin.homepage-shipping-charges.index')
            ->with('success', 'Homepage delivery area updated successfully.');
    }

    public function destroy(ShippingCharge $charge): RedirectResponse
    {
        $this->adminOnly();
        $this->assertGlobal($charge);

        DB::transaction(function () use ($charge): void {
            $current = $this->lockedGlobalArea((int) $charge->id);

            $this->assertNotRemovingLastActive($current, false);
            $current->delete(); // Soft-delete; existing order snapshots stay intact.
        });

        return redirect()->route('admin.homepage-shipping-charges.index')
            ->with('success', 'Homepage delivery area moved to trash.');
    }

    private function assertGlobal(ShippingCharge $charge): void
    {
        // Never allow an ID from a campaign form to be edited here.
        abort_unless($charge->campaign_id === null && ! $charge->trashed(), 404);
    }

    /** @return array{area_name: string, delivery_charge: int|string, status: bool|int|string} */
    private function validatedArea(Request $request, ?ShippingCharge $charge = null): array
    {
        $name = $request->input('area_name');
        if (is_string($name)) {
            $request->merge(['area_name' => trim($name)]);
        }

        return $request->validate([
            'area_name' => [
                'required', 'string', 'max:255',
                Rule::unique('shipping_charges', 'area_name')
                    ->whereNull('campaign_id')
                    ->whereNull('deleted_at')
                    ->ignore($charge?->id),
            ],
            'delivery_charge' => ['required', 'integer', 'between:0,999999'],
            'status' => ['required', 'boolean'],
        ]);
    }

    /**
     * Lock global delivery rows in one stable order before evaluating the
     * last-active invariant. Two admins cannot concurrently disable different
     * final active areas and both see the other as still active.
     */
    private function lockedGlobalArea(int $id): ShippingCharge
    {
        $areas = ShippingCharge::query()->whereNull('campaign_id')
            ->orderBy('id')->lockForUpdate()->get();
        $current = $areas->firstWhere('id', $id);

        abort_unless($current instanceof ShippingCharge, 404);

        return $current;
    }

    private function assertNotRemovingLastActive(ShippingCharge $current, bool $willStayActive): void
    {
        if (! $current->status || $willStayActive) {
            return;
        }

        $others = ShippingCharge::query()->whereNull('campaign_id')
            ->where('status', true)->where('id', '!=', $current->id)->exists();

        if (! $others) {
            throw ValidationException::withMessages([
                'status' => 'At least one homepage delivery area must stay active. Add or activate another area first.',
            ]);
        }
    }
}
