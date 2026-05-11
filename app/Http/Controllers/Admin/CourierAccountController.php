<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourierAccount;
use Illuminate\Http\Request;
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

        return view('admin.courier-accounts.index', [
            'title' => 'Courier API Accounts',
            'couriers' => CourierAccount::query()->latest()->paginate(20),
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
            'code' => ['required', 'string', Rule::in(['steadfast', 'pathao'])],

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
            'code' => ['required', 'string', Rule::in(['steadfast', 'pathao'])],

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

    private function defaultBaseUrl(string $code): ?string
    {
        return match ($code) {
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