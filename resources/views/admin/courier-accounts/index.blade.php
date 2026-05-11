@extends('adminlte::page')

@section('title', $title ?? 'Courier API Accounts')

@section('content_header')
    <h1 class="mb-0">{{ $title ?? 'Courier API Accounts' }}</h1>
@endsection

@section('content')
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
                <code>https://api-hermes.pathao.com</code>
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
                            <option value="steadfast" @selected(old('code') === 'steadfast')>SteadFast</option>
                            <option value="pathao" @selected(old('code') === 'pathao')>Pathao</option>
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
                        <label>API Key</label>
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
                        <label>Secret Key</label>
                        <input type="text"
                               name="secret_key"
                               class="form-control"
                               value="{{ old('secret_key') }}"
                               placeholder="SteadFast Secret Key"
                               autocomplete="off">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Token / Pathao Access Token</label>
                        <textarea name="token"
                                  class="form-control"
                                  rows="2"
                                  placeholder="Pathao access token">{{ old('token') }}</textarea>
                    </div>
                </div>

                <div class="col-md-12">
                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                        Pathao Settings
                    </h6>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Pathao Store ID</label>
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
                            {{ ucfirst($courier->code) }}
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
                                            <option value="steadfast" @selected(old('code', $courier->code) === 'steadfast')>SteadFast</option>
                                            <option value="pathao" @selected(old('code', $courier->code) === 'pathao')>Pathao</option>
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
                                        <label>API Key</label>
                                        <input type="text"
                                               name="api_key"
                                               class="form-control"
                                               value="{{ old('api_key', $courier->api_key) }}">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Secret Key</label>
                                        <input type="text"
                                               name="secret_key"
                                               class="form-control"
                                               value="{{ old('secret_key', $courier->secret_key) }}">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Token / Pathao Access Token</label>
                                        <textarea name="token" class="form-control" rows="2">{{ old('token', $courier->token) }}</textarea>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <h6 class="font-weight-bold text-muted border-bottom pb-2 mt-2">
                                        Pathao Settings
                                    </h6>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Pathao Store ID</label>
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
    function defaultBaseUrl(code) {
        if (code === 'pathao') {
            return 'https://api-hermes.pathao.com';
        }

        if (code === 'steadfast') {
            return 'https://portal.packzy.com/api/v1';
        }

        return '';
    }

    $(document).on('change', '.courier-code-select', function () {
        const code = $(this).val();
        const target = $($(this).data('base-url-target'));

        if (!target.val()) {
            target.val(defaultBaseUrl(code));
            return;
        }

        if (target.val().includes('portal.steadfast.com.bd')) {
            target.val(defaultBaseUrl(code));
            return;
        }

        if (target.val().includes('portal.packzy.com') || target.val().includes('api-hermes.pathao.com')) {
            target.val(defaultBaseUrl(code));
        }
    });
});
</script>
@endsection