@extends('adminlte::page')

@section('title', $title ?? 'Website Order Sync')
@section('plugins.Sweetalert2', true)

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Website Order Sync' }}</h1>

        @if(isset($breadcrumb))
            <ol class="breadcrumb mt-2 mb-0 bg-transparent p-0">
                @foreach($breadcrumb as $item)
                    <li class="breadcrumb-item">
                        <a href="{{ $item['url'] }}">{{ $item['text'] }}</a>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

@if(session('new_api_token'))
    <div class="card border-success shadow-sm credential-result-card">
        <div class="card-header bg-success text-white">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-check-circle mr-1"></i>
                Receiver Credentials Saved
            </h3>
        </div>
        <div class="card-body">
            <div class="alert alert-warning mb-3">
                Copy these receiver credentials to the connected website's
                <strong>Send Orders</strong> section. Integration endpoint and token values are stored in the database;
                no integration-specific <code>.env</code> value is required.
            </div>

            <div class="row">
                <div class="col-lg-5">
                    <div class="form-group">
                        <label>Order Receiver Endpoint</label>
                        <div class="input-group">
                            <input type="text"
                                   value="{{ session('new_api_endpoint') }}"
                                   class="form-control bg-white integration-copy-value"
                                   readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary btn-copy-value">
                                    <i class="far fa-copy mr-1"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="form-group">
                        <label>Receiver Authentication Token</label>
                        <div class="input-group">
                            <input type="text"
                                   value="{{ session('new_api_token') }}"
                                   class="form-control bg-white integration-copy-value"
                                   readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary btn-copy-value">
                                    <i class="far fa-copy mr-1"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="form-group">
                        <label>Health Check Endpoint</label>
                        <div class="input-group">
                            <input type="text"
                                   value="{{ session('new_health_endpoint') }}"
                                   class="form-control bg-white integration-copy-value"
                                   readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary btn-copy-value">
                                    <i class="far fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
        <ul class="mb-0 pl-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

<div class="alert alert-info border-0 shadow-sm">
    <div class="d-flex align-items-start">
        <i class="fas fa-exchange-alt fa-lg mt-1 mr-3"></i>
        <div>
            <strong>Bidirectional order sync:</strong>
            The same integration can receive orders from a website and send local orders back to that website.
            Received API orders are never sent again, which prevents DeshBajar ↔ AshBazar infinite loops.
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-9">
        <div class="card shadow-sm border-0 mb-4 integration-card">
            <div class="card-header bg-white border-0 py-3">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-plus-circle text-primary mr-1"></i>
                    Add Bidirectional Website Integration
                </h3>
            </div>

            <form action="{{ route('admin.external-websites.store') }}"
                  method="POST"
                  class="token-settings-form website-integration-form">
                @csrf

                <div class="card-body">
                    <div class="section-title">
                        <i class="fas fa-globe mr-1"></i> Website Information
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Website Name <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="name"
                                       value="{{ old('name') }}"
                                       class="form-control @error('name') is-invalid @enderror"
                                       placeholder="Example: AshBazar"
                                       required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Website Domain <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="domain"
                                       value="{{ old('domain') }}"
                                       class="form-control @error('domain') is-invalid @enderror"
                                       placeholder="https://ashbazar.com"
                                       required>
                                <small class="form-text text-muted">Used only to identify the connected website.</small>
                                @error('domain')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Integration Status</label>
                                <input type="hidden" name="status" value="0">
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox"
                                           name="status"
                                           value="1"
                                           class="custom-control-input"
                                           id="new_website_status"
                                           @checked((bool) old('status', true))>
                                    <label class="custom-control-label" for="new_website_status">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="direction-panel direction-receive mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                            <div>
                                <h5 class="mb-1 text-success">
                                    <i class="fas fa-download mr-1"></i>
                                    Receive Orders From This Website
                                </h5>
                                <small class="text-muted">These local credentials will be pasted into the external website's Send Orders section.</small>
                            </div>

                            <div>
                                <input type="hidden" name="receive_orders" value="0">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox"
                                           name="receive_orders"
                                           value="1"
                                           class="custom-control-input"
                                           id="new_receive_orders"
                                           @checked((bool) old('receive_orders', true))>
                                    <label class="custom-control-label" for="new_receive_orders">Receive Enabled</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-md-0">
                                    <label>Receiver Token Setup <span class="text-danger">*</span></label>
                                    <select name="token_action"
                                            class="form-control token-action-select @error('token_action') is-invalid @enderror"
                                            required>
                                        <option value="generate" @selected(old('token_action', 'generate') === 'generate')>
                                            Generate token automatically
                                        </option>
                                        <option value="manual" @selected(old('token_action') === 'manual')>
                                            Paste a custom token
                                        </option>
                                    </select>
                                    @error('token_action')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="form-group mb-0">
                                    <label>Receiver Authentication Token</label>
                                    <div class="input-group">
                                        <input type="text"
                                               name="api_token"
                                               value="{{ old('api_token') }}"
                                               class="form-control api-token-input @error('api_token') is-invalid @enderror"
                                               placeholder="Paste a token of at least 32 characters"
                                               autocomplete="off">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-generate-token">
                                                Generate
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted token-help-text"></small>
                                    @error('api_token')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="direction-panel direction-send">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                            <div>
                                <h5 class="mb-1 text-primary">
                                    <i class="fas fa-upload mr-1"></i>
                                    Send Local Orders To This Website
                                </h5>
                                <small class="text-muted">Paste the endpoint and token generated inside the external website.</small>
                            </div>

                            <div class="d-flex align-items-center flex-wrap">
                                <input type="hidden" name="send_orders" value="0">
                                <div class="custom-control custom-switch mr-3">
                                    <input type="checkbox"
                                           name="send_orders"
                                           value="1"
                                           class="custom-control-input send-orders-toggle"
                                           id="new_send_orders"
                                           @checked((bool) old('send_orders', false))>
                                    <label class="custom-control-label" for="new_send_orders">Send Enabled</label>
                                </div>

                                <input type="hidden" name="auto_send_orders" value="0">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox"
                                           name="auto_send_orders"
                                           value="1"
                                           class="custom-control-input"
                                           id="new_auto_send_orders"
                                           @checked((bool) old('auto_send_orders', true))>
                                    <label class="custom-control-label" for="new_auto_send_orders">Auto Send</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-7">
                                <div class="form-group">
                                    <label>External Website Receiver Endpoint</label>
                                    <input type="text"
                                           name="remote_order_endpoint"
                                           value="{{ old('remote_order_endpoint') }}"
                                           class="form-control outbound-required @error('remote_order_endpoint') is-invalid @enderror"
                                           placeholder="https://ashbazar.com/api/external-orders/deshbajar">
                                    @error('remote_order_endpoint')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>External Receiver Token</label>
                                    <div class="input-group">
                                        <input type="password"
                                               name="remote_api_token"
                                               value="{{ old('remote_api_token') }}"
                                               class="form-control outbound-required remote-token-input @error('remote_api_token') is-invalid @enderror"
                                               placeholder="Token generated on the external website"
                                               autocomplete="new-password">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-toggle-field-token">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    @error('remote_api_token')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="form-group mb-md-0">
                                    <label>External Health Check Endpoint <small class="text-muted">(Optional)</small></label>
                                    <input type="text"
                                           name="remote_health_endpoint"
                                           value="{{ old('remote_health_endpoint') }}"
                                           class="form-control @error('remote_health_endpoint') is-invalid @enderror"
                                           placeholder="Leave blank to use Receiver Endpoint + /status">
                                    @error('remote_health_endpoint')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <label>Request Timeout</label>
                                    <div class="input-group">
                                        <input type="number"
                                               name="request_timeout"
                                               value="{{ old('request_timeout', 15) }}"
                                               min="3"
                                               max="120"
                                               class="form-control @error('request_timeout') is-invalid @enderror"
                                               required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">seconds</span>
                                        </div>
                                    </div>
                                    @error('request_timeout')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4 mb-0">
                        <label>Notes</label>
                        <textarea name="notes"
                                  rows="2"
                                  class="form-control @error('notes') is-invalid @enderror"
                                  placeholder="Optional internal notes">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="card-footer bg-white d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save mr-1"></i> Save Integration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card shadow-sm border-0 setup-card">
            <div class="card-header bg-white border-0">
                <h3 class="card-title font-weight-bold">
                    <i class="fas fa-route text-primary mr-1"></i> Two-Way Setup
                </h3>
            </div>
            <div class="card-body">
                <div class="setup-step">
                    <span>1</span>
                    <div>
                        <strong>Add AshBazar in DeshBajar</strong>
                        <small>Generate DeshBajar receiver endpoint and token.</small>
                    </div>
                </div>

                <div class="setup-step">
                    <span>2</span>
                    <div>
                        <strong>Add DeshBajar in AshBazar</strong>
                        <small>Generate AshBazar receiver endpoint and token.</small>
                    </div>
                </div>

                <div class="setup-step">
                    <span>3</span>
                    <div>
                        <strong>Cross-paste credentials</strong>
                        <small>Each website's receiver credentials go into the other website's Send section.</small>
                    </div>
                </div>

                <div class="setup-step">
                    <span>4</span>
                    <div>
                        <strong>Test both connections</strong>
                        <small>Run Test Send Connection on both websites.</small>
                    </div>
                </div>

                <div class="setup-step mb-0">
                    <span>5</span>
                    <div>
                        <strong>Place test orders</strong>
                        <small>New local orders will automatically appear on the opposite website.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-warning shadow-sm">
            <strong>Credential rule</strong>
            <div class="small mt-1">
                DeshBajar receiver token is used by AshBazar when sending to DeshBajar.
                AshBazar receiver token is used by DeshBajar when sending to AshBazar.
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 integration-card">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0 font-weight-bold">
            <i class="fas fa-network-wired text-primary mr-1"></i>
            Connected Websites
        </h3>
        <span class="badge badge-light border">{{ $websites->total() }} integrations</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Website</th>
                    <th>Directions</th>
                    <th>Local Receiver Credentials</th>
                    <th>Remote Send Settings</th>
                    <th>Connection</th>
                    <th>Orders</th>
                    <th width="205" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($websites as $website)
                    <tr>
                        <td>
                            <strong>{{ $website->name }}</strong>
                            <small class="d-block text-muted">{{ $website->domain_host }}</small>
                            <span class="badge {{ $website->status ? 'badge-success' : 'badge-secondary' }} mt-1">
                                {{ $website->status ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <td>
                            <span class="badge {{ $website->receive_orders ? 'badge-success' : 'badge-light border' }} mb-1">
                                <i class="fas fa-download mr-1"></i> Receive
                            </span>
                            <span class="badge {{ $website->send_orders ? 'badge-primary' : 'badge-light border' }} mb-1">
                                <i class="fas fa-upload mr-1"></i> Send
                            </span>
                            @if($website->send_orders)
                                <small class="d-block text-muted">
                                    {{ $website->auto_send_orders ? 'Automatic sending' : 'Manual sending only' }}
                                </small>
                            @endif
                        </td>

                        <td style="min-width: 285px;">
                            <label class="small mb-1">Endpoint</label>
                            <div class="input-group input-group-sm mb-2">
                                <input type="text"
                                       class="form-control integration-copy-value"
                                       value="{{ $website->api_endpoint }}"
                                       readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary btn-copy-value">
                                        <i class="far fa-copy"></i>
                                    </button>
                                </div>
                            </div>

                            <label class="small mb-1">Receiver Token</label>
                            <div class="input-group input-group-sm">
                                <input type="password"
                                       class="form-control token-display-input integration-copy-value"
                                       value="{{ $website->api_token }}"
                                       readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary btn-toggle-token">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-copy-value">
                                        <i class="far fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </td>

                        <td style="min-width: 285px;">
                            @if($website->send_orders)
                                <label class="small mb-1">Remote Endpoint</label>
                                <div class="input-group input-group-sm mb-2">
                                    <input type="text"
                                           class="form-control integration-copy-value"
                                           value="{{ $website->remote_order_endpoint }}"
                                           readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary btn-copy-value">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                </div>

                                <label class="small mb-1">Remote Token</label>
                                <div class="input-group input-group-sm">
                                    <input type="password"
                                           class="form-control token-display-input integration-copy-value"
                                           value="{{ $website->remote_api_token }}"
                                           readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary btn-toggle-token">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-copy-value">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted">Outgoing sync disabled</span>
                            @endif
                        </td>

                        <td>
                            <div class="mb-2">
                                <small class="d-block text-muted">Receive connection</small>
                                @if($website->inbound_connection_status === 'connected')
                                    <span class="badge badge-success">Connected</span>
                                @elseif($website->inbound_connection_status === 'authentication_failed')
                                    <span class="badge badge-danger">Token Failed</span>
                                @elseif($website->inbound_connection_status === 'inactive')
                                    <span class="badge badge-secondary">Disabled</span>
                                @else
                                    <span class="badge badge-warning">Awaiting Request</span>
                                @endif
                            </div>

                            <div>
                                <small class="d-block text-muted">Send connection</small>
                                @if($website->outbound_connection_status === 'connected')
                                    <span class="badge badge-success">Connected</span>
                                @elseif($website->outbound_connection_status === 'failed')
                                    <span class="badge badge-danger" title="{{ $website->last_connection_message }}">Failed</span>
                                @elseif($website->outbound_connection_status === 'inactive')
                                    <span class="badge badge-secondary">Disabled</span>
                                @else
                                    <span class="badge badge-warning">Not Tested</span>
                                @endif
                            </div>
                        </td>

                        <td>
                            <small class="d-block"><strong>{{ $website->orders_count }}</strong> received</small>
                            <small class="d-block text-success"><strong>{{ $website->sent_orders_count }}</strong> sent</small>
                            <small class="d-block text-danger"><strong>{{ $website->failed_orders_count }}</strong> failed</small>
                            @if($website->last_order_received_at)
                                <small class="d-block text-muted mt-1">
                                    Last received: {{ $website->last_order_received_at->format('d M, h:i A') }}
                                </small>
                            @endif
                            @if($website->last_order_sent_at)
                                <small class="d-block text-muted">
                                    Last sent: {{ $website->last_order_sent_at->format('d M, h:i A') }}
                                </small>
                            @endif
                        </td>

                        <td class="text-center">
                            <div class="btn-group btn-group-sm mb-2">
                                <button type="button"
                                        class="btn btn-outline-primary"
                                        data-toggle="collapse"
                                        data-target="#editWebsite{{ $website->id }}"
                                        title="Edit settings">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <form action="{{ route('admin.external-websites.test-connection', $website) }}"
                                      method="POST"
                                      class="d-inline">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-outline-success"
                                            title="Test outgoing connection"
                                            @disabled(! $website->send_orders)>
                                        <i class="fas fa-plug"></i>
                                    </button>
                                </form>

                                <form action="{{ route('admin.external-websites.regenerate-token', $website) }}"
                                      method="POST"
                                      class="d-inline form-regenerate-token">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-outline-warning"
                                            title="Regenerate local receiver token">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </form>

                                <form action="{{ route('admin.external-websites.destroy', $website) }}"
                                      method="POST"
                                      class="d-inline form-delete-integration">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>

                            <div class="d-flex justify-content-center flex-wrap">
                                <form action="{{ route('admin.external-websites.sync-existing-orders', $website) }}"
                                      method="POST"
                                      class="mr-1 mb-1 form-sync-existing">
                                    @csrf
                                    <input type="hidden" name="limit" value="100">
                                    <button type="submit"
                                            class="btn btn-xs btn-outline-info"
                                            @disabled(! $website->send_orders)>
                                        Sync 100 Existing
                                    </button>
                                </form>

                                @if($website->failed_orders_count > 0)
                                    <form action="{{ route('admin.external-websites.retry-failed-orders', $website) }}"
                                          method="POST"
                                          class="mb-1">
                                        @csrf
                                        <input type="hidden" name="limit" value="100">
                                        <button type="submit" class="btn btn-xs btn-outline-danger">
                                            Retry Failed
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <tr class="collapse bg-light" id="editWebsite{{ $website->id }}">
                        <td colspan="7">
                            <form action="{{ route('admin.external-websites.update', $website) }}"
                                  method="POST"
                                  class="token-settings-form website-integration-form p-3">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Website Name</label>
                                            <input type="text" name="name" value="{{ $website->name }}" class="form-control" required>
                                        </div>
                                    </div>

                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Website Domain</label>
                                            <input type="text" name="domain" value="{{ $website->domain }}" class="form-control" required>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Integration Status</label>
                                            <input type="hidden" name="status" value="0">
                                            <div class="custom-control custom-switch mt-2">
                                                <input type="checkbox"
                                                       name="status"
                                                       value="1"
                                                       class="custom-control-input"
                                                       id="website_status_{{ $website->id }}"
                                                       @checked($website->status)>
                                                <label class="custom-control-label" for="website_status_{{ $website->id }}">Active</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-2">
                                        <label>Receive Orders</label>
                                        <input type="hidden" name="receive_orders" value="0">
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox"
                                                   name="receive_orders"
                                                   value="1"
                                                   class="custom-control-input"
                                                   id="receive_orders_{{ $website->id }}"
                                                   @checked($website->receive_orders)>
                                            <label class="custom-control-label" for="receive_orders_{{ $website->id }}">Enabled</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Receiver Token Action</label>
                                            <select name="token_action" class="form-control token-action-select" required>
                                                <option value="keep">Keep current receiver token</option>
                                                <option value="generate">Generate a new receiver token</option>
                                                <option value="manual">Paste replacement receiver token</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Replacement Receiver Token</label>
                                            <div class="input-group">
                                                <input type="text"
                                                       name="api_token"
                                                       class="form-control api-token-input"
                                                       placeholder="Select manual to paste a replacement token"
                                                       autocomplete="off">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary btn-generate-token">
                                                        Generate
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted token-help-text"></small>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <div class="row">
                                    <div class="col-md-2">
                                        <label>Send Orders</label>
                                        <input type="hidden" name="send_orders" value="0">
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox"
                                                   name="send_orders"
                                                   value="1"
                                                   class="custom-control-input send-orders-toggle"
                                                   id="send_orders_{{ $website->id }}"
                                                   @checked($website->send_orders)>
                                            <label class="custom-control-label" for="send_orders_{{ $website->id }}">Enabled</label>
                                        </div>
                                    </div>

                                    <div class="col-md-2">
                                        <label>Auto Send</label>
                                        <input type="hidden" name="auto_send_orders" value="0">
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox"
                                                   name="auto_send_orders"
                                                   value="1"
                                                   class="custom-control-input"
                                                   id="auto_send_orders_{{ $website->id }}"
                                                   @checked($website->auto_send_orders)>
                                            <label class="custom-control-label" for="auto_send_orders_{{ $website->id }}">Enabled</label>
                                        </div>
                                    </div>

                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Remote Receiver Endpoint</label>
                                            <input type="text"
                                                   name="remote_order_endpoint"
                                                   value="{{ $website->remote_order_endpoint }}"
                                                   class="form-control outbound-required"
                                                   placeholder="https://remote-site.com/api/external-orders/local-site">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Request Timeout</label>
                                            <div class="input-group">
                                                <input type="number"
                                                       name="request_timeout"
                                                       value="{{ $website->request_timeout ?: 15 }}"
                                                       min="3"
                                                       max="120"
                                                       class="form-control"
                                                       required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text">sec</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Remote Health Endpoint</label>
                                            <input type="text"
                                                   name="remote_health_endpoint"
                                                   value="{{ $website->remote_health_endpoint }}"
                                                   class="form-control"
                                                   placeholder="Optional: endpoint + /status is used automatically">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Replacement Remote Token</label>
                                            <div class="input-group">
                                                <input type="password"
                                                       name="remote_api_token"
                                                       class="form-control remote-token-input"
                                                       placeholder="Leave blank to keep current remote token"
                                                       autocomplete="new-password">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary btn-toggle-field-token">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Notes</label>
                                            <input type="text" name="notes" value="{{ $website->notes }}" class="form-control">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save mr-1"></i> Update Integration
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-network-wired fa-2x mb-2"></i>
                            <div>No website integration found.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($websites->hasPages())
        <div class="card-footer bg-white">
            {{ $websites->links() }}
        </div>
    @endif
</div>

<div class="card shadow-sm border-0 mt-4 integration-card">
    <div class="card-header bg-white border-0 py-3">
        <h3 class="card-title mb-0 font-weight-bold">
            <i class="fas fa-project-diagram text-info mr-1"></i>
            Bidirectional Technical Flow
        </h3>
    </div>

    <div class="card-body">
        <div class="row text-center align-items-center">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="flow-box">
                    <i class="fas fa-store fa-2x text-primary"></i>
                    <strong class="d-block mt-2">DeshBajar Local Order</strong>
                    <small>Auto Send → AshBazar Receiver Endpoint</small>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="flow-arrows">
                    <div><i class="fas fa-long-arrow-alt-right mr-1"></i> Secure API + Token</div>
                    <div><i class="fas fa-long-arrow-alt-left mr-1"></i> Secure API + Token</div>
                    <small class="text-muted">Each direction uses the receiver's own token.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="flow-box">
                    <i class="fas fa-shopping-cart fa-2x text-success"></i>
                    <strong class="d-block mt-2">AshBazar Local Order</strong>
                    <small>Auto Send → DeshBajar Receiver Endpoint</small>
                </div>
            </div>
        </div>

        <div class="alert alert-light border mt-4 mb-0">
            <strong>Loop protection:</strong>
            An order received through the external API is saved with <code>created_via = external_api</code> and is not sent again.
            A UUID and per-website sync log also prevent duplicate imports and repeated sends.
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
.integration-card,
.setup-card,
.credential-result-card {
    border-radius: 12px;
    overflow: hidden;
}

.integration-card .table td,
.integration-card .table th {
    vertical-align: middle;
}

.section-title {
    border-bottom: 1px solid #e5e7eb;
    color: #2563eb;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 18px;
    padding-bottom: 8px;
    text-transform: uppercase;
}

.direction-panel {
    border: 1px solid #dbeafe;
    border-radius: 10px;
    padding: 18px;
}

.direction-receive {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.direction-send {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.setup-step {
    display: flex;
    gap: 12px;
    margin-bottom: 18px;
}

.setup-step > span {
    align-items: center;
    background: #2563eb;
    border-radius: 50%;
    color: #fff;
    display: inline-flex;
    flex: 0 0 28px;
    font-size: 12px;
    font-weight: 700;
    height: 28px;
    justify-content: center;
}

.setup-step small {
    color: #6b7280;
    display: block;
    margin-top: 2px;
}

.flow-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px 15px;
}

.flow-arrows {
    color: #1d4ed8;
    font-size: 16px;
    font-weight: 600;
    line-height: 2;
}

.btn-xs {
    font-size: 11px;
    padding: 2px 7px;
}
</style>
@endsection

@section('js')
<script>
$(document).ready(function() {
    function generateToken() {
        const bytes = new Uint8Array(32);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    function refreshTokenForm(form) {
        const action = form.find('.token-action-select').val();
        const tokenInput = form.find('.api-token-input');
        const generateButton = form.find('.btn-generate-token');
        const helpText = form.find('.token-help-text');

        if (action === 'keep') {
            tokenInput.val('').prop('disabled', true);
            generateButton.prop('disabled', true);
            helpText.text('The receiver token already saved in the database will remain unchanged.');
            return;
        }

        if (action === 'generate') {
            tokenInput.val('').prop('disabled', true);
            generateButton.prop('disabled', false);
            helpText.text('The server will generate and save a secure receiver token after submission.');
            return;
        }

        tokenInput.prop('disabled', false);
        generateButton.prop('disabled', false);
        helpText.text('Paste the exact token that the sending website will use.');
    }

    function refreshOutboundFields(form) {
        const sendEnabled = form.find('.send-orders-toggle').is(':checked');
        form.find('.outbound-required').prop('required', sendEnabled);
    }

    function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }

        return new Promise(function(resolve, reject) {
            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                resolve();
            } catch (error) {
                reject(error);
            } finally {
                document.body.removeChild(textarea);
            }
        });
    }

    $('.token-settings-form').each(function() {
        refreshTokenForm($(this));
        refreshOutboundFields($(this));
    });

    $(document).on('change', '.token-action-select', function() {
        refreshTokenForm($(this).closest('.token-settings-form'));
    });

    $(document).on('change', '.send-orders-toggle', function() {
        refreshOutboundFields($(this).closest('.website-integration-form'));
    });

    $(document).on('click', '.btn-generate-token', function() {
        const form = $(this).closest('.token-settings-form');
        const actionSelect = form.find('.token-action-select');
        const tokenInput = form.find('.api-token-input');

        actionSelect.val('manual');
        tokenInput.prop('disabled', false).val(generateToken()).trigger('focus');
        refreshTokenForm(form);
    });

    $(document).on('click', '.btn-copy-value', function() {
        const input = $(this).closest('.input-group').find('.integration-copy-value').get(0);

        if (! input) {
            return;
        }

        copyText(input.value).then(function() {
            Swal.fire({
                icon: 'success',
                title: 'Copied',
                position: 'top-end',
                showConfirmButton: false,
                timer: 1200,
                toast: true
            });
        });
    });

    $(document).on('click', '.btn-toggle-token, .btn-toggle-field-token', function() {
        const input = $(this).closest('.input-group').find('input');
        const icon = $(this).find('i');
        const show = input.attr('type') === 'password';

        input.attr('type', show ? 'text' : 'password');
        icon.toggleClass('fa-eye', ! show).toggleClass('fa-eye-slash', show);
    });

    $(document).on('submit', '.form-regenerate-token', function(event) {
        event.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Generate a new receiver token?',
            text: 'The old token will stop working. Copy the new token into the other website Send Orders settings.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, generate token'
        }).then(function(result) {
            if (result.isConfirmed || result.value) {
                form.submit();
            }
        });
    });

    $(document).on('submit', '.form-sync-existing', function(event) {
        event.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Sync existing local orders?',
            text: 'Up to 100 unsynced local orders will be sent to this website. Imported orders will be skipped.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Start Sync'
        }).then(function(result) {
            if (result.isConfirmed || result.value) {
                form.submit();
            }
        });
    });

    $(document).on('submit', '.form-delete-integration', function(event) {
        event.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Delete this integration?',
            text: 'Existing received orders will remain unchanged.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#dc3545'
        }).then(function(result) {
            if (result.isConfirmed || result.value) {
                form.submit();
            }
        });
    });
});
</script>
@endsection
