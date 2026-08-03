@extends('adminlte::page')

@section('title', $title ?? 'Courier API Accounts')

@section('content_header')
    <h1 class="mb-0">{{ $title ?? 'Courier API Accounts' }}</h1>
@endsection

@section('content')
@php
    /*
     * Courier Type dropdown source:
     * - Comes from Courier Manage active couriers.
     * - SteadFast/Pathao are fallback options for backward compatibility.
     */
    $courierTypeOptions = collect($courierTypes ?? [])
        ->mapWithKeys(function ($courier) {
            $code = strtolower((string) data_get($courier, 'code'));
            $name = data_get($courier, 'name') ?: ucwords(str_replace('_', ' ', $code));

            return $code ? [$code => $name] : [];
        })
        ->toArray();

    $courierTypeOptions = array_merge([
        'steadfast' => 'SteadFast',
        'pathao' => 'Pathao',
    ], $courierTypeOptions);

    $courierDefaultBaseUrls = $courierDefaultBaseUrls ?? [
        'steadfast' => 'https://portal.packzy.com/api/v1',
        'pathao' => 'https://api-hermes.pathao.com',
    ];
@endphp

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix errors:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h3 class="card-title mb-0">Add Courier API</h3>
    </div>

    <form action="{{ route('admin.courier-accounts.store') }}" method="POST">
        @csrf

        <div class="card-body">
            <div class="alert alert-info mb-3">
                <strong>Note:</strong>
                SteadFast এর জন্য Base URL:
                <code>https://portal.packzy.com/api/v1</code>
                এবং Pathao এর জন্য Base URL:
                <code>https://api-hermes.pathao.com</code>.
                Other courier type হলে manual Base URL/API credential দিন।
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Courier Name <span class="text-danger">*</span></label>
                        <input type="text"
                               name="name"
                               class="form-control"
                               value="{{ old('name') }}"
                               placeholder="Example: SteadFast Main / Pathao Main"
                               required>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Courier Type <span class="text-danger">*</span></label>
                        <select name="code" class="form-control courier-code-select" data-base-url-target="#base_url_new" required>
                            @foreach($courierTypeOptions as $code => $name)
                                <option value="{{ $code }}" @selected(old('code', 'steadfast') === $code)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Base URL</label>
                        <input type="url"
                               name="base_url"
                               id="base_url_new"
                               class="form-control"
                               value="{{ old('base_url', 'https://portal.packzy.com/api/v1') }}"
                               placeholder="SteadFast: https://portal.packzy.com/api/v1 | Pathao: https://api-hermes.pathao.com">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label class="api-key-label">API Key</label>
                        <input type="text"
                               name="api_key"
                               class="form-control"
                               value="{{ old('api_key') }}"
                               placeholder="SteadFast API Key"
                               autocomplete="off">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label class="secret-key-label">Secret Key</label>
                        <input type="text"
                               name="secret_key"
                               class="form-control"
                               value="{{ old('secret_key') }}"
                               placeholder="SteadFast Secret Key"
                               autocomplete="off">
                    </div>
                </div>

                <div class="col-md-12 manual-token-settings">
                    <div class="form-group">
                        <label>Courier Access Token</label>
                        <textarea name="token"
                                  class="form-control"
                                  rows="2"
                                  placeholder="Courier access token">{{ old('token') }}</textarea>
                    </div>
                </div>
                <div class="col-md-12 pathao-auth-settings">
                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                        Pathao Dynamic Authentication
                    </h6>

                    <div class="alert alert-light border small">
                        Pathao Client ID, Client Secret, merchant username এবং password database-এ save হবে।
                        Access token system নিজে generate/refresh করবে; token paste করতে হবে না।
                        Access token manually paste করতে হবে না; order পাঠানোর সময় system token generate/refresh করবে।
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Pathao Merchant Username <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="auth_username"
                                       class="form-control"
                                       value="{{ old('auth_username') }}"
                                       autocomplete="username"
                                       placeholder="Pathao merchant username">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Pathao Merchant Password <span class="text-danger">*</span></label>
                                <input type="password"
                                       name="auth_password"
                                       class="form-control"
                                       autocomplete="new-password"
                                       placeholder="Pathao merchant password">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                        Courier Settings
                    </h6>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Courier Store ID</label>
                        <input type="text"
                               name="store_id"
                               class="form-control"
                               value="{{ old('store_id') }}"
                               placeholder="Example: 123456">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Delivery Type</label>
                        <select name="delivery_type" class="form-control">
                            <option value="48" @selected(old('delivery_type', 48) == 48)>Normal Delivery - 48</option>
                            <option value="12" @selected(old('delivery_type') == 12)>On Demand - 12</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Item Type</label>
                        <select name="item_type" class="form-control">
                            <option value="2" @selected(old('item_type', 2) == 2)>Parcel - 2</option>
                            <option value="1" @selected(old('item_type') == 1)>Document - 1</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Item Weight</label>
                        <input type="number"
                               step="0.1"
                               min="0.1"
                               name="item_weight"
                               class="form-control"
                               value="{{ old('item_weight', 0.5) }}"
                               placeholder="0.5">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Special Instruction</label>
                        <textarea name="special_instruction"
                                  class="form-control"
                                  rows="2"
                                  placeholder="Please call before delivery">{{ old('special_instruction') }}</textarea>
                    </div>
                </div>

                <div class="col-md-12 steadfast-webhook-settings">
                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                        Courier Webhook & Status Sync
                    </h6>

                    <div class="alert alert-light border small">
                        Courier account save করার পর Edit section-এ account-specific Callback URL দেখা যাবে।
                        SteadFast portal-এ সেই URL এবং নিচের Bearer Token ব্যবহার করবেন।
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Webhook Bearer Token</label>
                                <div class="input-group">
                                    <input type="text"
                                           name="webhook_bearer_token"
                                           class="form-control webhook-token-input"
                                           value="{{ old('webhook_bearer_token') }}"
                                           placeholder="Use a long random token"
                                           autocomplete="off">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary btn-generate-webhook-token">Generate</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Status Sync Interval (Minutes)</label>
                                <input type="number"
                                       min="5"
                                       max="1440"
                                       name="status_sync_interval_minutes"
                                       class="form-control"
                                       value="{{ old('status_sync_interval_minutes', 15) }}">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="custom-control custom-switch d-inline-block mr-4">
                            <input type="hidden" name="webhook_enabled" value="0">
                            <input type="checkbox" name="webhook_enabled" value="1" class="custom-control-input" id="webhook_enabled_new" @checked(old('webhook_enabled'))>
                            <label class="custom-control-label" for="webhook_enabled_new">Webhook Enabled</label>
                        </div>

                        <div class="custom-control custom-switch d-inline-block mr-4">
                            <input type="hidden" name="auto_update_order_status" value="0">
                            <input type="checkbox" name="auto_update_order_status" value="1" class="custom-control-input" id="auto_update_order_status_new" @checked(old('auto_update_order_status', true))>
                            <label class="custom-control-label" for="auto_update_order_status_new">Auto Update Delivered/Cancelled</label>
                        </div>

                        <div class="custom-control custom-switch d-inline-block">
                            <input type="hidden" name="status_sync_enabled" value="0">
                            <input type="checkbox" name="status_sync_enabled" value="1" class="custom-control-input" id="status_sync_enabled_new" @checked(old('status_sync_enabled', true))>
                            <label class="custom-control-label" for="status_sync_enabled_new">API Fallback Sync Enabled</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-12 pathao-webhook-settings">
                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                        Pathao Webhook
                    </h6>

                    <div class="alert alert-light border small">
                        Account save করার পর Edit section-এ Pathao Callback URL দেখা যাবে।
                        Pathao Merchant Panel-এর webhook setup-এ সেই URL এবং নিচের signature secret ব্যবহার করবেন।
                    </div>

                    <div class="form-group">
                        <label>Pathao Webhook Signature Secret</label>
                        <div class="input-group">
                            <input type="text"
                                   name="pathao_webhook_secret"
                                   class="form-control webhook-token-input"
                                   value="{{ old('pathao_webhook_secret') }}"
                                   placeholder="Use a long random secret"
                                   autocomplete="off">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary btn-generate-webhook-token">Generate</button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="custom-control custom-switch d-inline-block mr-4">
                            <input type="hidden" name="webhook_enabled" value="0">
                            <input type="checkbox" name="webhook_enabled" value="1" class="custom-control-input" id="pathao_webhook_enabled_new" @checked(old('webhook_enabled'))>
                            <label class="custom-control-label" for="pathao_webhook_enabled_new">Webhook Enabled</label>
                        </div>

                        <div class="custom-control custom-switch d-inline-block">
                            <input type="hidden" name="auto_update_order_status" value="0">
                            <input type="checkbox" name="auto_update_order_status" value="1" class="custom-control-input" id="pathao_auto_update_order_status_new" @checked(old('auto_update_order_status', true))>
                            <label class="custom-control-label" for="pathao_auto_update_order_status_new">Auto Update Delivered/Cancelled</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="custom-control custom-switch d-inline-block mr-4">
                        <input type="checkbox"
                               name="is_default"
                               value="1"
                               class="custom-control-input"
                               id="is_default_new"
                               @checked(old('is_default'))>
                        <label class="custom-control-label" for="is_default_new">Default Courier</label>
                    </div>

                    <div class="custom-control custom-switch d-inline-block">
                        <input type="checkbox"
                               name="status"
                               value="1"
                               class="custom-control-input"
                               id="status_new"
                               @checked(old('status', true))>
                        <label class="custom-control-label" for="status_new">Active</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white text-right">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>
                Save Courier
            </button>
        </div>
    </form>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h3 class="card-title mb-0">Courier API List</h3>
    </div>

    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Base URL</th>
                <th>Status</th>
                <th>Default</th>
                <th width="260">Action</th>
            </tr>
            </thead>

            <tbody>
            @forelse($couriers as $courier)
                <tr>
                    <td>{{ $courier->name }}</td>
                    <td>
                        <span class="badge badge-info">
                            {{ $courierTypeOptions[$courier->code] ?? ucfirst($courier->code) }}
                        </span>
                    </td>
                    <td class="small">{{ $courier->base_url ?: '-' }}</td>
                    <td>
                        <span class="badge {{ $courier->status ? 'badge-success' : 'badge-secondary' }}">
                            {{ $courier->status ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        @if($courier->is_default)
                            <span class="badge badge-primary">Default</span>
                        @else
                            <span class="text-muted">No</span>
                        @endif
                    </td>
                    <td>
                        <button type="button"
                                class="btn btn-sm btn-warning"
                                data-toggle="collapse"
                                data-target="#editCourier{{ $courier->id }}">
                            Edit
                        </button>

                        <form action="{{ route('admin.courier-accounts.destroy', $courier->id) }}"
                              method="POST"
                              class="d-inline"
                              onsubmit="return confirm('Delete this courier API account?')">
                            @csrf
                            @method('DELETE')

                            <button type="submit" class="btn btn-sm btn-danger">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>

                <tr class="collapse" id="editCourier{{ $courier->id }}">
                    <td colspan="6">
                        <form action="{{ route('admin.courier-accounts.update', $courier->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            @php
                                $editCourierTypeOptions = $courierTypeOptions;

                                if (! isset($editCourierTypeOptions[$courier->code])) {
                                    $editCourierTypeOptions[$courier->code] = ucwords(str_replace('_', ' ', $courier->code));
                                }
                            @endphp

                            <div class="row p-3 bg-light">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Courier Name</label>
                                        <input type="text"
                                               name="name"
                                               class="form-control"
                                               value="{{ old('name', $courier->name) }}"
                                               required>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Courier Type</label>
                                        <select name="code"
                                                class="form-control courier-code-select"
                                                data-base-url-target="#base_url_{{ $courier->id }}"
                                                required>
                                            @foreach($editCourierTypeOptions as $code => $name)
                                                <option value="{{ $code }}" @selected(old('code', $courier->code) === $code)>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Base URL</label>
                                        <input type="url"
                                               name="base_url"
                                               id="base_url_{{ $courier->id }}"
                                               class="form-control"
                                               value="{{ old('base_url', $courier->base_url) }}">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label class="api-key-label">API Key</label>
                                        <input type="text"
                                               name="api_key"
                                               class="form-control"
                                               value="{{ old('api_key', $courier->api_key) }}">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>
                                            <span class="secret-key-label">Secret Key</span>
                                            @if($courier->hasStoredSecretKey())
                                                <span class="badge badge-success ml-1">Saved</span>
                                            @else
                                                <span class="badge badge-warning ml-1">Not Saved</span>
                                            @endif
                                        </label>
                                        <input type="password"
                                               name="secret_key"
                                               class="form-control"
                                               value=""
                                               autocomplete="new-password"
                                               data-lpignore="true"
                                               data-1p-ignore="true"
                                               placeholder="{{ $courier->hasStoredSecretKey() ? '••••••••••••  Saved — blank রাখলে অপরিবর্তিত থাকবে' : 'Enter Pathao Client Secret' }}">
                                        @if($courier->hasStoredSecretKey())
                                            <small class="text-success">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Client Secret database-এ saved আছে। Security-এর জন্য actual value দেখানো হচ্ছে না।
                                            </small>
                                        @else
                                            <small class="text-danger">Pathao Client Secret এখনো save করা হয়নি।</small>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-12 manual-token-settings">
                                    <div class="form-group">
                                        <label>Token / Courier Access Token</label>
                                        <textarea name="token" class="form-control" rows="2">{{ old('token', strtolower((string) $courier->code) === 'pathao' ? '' : $courier->token) }}</textarea>
                                    </div>
                                </div>

                                <div class="col-md-12 pathao-auth-settings">
                                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                                        Pathao Dynamic Authentication
                                    </h6>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Pathao Merchant Username</label>
                                                <input type="text"
                                                       name="auth_username"
                                                       class="form-control"
                                                       value="{{ old('auth_username', $courier->auth_username) }}"
                                                       autocomplete="username">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>
                                                    Pathao Merchant Password
                                                    @if($courier->hasStoredAuthPassword())
                                                        <span class="badge badge-success ml-1">Saved & Encrypted</span>
                                                    @else
                                                        <span class="badge badge-warning ml-1">Not Saved</span>
                                                    @endif
                                                </label>
                                                <input type="password"
                                                       name="auth_password"
                                                       class="form-control"
                                                       autocomplete="new-password"
                                                       data-lpignore="true"
                                                       data-1p-ignore="true"
                                                       placeholder="{{ $courier->hasStoredAuthPassword() ? '••••••••••••  Saved — blank রাখলে অপরিবর্তিত থাকবে' : 'Enter Pathao Merchant Password' }}">
                                                @if($courier->hasStoredAuthPassword())
                                                    <small class="text-success">
                                                        <i class="fas fa-lock mr-1"></i>
                                                        Merchant Password encrypted অবস্থায় database-এ saved আছে।
                                                    </small>
                                                @else
                                                    <small class="text-danger">Pathao Merchant Password এখনো save করা হয়নি।</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @php
                                        $pathaoTokenState = blank($courier->token)
                                            ? 'missing'
                                            : (($courier->token_expires_at && $courier->token_expires_at->isPast()) ? 'expired' : 'active');
                                        $pathaoTokenLabel = match ($pathaoTokenState) {
                                            'active' => 'Token Available',
                                            'expired' => 'Token Expired',
                                            default => 'Token Missing',
                                        };
                                        $pathaoTokenBadge = match ($pathaoTokenState) {
                                            'active' => 'badge-success',
                                            'expired' => 'badge-warning',
                                            default => 'badge-secondary',
                                        };
                                    @endphp

                                    <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap">
                                        <div>
                                            <strong>Access Token:</strong>
                                            <span class="badge {{ $pathaoTokenBadge }}">{{ $pathaoTokenLabel }}</span>
                                            @if($courier->token_expires_at)
                                                <span class="small text-muted ml-2">
                                                    Expires: {{ $courier->token_expires_at->format('d M Y, h:i A') }}
                                                </span>
                                            @endif
                                        </div>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary btn-refresh-pathao-token"
                                                data-url="{{ route('admin.courier-accounts.refresh-pathao-token', $courier->id) }}">
                                            <i class="fas fa-sync-alt mr-1"></i>
                                            Generate / Refresh Token
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                                        Courier Settings
                                    </h6>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Courier Store ID</label>
                                        <input type="text"
                                               name="store_id"
                                               class="form-control"
                                               value="{{ old('store_id', data_get($courier->settings, 'store_id')) }}">
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Delivery Type</label>
                                        <select name="delivery_type" class="form-control">
                                            <option value="48" @selected(old('delivery_type', data_get($courier->settings, 'delivery_type', 48)) == 48)>Normal Delivery - 48</option>
                                            <option value="12" @selected(old('delivery_type', data_get($courier->settings, 'delivery_type')) == 12)>On Demand - 12</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Item Type</label>
                                        <select name="item_type" class="form-control">
                                            <option value="2" @selected(old('item_type', data_get($courier->settings, 'item_type', 2)) == 2)>Parcel - 2</option>
                                            <option value="1" @selected(old('item_type', data_get($courier->settings, 'item_type')) == 1)>Document - 1</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Item Weight</label>
                                        <input type="number"
                                               step="0.1"
                                               min="0.1"
                                               name="item_weight"
                                               class="form-control"
                                               value="{{ old('item_weight', data_get($courier->settings, 'item_weight', 0.5)) }}">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Special Instruction</label>
                                        <textarea name="special_instruction"
                                                  class="form-control"
                                                  rows="2">{{ old('special_instruction', data_get($courier->settings, 'special_instruction')) }}</textarea>
                                    </div>
                                </div>

                                <div class="col-md-12 steadfast-webhook-settings" data-current-code="{{ $courier->code }}">
                                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                                        SteadFast Webhook & Status Sync
                                    </h6>

                                    <div class="form-group">
                                        <label>SteadFast Callback URL</label>
                                        <div class="input-group">
                                            <input type="text"
                                                   class="form-control webhook-callback-url"
                                                   value="{{ route('webhooks.steadfast', $courier->id) }}"
                                                   readonly>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary btn-copy-webhook-url">Copy</button>
                                            </div>
                                        </div>
                                        <small class="text-muted">SteadFast portal Callback URL হিসেবে এটি ব্যবহার করুন।</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label>Webhook Bearer Token</label>
                                                <div class="input-group">
                                                    <input type="text"
                                                           name="webhook_bearer_token"
                                                           class="form-control webhook-token-input"
                                                           value="{{ old('webhook_bearer_token', data_get($courier->settings, 'webhook_bearer_token')) }}"
                                                           autocomplete="off">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-outline-secondary btn-generate-webhook-token">Generate</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Status Sync Interval (Minutes)</label>
                                                <input type="number"
                                                       min="5"
                                                       max="1440"
                                                       name="status_sync_interval_minutes"
                                                       class="form-control"
                                                       value="{{ old('status_sync_interval_minutes', data_get($courier->settings, 'status_sync_interval_minutes', 15)) }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="custom-control custom-switch d-inline-block mr-4">
                                            <input type="hidden" name="webhook_enabled" value="0">
                                            <input type="checkbox" name="webhook_enabled" value="1" class="custom-control-input" id="webhook_enabled_{{ $courier->id }}" @checked(old('webhook_enabled', data_get($courier->settings, 'webhook_enabled', false)))>
                                            <label class="custom-control-label" for="webhook_enabled_{{ $courier->id }}">Webhook Enabled</label>
                                        </div>

                                        <div class="custom-control custom-switch d-inline-block mr-4">
                                            <input type="hidden" name="auto_update_order_status" value="0">
                                            <input type="checkbox" name="auto_update_order_status" value="1" class="custom-control-input" id="auto_update_order_status_{{ $courier->id }}" @checked(old('auto_update_order_status', data_get($courier->settings, 'auto_update_order_status', true)))>
                                            <label class="custom-control-label" for="auto_update_order_status_{{ $courier->id }}">Auto Update Delivered/Cancelled</label>
                                        </div>

                                        <div class="custom-control custom-switch d-inline-block">
                                            <input type="hidden" name="status_sync_enabled" value="0">
                                            <input type="checkbox" name="status_sync_enabled" value="1" class="custom-control-input" id="status_sync_enabled_{{ $courier->id }}" @checked(old('status_sync_enabled', data_get($courier->settings, 'status_sync_enabled', true)))>
                                            <label class="custom-control-label" for="status_sync_enabled_{{ $courier->id }}">API Fallback Sync Enabled</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 pathao-webhook-settings" data-current-code="{{ $courier->code }}">
                                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                                        Pathao Webhook
                                    </h6>

                                    <div class="form-group">
                                        <label>Pathao Callback URL</label>
                                        <div class="input-group">
                                            <input type="text"
                                                   class="form-control webhook-callback-url"
                                                   value="{{ route('webhooks.pathao', $courier->id) }}"
                                                   readonly>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary btn-copy-webhook-url">Copy</button>
                                            </div>
                                        </div>
                                        <small class="text-muted">Pathao Merchant Panel webhook URL হিসেবে এটি ব্যবহার করুন।</small>
                                    </div>

                                    <div class="form-group">
                                        <label>Pathao Webhook Signature Secret</label>
                                        <div class="input-group">
                                            <input type="text"
                                                   name="pathao_webhook_secret"
                                                   class="form-control webhook-token-input"
                                                   value="{{ old('pathao_webhook_secret', data_get($courier->settings, 'pathao_webhook_secret')) }}"
                                                   autocomplete="off">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary btn-generate-webhook-token">Generate</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="custom-control custom-switch d-inline-block mr-4">
                                            <input type="hidden" name="webhook_enabled" value="0">
                                            <input type="checkbox" name="webhook_enabled" value="1" class="custom-control-input" id="pathao_webhook_enabled_{{ $courier->id }}" @checked(old('webhook_enabled', data_get($courier->settings, 'webhook_enabled', false)))>
                                            <label class="custom-control-label" for="pathao_webhook_enabled_{{ $courier->id }}">Webhook Enabled</label>
                                        </div>

                                        <div class="custom-control custom-switch d-inline-block">
                                            <input type="hidden" name="auto_update_order_status" value="0">
                                            <input type="checkbox" name="auto_update_order_status" value="1" class="custom-control-input" id="pathao_auto_update_order_status_{{ $courier->id }}" @checked(old('auto_update_order_status', data_get($courier->settings, 'auto_update_order_status', true)))>
                                            <label class="custom-control-label" for="pathao_auto_update_order_status_{{ $courier->id }}">Auto Update Delivered/Cancelled</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="custom-control custom-switch d-inline-block mr-4">
                                        <input type="checkbox"
                                               name="is_default"
                                               value="1"
                                               class="custom-control-input"
                                               id="is_default_{{ $courier->id }}"
                                               @checked(old('is_default', $courier->is_default))>
                                        <label class="custom-control-label" for="is_default_{{ $courier->id }}">
                                            Default Courier
                                        </label>
                                    </div>

                                    <div class="custom-control custom-switch d-inline-block mr-4">
                                        <input type="checkbox"
                                               name="status"
                                               value="1"
                                               class="custom-control-input"
                                               id="status_{{ $courier->id }}"
                                               @checked(old('status', $courier->status))>
                                        <label class="custom-control-label" for="status_{{ $courier->id }}">
                                            Active
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-success">
                                        Update Courier
                                    </button>
                                </div>
                            </div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        No courier API account found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($couriers, 'links'))
        <div class="card-footer bg-white">
            {{ $couriers->links() }}
        </div>
    @endif
</div>
@endsection

@section('js')
<script>
$(document).ready(function () {
    const courierDefaultBaseUrls = @json($courierDefaultBaseUrls);

    function defaultBaseUrl(code) {
        return courierDefaultBaseUrls[code] || '';
    }

    function knownDefaultBaseUrls() {
        return Object.values(courierDefaultBaseUrls).filter(Boolean);
    }

    function setSectionState(section, visible) {
        section.toggle(visible);
        section.find(':input').prop('disabled', !visible);
    }

    function toggleProviderSettings(select) {
        const form = $(select).closest('form');
        const code = String($(select).val() || '').toLowerCase();
        const isSteadfast = code === 'steadfast';
        const isPathao = code === 'pathao';

        setSectionState(form.find('.steadfast-webhook-settings'), isSteadfast);
        setSectionState(form.find('.pathao-auth-settings'), isPathao);
        setSectionState(form.find('.pathao-webhook-settings'), isPathao);
        setSectionState(form.find('.manual-token-settings'), !isPathao);

        form.find('.api-key-label').text(isPathao ? 'Pathao Client ID' : 'API Key');
        form.find('.secret-key-label').text(isPathao ? 'Pathao Client Secret' : 'Secret Key');
        form.find('input[name="api_key"]').attr(
            'placeholder',
            isPathao ? 'Pathao Client ID' : (isSteadfast ? 'SteadFast API Key' : 'API Key')
        );
        form.find('input[name="secret_key"]').attr(
            'placeholder',
            isPathao ? 'Pathao Client Secret' : (isSteadfast ? 'SteadFast Secret Key' : 'Secret Key')
        );
    }

    $(document).on('change', '.courier-code-select', function () {
        const code = $(this).val();
        const target = $($(this).data('base-url-target'));
        const newDefaultUrl = defaultBaseUrl(code);
        const currentUrl = target.val() || '';

        toggleProviderSettings(this);

        if (!target.length) {
            return;
        }

        if (!currentUrl) {
            target.val(newDefaultUrl);
            return;
        }

        const isKnownDefaultUrl = knownDefaultBaseUrls().some(function (url) {
            return currentUrl.includes(url);
        });

        if (isKnownDefaultUrl && newDefaultUrl) {
            target.val(newDefaultUrl);
        }
    });

    $('.courier-code-select').each(function () {
        toggleProviderSettings(this);
    });

    $(document).on('click', '.btn-generate-webhook-token', function () {
        const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let token = '';

        if (window.crypto && window.crypto.getRandomValues) {
            const bytes = new Uint8Array(48);
            window.crypto.getRandomValues(bytes);
            bytes.forEach(function (byte) {
                token += alphabet[byte % alphabet.length];
            });
        } else {
            for (let i = 0; i < 48; i++) {
                token += alphabet[Math.floor(Math.random() * alphabet.length)];
            }
        }

        $(this).closest('.input-group').find('.webhook-token-input').val(token);
    });

    $(document).on('click', '.btn-refresh-pathao-token', function () {
        const url = $(this).data('url');

        if (!url) {
            return;
        }

        if (!window.confirm('Saved Pathao credentials দিয়ে access token generate/refresh করবেন?')) {
            return;
        }

        const form = $('<form>', {
            method: 'POST',
            action: url
        });

        form.append($('<input>', {
            type: 'hidden',
            name: '_token',
            value: @json(csrf_token())
        }));

        $('body').append(form);
        form.trigger('submit');
    });

    $(document).on('click', '.btn-copy-webhook-url', function () {
        const input = $(this).closest('.input-group').find('.webhook-callback-url').get(0);

        if (!input) return;

        input.select();
        input.setSelectionRange(0, 99999);

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value);
        } else {
            document.execCommand('copy');
        }

        const button = $(this);
        const oldText = button.text();
        button.text('Copied');
        setTimeout(function () { button.text(oldText); }, 1200);
    });
});
</script>
@endsection
