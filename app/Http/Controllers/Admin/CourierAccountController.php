<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourierAccountController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function index()
    {
        $this->adminOnly();

        $courierTypes = $this->courierTypeOptions();

        return view('admin.courier-accounts.index', [
            'title' => 'Courier API Accounts',
            'couriers' => CourierAccount::query()->latest()->paginate(20),
            'courierTypes' => $courierTypes,
            'courierDefaultBaseUrls' => $this->courierDefaultBaseUrls($courierTypes),
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Courier API Accounts', 'url' => route('admin.courier-accounts.index')],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            /*
             * Courier type now comes from Courier Manage.
             * Previously it was restricted to only steadfast/pathao.
             */
            'code' => ['required', 'string', Rule::in($this->allowedCourierCodes())],

            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'secret_key' => ['nullable', 'string', 'max:1000'],
            'token' => ['nullable', 'string', 'max:5000'],

            'store_id' => ['nullable', 'string', 'max:255'],
            'delivery_type' => ['nullable', 'integer'],
            'item_type' => ['nullable', 'integer'],
            'item_weight' => ['nullable', 'numeric', 'min:0.1'],
            'special_instruction' => ['nullable', 'string', 'max:1000'],

            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $code = strtolower($validated['code']);

            if ($request->boolean('is_default')) {
                CourierAccount::query()
                    ->where('code', $code)
                    ->update(['is_default' => false]);
            }

            CourierAccount::create([
                'name' => $validated['name'],
                'code' => $code,

                'base_url' => $validated['base_url'] ?: $this->defaultBaseUrl($code),
                'api_key' => $validated['api_key'] ?? null,
                'secret_key' => $validated['secret_key'] ?? null,
                'token' => $validated['token'] ?? null,

                'settings' => $this->makeSettings($request),

                'is_default' => $request->boolean('is_default'),
                'status' => $request->boolean('status', true),
            ]);
        });

        return back()->with('success', 'Courier API account created successfully.');
    }

    public function update(Request $request, CourierAccount $courierAccount)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            /*
             * Keep the current account code valid even if that courier type
             * was later deactivated/removed from Courier Manage.
             */
            'code' => ['required', 'string', Rule::in($this->allowedCourierCodes($courierAccount->code))],

            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'secret_key' => ['nullable', 'string', 'max:1000'],
            'token' => ['nullable', 'string', 'max:5000'],

            'store_id' => ['nullable', 'string', 'max:255'],
            'delivery_type' => ['nullable', 'integer'],
            'item_type' => ['nullable', 'integer'],
            'item_weight' => ['nullable', 'numeric', 'min:0.1'],
            'special_instruction' => ['nullable', 'string', 'max:1000'],

            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated, $courierAccount) {
            $code = strtolower($validated['code']);

            if ($request->boolean('is_default')) {
                CourierAccount::query()
                    ->where('code', $code)
                    ->where('id', '!=', $courierAccount->id)
                    ->update(['is_default' => false]);
            }

            $courierAccount->update([
                'name' => $validated['name'],
                'code' => $code,

                'base_url' => $validated['base_url'] ?: $this->defaultBaseUrl($code),
                'api_key' => $validated['api_key'] ?? null,
                'secret_key' => $validated['secret_key'] ?? null,
                'token' => $validated['token'] ?? null,

                'settings' => $this->makeSettings($request),

                'is_default' => $request->boolean('is_default'),
                'status' => $request->boolean('status'),
            ]);
        });

        return back()->with('success', 'Courier API account updated successfully.');
    }

    public function destroy(CourierAccount $courierAccount)
    {
        $this->adminOnly();

        $courierAccount->delete();

        return back()->with('success', 'Courier API account deleted successfully.');
    }

    /**
     * Courier Type dropdown source.
     *
     * It loads active couriers from Courier Manage so Courier API Accounts can
     * create accounts for every courier available in the system.
     * SteadFast/Pathao are kept as fallback for backward compatibility.
     */
    private function courierTypeOptions(): Collection
    {
        $fallbackTypes = collect([
            ['name' => 'SteadFast', 'code' => 'steadfast'],
            ['name' => 'Pathao', 'code' => 'pathao'],
        ]);

        $activeCourierTypes = Courier::query()
            ->active()
            ->orderBy('name')
            ->get(['name', 'code'])
            ->map(fn (Courier $courier) => [
                'name' => $courier->name,
                'code' => strtolower((string) $courier->code),
            ]);

        return $fallbackTypes
            ->merge($activeCourierTypes)
            ->filter(fn (array $courier) => ! empty($courier['code']))
            ->unique('code')
            ->values();
    }

    private function allowedCourierCodes(?string $extraCode = null): array
    {
        $codes = $this->courierTypeOptions()
            ->pluck('code')
            ->map(fn ($code) => strtolower((string) $code))
            ->filter()
            ->values();

        if ($extraCode) {
            $codes->push(strtolower($extraCode));
        }

        return $codes->unique()->values()->all();
    }

    private function courierDefaultBaseUrls(Collection $courierTypes): array
    {
        return $courierTypes
            ->pluck('code')
            ->mapWithKeys(fn ($code) => [
                $code => $this->defaultBaseUrl((string) $code),
            ])
            ->filter()
            ->toArray();
    }

    private function defaultBaseUrl(string $code): ?string
    {
        return match (strtolower($code)) {
            'steadfast' => 'https://portal.packzy.com/api/v1',
            'pathao' => 'https://api-hermes.pathao.com',
            default => null,
        };
    }

    private function makeSettings(Request $request): array
    {
        return [
            'store_id' => $request->store_id,
            'delivery_type' => $request->delivery_type ?: 48,
            'item_type' => $request->item_type ?: 2,
            'item_weight' => $request->item_weight ?: 0.5,
            'special_instruction' => $request->special_instruction,
        ];
    }
}
