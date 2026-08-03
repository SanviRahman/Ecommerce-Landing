<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Services\PathaoCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

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

        $validated = $this->validateAccount($request);

        DB::transaction(function () use ($request, $validated): void {
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
                'auth_username' => $code === 'pathao'
                    ? ($validated['auth_username'] ?? null)
                    : null,
                'auth_password' => $code === 'pathao'
                    ? ($validated['auth_password'] ?? null)
                    : null,
                'token' => $code === 'pathao'
                    ? null
                    : ($validated['token'] ?? null),
                'refresh_token' => null,
                'token_type' => null,
                'token_expires_at' => null,
                'settings' => $this->makeSettings($request),
                'is_default' => $request->boolean('is_default'),
                'status' => $request->boolean('status', true),
            ]);
        });

        return back()->with(
            'success',
            strtolower($validated['code']) === 'pathao'
                ? 'Pathao API account created. Edit the account to generate/test the access token.'
                : 'Courier API account created successfully.'
        );
    }

    public function update(Request $request, CourierAccount $courierAccount)
    {
        $this->adminOnly();

        $validated = $this->validateAccount($request, $courierAccount);

        DB::transaction(function () use ($request, $validated, $courierAccount): void {
            $code = strtolower($validated['code']);

            if ($request->boolean('is_default')) {
                CourierAccount::query()
                    ->where('code', $code)
                    ->where('id', '!=', $courierAccount->id)
                    ->update(['is_default' => false]);
            }

            $pathaoCredentialsChanged = $this->pathaoCredentialsChanged(
                $courierAccount,
                $code,
                $validated,
                $request
            );

            $updates = [
                'name' => $validated['name'],
                'code' => $code,
                'base_url' => $validated['base_url'] ?: $this->defaultBaseUrl($code),
                'api_key' => $validated['api_key'] ?? null,
                'secret_key' => $request->filled('secret_key')
                    ? $validated['secret_key']
                    : $courierAccount->secret_key,
                'auth_username' => $code === 'pathao'
                    ? ($validated['auth_username'] ?? null)
                    : null,
                'settings' => $this->makeSettings($request, $courierAccount),
                'is_default' => $request->boolean('is_default'),
                'status' => $request->boolean('status'),
            ];

            if ($code === 'pathao') {
                if ($request->filled('auth_password')) {
                    $updates['auth_password'] = $validated['auth_password'];
                } elseif (strtolower((string) $courierAccount->code) !== 'pathao') {
                    $updates['auth_password'] = null;
                }

                if ($pathaoCredentialsChanged) {
                    $updates['token'] = null;
                    $updates['refresh_token'] = null;
                    $updates['token_type'] = null;
                    $updates['token_expires_at'] = null;
                }
            } else {
                $updates['auth_password'] = null;
                $updates['token'] = $validated['token'] ?? null;
                $updates['refresh_token'] = null;
                $updates['token_type'] = null;
                $updates['token_expires_at'] = null;
            }

            $courierAccount->update($updates);
        });

        return back()->with(
            'success',
            strtolower($validated['code']) === 'pathao'
                ? 'Pathao API account updated. Saved Client Secret and Merchant Password remain unchanged when their fields are left blank.'
                : 'Courier API account updated successfully.'
        );
    }

    public function refreshPathaoToken(
        CourierAccount $courierAccount,
        PathaoCourierService $pathaoCourierService
    ) {
        $this->adminOnly();

        if (strtolower((string) $courierAccount->code) !== 'pathao') {
            return back()->withErrors([
                'pathao_token' => 'Selected courier account is not Pathao.',
            ]);
        }

        if (! $courierAccount->status) {
            return back()->withErrors([
                'pathao_token' => 'Pathao courier account is inactive.',
            ]);
        }

        try {
            $token = $pathaoCourierService->refreshToken($courierAccount);

            return back()->with(
                'success',
                'Pathao access token generated successfully. Expires at: '
                    . optional($token['expires_at'] ?? null)->format('d M Y, h:i A')
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'pathao_token' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Pathao token generation failed.',
            ]);
        }
    }

    public function destroy(CourierAccount $courierAccount)
    {
        $this->adminOnly();

        $courierAccount->delete();

        return back()->with('success', 'Courier API account deleted successfully.');
    }

    private function validateAccount(
        Request $request,
        ?CourierAccount $courierAccount = null
    ): array {
        $code = strtolower((string) $request->input('code'));
        $isPathao = $code === 'pathao';
        $isSteadfast = $code === 'steadfast';
        $pathaoPasswordRequired = $isPathao
            && (! $courierAccount || blank($courierAccount->auth_password));
        $pathaoSecretRequired = $isPathao
            && (! $courierAccount || blank($courierAccount->secret_key));

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                Rule::in($this->allowedCourierCodes($courierAccount?->code)),
            ],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => [
                Rule::requiredIf($isPathao),
                'nullable',
                'string',
                'max:1000',
            ],
            'secret_key' => [
                Rule::requiredIf($pathaoSecretRequired),
                'nullable',
                'string',
                'max:1000',
            ],
            'auth_username' => [
                Rule::requiredIf($isPathao),
                'nullable',
                'string',
                'max:255',
            ],
            'auth_password' => [
                Rule::requiredIf($pathaoPasswordRequired),
                'nullable',
                'string',
                'max:1000',
            ],
            'token' => ['nullable', 'string', 'max:10000'],
            'store_id' => [Rule::requiredIf($isPathao), 'nullable', 'string', 'max:255'],
            'delivery_type' => ['nullable', 'integer'],
            'item_type' => ['nullable', 'integer'],
            'item_weight' => ['nullable', 'numeric', 'min:0.1'],
            'special_instruction' => ['nullable', 'string', 'max:1000'],
            'webhook_bearer_token' => [
                Rule::requiredIf($isSteadfast && $request->boolean('webhook_enabled')),
                'nullable',
                'string',
                'min:24',
                'max:1000',
            ],
            'pathao_webhook_secret' => [
                Rule::requiredIf($isPathao && $request->boolean('webhook_enabled')),
                'nullable',
                'string',
                'min:24',
                'max:1000',
            ],
            'webhook_enabled' => ['nullable', 'boolean'],
            'auto_update_order_status' => ['nullable', 'boolean'],
            'status_sync_enabled' => ['nullable', 'boolean'],
            'status_sync_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);
    }

    private function pathaoCredentialsChanged(
        CourierAccount $courierAccount,
        string $code,
        array $validated,
        Request $request
    ): bool {
        if ($code !== 'pathao') {
            return false;
        }

        return strtolower((string) $courierAccount->code) !== 'pathao'
            || trim((string) $courierAccount->base_url) !== trim((string) ($validated['base_url'] ?: $this->defaultBaseUrl('pathao')))
            || trim((string) $courierAccount->api_key) !== trim((string) ($validated['api_key'] ?? ''))
            || ($request->filled('secret_key')
                && trim((string) $courierAccount->secret_key) !== trim((string) ($validated['secret_key'] ?? '')))
            || trim((string) $courierAccount->auth_username) !== trim((string) ($validated['auth_username'] ?? ''))
            || $request->filled('auth_password');
    }

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

    private function makeSettings(
        Request $request,
        ?CourierAccount $courierAccount = null
    ): array {
        $code = strtolower((string) $request->input('code'));
        $isSteadfast = $code === 'steadfast';
        $isPathao = $code === 'pathao';

        return [
            'store_id' => $request->store_id,
            'delivery_type' => $request->delivery_type ?: 48,
            'item_type' => $request->item_type ?: 2,
            'item_weight' => $request->item_weight ?: 0.5,
            'special_instruction' => $request->special_instruction,
            'webhook_bearer_token' => $isSteadfast
                ? (trim((string) $request->webhook_bearer_token) ?: null)
                : null,
            'pathao_webhook_secret' => $isPathao
                ? (trim((string) $request->pathao_webhook_secret) ?: null)
                : null,
            'webhook_enabled' => ($isSteadfast || $isPathao)
                && $request->boolean('webhook_enabled'),
            'auto_update_order_status' => ($isSteadfast || $isPathao)
                && $request->boolean('auto_update_order_status'),
            'status_sync_enabled' => $isSteadfast
                && $request->boolean('status_sync_enabled'),
            'status_sync_interval_minutes' => min(
                max((int) ($request->status_sync_interval_minutes ?: 15), 5),
                1440
            ),
        ];
    }
}
