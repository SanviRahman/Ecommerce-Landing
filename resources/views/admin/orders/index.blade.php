@extends('adminlte::page')

@section('title', $title ?? 'Order Management')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Order Management' }}</h1>

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

@if(auth()->user()->isEmployee())
    <div class="alert alert-info">
        <i class="fas fa-info-circle mr-1"></i>
        You are viewing your assigned orders only.
    </div>
@endif

@php
    $currentStatusView = $currentStatusView ?? 'new';
    $currentFieldId = $currentOrderField->id ?? null;
    $isInvoiceView = in_array($currentStatusView, ['pending-invoice', 'complete-invoice'], true);
    $isCourierStatusView = in_array($currentStatusView, ['courier-pending', 'courier-cancelled', 'courier-delivered'], true);
    $canBulkManageOrders = auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isEmployee());
    $canCreateManualOrder = $canBulkManageOrders;
    $canDeleteOrders = auth()->check() && auth()->user()->isAdmin();
    $isApiOrdersView = $currentStatusView === 'api-orders';
    $selectedWebsiteFilters = collect($selectedWebsiteFilters ?? explode(',', (string) request('external_website_ids', request('external_website_id', 'all'))))
        ->map(fn($value) => trim((string) $value))
        ->filter()
        ->unique()
        ->values();

    if ($selectedWebsiteFilters->isEmpty() || $selectedWebsiteFilters->contains('all')) {
        $selectedWebsiteFilters = collect(['all']);
    }

    $selectedWebsiteLabel = 'All Websites';

    if (! $selectedWebsiteFilters->contains('all')) {
        if ($selectedWebsiteFilters->count() === 1 && $selectedWebsiteFilters->first() === 'local') {
            $selectedWebsiteLabel = $localWebsiteName ?? request()->getHost();
        } elseif ($selectedWebsiteFilters->count() === 1) {
            $selectedExternalWebsite = ($externalWebsites ?? collect())
                ->firstWhere('id', (int) $selectedWebsiteFilters->first());
            $selectedWebsiteLabel = $selectedExternalWebsite->domain_host ?? 'Selected Website';
        } else {
            $selectedWebsiteLabel = $selectedWebsiteFilters->count() . ' Websites';
        }
    }
@endphp

{{-- Top Small Stats --}}
@if($isInvoiceView)
    <div class="row mb-3" id="orderStatsCards">
        <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.invoices.pending') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'pending-invoice' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_invoice_pending">{{ $stats['invoice_pending'] ?? 0 }}</h4>
                    <p>Pending Invoice</p>
                </div>
                <i class="fas fa-file-invoice"></i>
            </a>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.invoices.complete') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'complete-invoice' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_invoice_complete">{{ $stats['invoice_complete'] ?? 0 }}</h4>
                    <p>Complete Invoice</p>
                </div>
                <i class="fas fa-print"></i>
            </a>
        </div>
    </div>
@elseif($isCourierStatusView)
    <div class="row mb-3" id="orderStatsCards">
        <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.courier_pending') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'courier-pending' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_courier_pending">{{ $stats['courier_pending'] ?? 0 }}</h4>
                    <p>Courier Pending</p>
                </div>
                <i class="fas fa-hourglass-half"></i>
            </a>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.courier_cancelled') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'courier-cancelled' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_courier_cancelled">{{ $stats['courier_cancelled'] ?? 0 }}</h4>
                    <p>Courier Cancel</p>
                </div>
                <i class="fas fa-ban"></i>
            </a>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.courier_delivered') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'courier-delivered' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_courier_delivered">{{ $stats['courier_delivered'] ?? 0 }}</h4>
                    <p>Delivered</p>
                </div>
                <i class="fas fa-box-open"></i>
            </a>
        </div>
    </div>
@elseif($currentStatusView === 'api-orders')
    @php
        $currentApiCard = (string) request('api_card', 'all');
        $apiCards = [
            ['key' => 'all', 'label' => 'Total API Orders', 'icon' => 'fas fa-cloud-download-alt'],
            ['key' => 'new', 'label' => 'New Orders', 'icon' => 'fas fa-shopping-cart'],
            ['key' => 'pending', 'label' => 'Pending Orders', 'icon' => 'fas fa-shopping-cart'],
            ['key' => 'completed', 'label' => 'Complete Orders', 'icon' => 'fas fa-shopping-cart'],
            ['key' => 'shipped', 'label' => 'Shipped', 'icon' => 'fas fa-truck-loading'],
            ['key' => 'order_list_1', 'label' => 'Order List 1', 'icon' => 'fas fa-list-ol'],
            ['key' => 'order_list_2', 'label' => 'Order List 2', 'icon' => 'fas fa-list-ol'],
            ['key' => 'cancelled', 'label' => 'Cancelled Orders', 'icon' => 'fas fa-shopping-cart'],
            ['key' => 'delivered', 'label' => 'Delivered', 'icon' => 'fas fa-truck'],
            ['key' => 'stock_out', 'label' => 'Stock Out', 'icon' => 'fas fa-box-open'],
        ];
    @endphp

    <div class="row mb-3" id="orderStatsCards">
        @foreach($apiCards as $apiCard)
            @php
                $cardKey = $apiCard['key'];
                $apiCardQuery = [];

                if ($cardKey !== 'all') {
                    $apiCardQuery['api_card'] = $cardKey;
                }

                if (! $selectedWebsiteFilters->contains('all')) {
                    $apiCardQuery['external_website_ids'] = $selectedWebsiteFilters->implode(',');
                }

                $cardUrl = route('admin.orders.api_orders', $apiCardQuery);
            @endphp
            <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
                <a href="{{ $cardUrl }}"
                   class="order-stat-card text-decoration-none {{ $currentApiCard === $cardKey ? 'active' : '' }}">
                    <div>
                        <h4 id="stat_{{ $cardKey }}">{{ $stats[$cardKey] ?? 0 }}</h4>
                        <p>{{ $apiCard['label'] }}</p>
                    </div>
                    <i class="{{ $apiCard['icon'] }}"></i>
                </a>
            </div>
        @endforeach
    </div>
@else
    <div class="row mb-3" id="orderStatsCards">
        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.all') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'all' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_all">{{ $stats['all'] ?? 0 }}</h4>
                    <p>All Orders</p>
                </div>
                <i class="fas fa-shopping-cart"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.index') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'new' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_new">{{ $stats['new'] ?? 0 }}</h4>
                    <p>New Orders</p>
                </div>
                <i class="fas fa-shopping-cart"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.pending') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'pending' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_pending">{{ $stats['pending'] ?? 0 }}</h4>
                    <p>Pending Orders</p>
                </div>
                <i class="fas fa-shopping-cart"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.confirmed') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'completed' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_completed">{{ $stats['completed'] ?? 0 }}</h4>
                    <p>Complete Orders</p>
                </div>
                <i class="fas fa-shopping-cart"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.shipped') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'shipped' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_shipped">{{ $stats['shipped'] ?? 0 }}</h4>
                    <p>Shipped</p>
                </div>
                <i class="fas fa-truck-loading"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.order_list_1') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'order-list-1' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_order_list_1">{{ $stats['order_list_1'] ?? 0 }}</h4>
                    <p>Order List 1</p>
                </div>
                <i class="fas fa-list-ol"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.order_list_2') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'order-list-2' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_order_list_2">{{ $stats['order_list_2'] ?? 0 }}</h4>
                    <p>Order List 2</p>
                </div>
                <i class="fas fa-list-ol"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.cancelled') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'cancelled' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_cancelled">{{ $stats['cancelled'] ?? 0 }}</h4>
                    <p>Cancelled Orders</p>
                </div>
                <i class="fas fa-shopping-cart"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.delivered') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'delivered' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_delivered">{{ $stats['delivered'] ?? 0 }}</h4>
                    <p>Delivered</p>
                </div>
                <i class="fas fa-truck"></i>
            </a>
        </div>

        <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2">
            <a href="{{ route('admin.orders.stock_out') }}"
               class="order-stat-card text-decoration-none {{ $currentStatusView === 'stock-out' ? 'active' : '' }}">
                <div>
                    <h4 id="stat_stock_out">{{ $stats['stock_out'] ?? 0 }}</h4>
                    <p>Stock Out</p>
                </div>
                <i class="fas fa-shopping-cart"></i>
            </a>
        </div>

        @foreach($orderFields ?? [] as $field)
            <div class="col-xl col-lg-3 col-md-4 col-sm-6 mb-2 order-dynamic-field-card" data-field-id="{{ $field->id }}">
                <a href="{{ route('admin.orders.field', $field->slug) }}"
                   class="order-stat-card dynamic-field-card text-decoration-none {{ $currentFieldId === $field->id ? 'active' : '' }}"
                   style="--field-color: {{ $field->color ?: '#2563eb' }};">
                    <div>
                        <h4 class="stat_field_{{ $field->id }}">{{ $field->orders_count ?? 0 }}</h4>
                        <p>{{ $field->name }}</p>
                    </div>
                    <i class="fas fa-shopping-cart"></i>
                </a>
            </div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm border-0" style="border-radius: 12px;">
    <div class="card-header bg-white py-3 border-0">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0 font-weight-bold text-dark">
                    <i class="fas fa-shopping-cart mr-2 text-primary"></i>
                    {{ $title ?? 'Order Management' }}

                    <span id="view-label" class="badge badge-primary-soft ml-2 border">
                        {{ isset($isTrash) && $isTrash ? 'Trash Bin' : 'Active List' }}
                    </span>
                </h5>
            </div>

            <div class="col-md-6 mt-2 mt-md-0 text-md-right">
                @if($canCreateManualOrder && empty($isTrash))
                    <a href="{{ route('admin.orders.bulk_create', ['return_url' => url()->full()]) }}"
                       class="btn btn-primary btn-sm px-3 mr-2 shadow-none">
                        <i class="fas fa-layer-group mr-1"></i> Bulk Order
                    </a>

                    <a href="{{ route('admin.orders.create', ['return_url' => url()->full()]) }}"
                       class="btn btn-success btn-sm px-3 mr-2 shadow-none">
                        <i class="fas fa-plus-circle mr-1"></i> Create Manual Order
                    </a>
                @endif

                <button class="btn btn-outline-primary btn-sm px-3 shadow-none"
                        type="button"
                        id="btnToggleOrderFilter"
                        data-toggle="collapse"
                        data-target="#orderFilterForm"
                        aria-expanded="false"
                        aria-controls="orderFilterForm">
                    <i class="fas fa-filter mr-1"></i> Filter / Search
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        {{-- Filter Section --}}
        <form id="orderFilterForm"
              class="collapse px-4 py-3 border-top bg-white"
              method="GET"
              action="{{ url()->current() }}">
            <input type="hidden" id="filter_per_page" name="per_page" value="{{ request('per_page', 20) }}">
            <input type="hidden" id="filter_external_website_ids" name="external_website_ids" value="{{ $selectedWebsiteFilters->implode(',') }}">
            @if($isApiOrdersView && request()->filled('api_card'))
                <input type="hidden" name="api_card" value="{{ request('api_card') }}">
            @endif
            <div class="row">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Order Status</label>
                    <select id="filter_order_status"
                            name="order_status"
                            class="form-control border-0 bg-light shadow-none">
                        <option value="all" @selected(request('order_status', 'all') === 'all')>All Status</option>
                        @foreach($orderStatuses ?? [] as $status)
                            @php
                                $statusLabel = match ($status) {
                                    \App\Models\Order::STATUS_COURIER_PENDING => 'Courier Pending',
                                    \App\Models\Order::STATUS_COURIER_CANCELLED => 'Courier Cancel',
                                    default => ucwords(str_replace('_', ' ', $status)),
                                };
                            @endphp
                            <option value="{{ $status }}" @selected(request('order_status') === $status)>
                                {{ $statusLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Payment</label>
                    <select id="filter_payment_status"
                            name="payment_status"
                            class="form-control border-0 bg-light shadow-none">
                        <option value="all" @selected(request('payment_status', 'all') === 'all')>All Payment</option>
                        @foreach($paymentStatuses ?? [] as $status)
                            <option value="{{ $status }}" @selected(request('payment_status') === $status)>
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Courier</label>
                    <select id="filter_courier_id"
                            name="courier_id"
                            class="form-control border-0 bg-light shadow-none">
                        <option value="all" @selected(request('courier_id', 'all') === 'all')>All Courier</option>
                        <option value="none" @selected(request('courier_id') === 'none')>No Courier</option>
                        @foreach($couriers ?? [] as $courier)
                            <option value="{{ $courier->id }}" @selected((string) request('courier_id') === (string) $courier->id)>
                                {{ $courier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Fake Status</label>
                    <select id="filter_fake_status"
                            name="fake_status"
                            class="form-control border-0 bg-light shadow-none">
                        <option value="all" @selected(request('fake_status', 'all') === 'all')>All Orders</option>
                        <option value="real" @selected(request('fake_status') === 'real')>Real Orders</option>
                        <option value="fake" @selected(request('fake_status') === 'fake')>Fake Orders</option>
                    </select>
                </div>

                @if(auth()->user()->isAdmin())
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Employee</label>
                        <select id="filter_employee"
                                name="assigned_employee_id"
                                class="form-control border-0 bg-light shadow-none">
                            <option value="all" @selected(request('assigned_employee_id', 'all') === 'all')>All Employees</option>
                            <option value="unassigned" @selected(request('assigned_employee_id') === 'unassigned')>Unassigned</option>
                            @foreach($employees ?? [] as $employee)
                                <option value="{{ $employee->id }}" @selected((string) request('assigned_employee_id') === (string) $employee->id)>
                                    {{ $employee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" id="filter_employee" name="assigned_employee_id" value="all">
                @endif

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Date From</label>
                    <input type="date"
                           id="filter_date_from"
                           name="date_from"
                           value="{{ request('date_from') }}"
                           class="form-control border-0 bg-light shadow-none">
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Date To</label>
                    <input type="date"
                           id="filter_date_to"
                           name="date_to"
                           value="{{ request('date_to') }}"
                           class="form-control border-0 bg-light shadow-none">
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Order Field</label>
                    <select id="filter_order_field"
                            name="order_field_id"
                            class="form-control border-0 bg-light shadow-none">
                        @php
                            $selectedOrderFieldId = request('order_field_id', $currentOrderField->id ?? 'all');
                        @endphp

                        <option value="all" @selected((string) $selectedOrderFieldId === 'all')>All Fields</option>
                        <option value="none" @selected((string) $selectedOrderFieldId === 'none')>No Field</option>

                        @foreach($orderFields ?? [] as $field)
                            <option value="{{ $field->id }}" @selected((string) $selectedOrderFieldId === (string) $field->id)>
                                {{ $field->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-8 col-sm-8 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Search</label>
                    <input type="text"
                           id="table_search"
                           name="search"
                           value="{{ request('search') }}"
                           class="form-control shadow-none"
                           placeholder="Search invoice, external order, customer, phone, website, product, courier, employee...">
                </div>

                <div class="col-md-2 col-sm-4 mb-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-dark btn-block shadow-none" id="btnFilter">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </div>
            </div>
        </form>

        {{-- Bulk Action Bar --}}
        @if($canBulkManageOrders)
            <div class="px-4 py-2 bg-light border-top border-bottom">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center flex-wrap">
                        <div class="dropdown d-inline-block mr-2 mb-1">
                            <button class="btn btn-dark btn-sm dropdown-toggle" type="button" id="bulkSelectLimitDropdown" data-toggle="dropdown">
                                Total <span id="selectedCount">0</span> Orders
                            </button>
                            <div class="dropdown-menu" aria-labelledby="bulkSelectLimitDropdown">
                                <h6 class="dropdown-header">Select Orders</h6>
                                @foreach([50,100,150,200,250,300,350,400,450,500] as $selectLimit)
                                    <a href="#" class="dropdown-item bulk-select-limit-action" data-limit="{{ $selectLimit }}">
                                        <i class="far fa-square text-muted mr-1 bulk-select-limit-icon"></i> Select {{ $selectLimit }} Orders
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <button type="button" class="btn btn-secondary btn-sm mr-2 mb-1" id="btnPrintSelectedInvoice">
                            <i class="fas fa-print mr-1"></i> Print Selected Invoice
                        </button>

                        <div class="dropdown d-inline-block mr-2 mb-1">
                            <button class="btn btn-outline-danger btn-sm dropdown-toggle" type="button" id="exportOrdersDropdown"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-file-excel mr-1"></i> Export Courier
                            </button>
                            <div class="dropdown-menu" aria-labelledby="exportOrdersDropdown">
                                <a class="dropdown-item export-orders-action" href="#" data-type="steadfast">
                                    <i class="fas fa-file-export mr-1 text-primary"></i> SteadFast Export
                                </a>
                                <a class="dropdown-item export-orders-action" href="#" data-type="pathao">
                                    <i class="fas fa-file-export mr-1 text-success"></i> Pathao Export
                                </a>
                                <a class="dropdown-item export-orders-action" href="#" data-type="redex">
                                    <i class="fas fa-file-export mr-1 text-danger"></i> RedX Export
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item export-orders-action" href="#" data-type="default">
                                    <i class="fas fa-file-alt mr-1 text-muted"></i> General Export
                                </a>
                            </div>
                        </div>

                        <div class="dropdown d-inline-block mr-2 mb-1">
                            <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="sendCourierDropdown"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-shipping-fast mr-1"></i> Send Courier
                            </button>
                            <div class="dropdown-menu" aria-labelledby="sendCourierDropdown">
                                @forelse($couriers ?? [] as $courier)
                                    @php
                                        $courierCode = strtolower((string) ($courier->code ?? ''));
                                        $isSteadFast = $courierCode === 'steadfast';
                                        $isPathao = $courierCode === 'pathao';
                                        $sendUrl = $isSteadFast
                                            ? route('admin.orders.send_steadfast_bulk')
                                            : ($isPathao ? route('admin.orders.send_pathao_bulk') : route('admin.orders.assign_courier_bulk'));
                                        $iconClass = $isSteadFast ? 'fas fa-paper-plane text-primary' : ($isPathao ? 'fas fa-shipping-fast text-success' : 'fas fa-truck text-muted');
                                    @endphp

                                    <a class="dropdown-item bulk-courier-action" href="#"
                                       data-url="{{ $sendUrl }}"
                                       data-name="{{ $courier->name }}"
                                       @if(! $isSteadFast && ! $isPathao)
                                           data-courier-id="{{ $courier->id }}"
                                       @endif>
                                        <i class="{{ $iconClass }} mr-1"></i>
                                        {{ $isSteadFast || $isPathao ? 'Send' : 'Assign' }} {{ $courier->name }}
                                    </a>
                                @empty
                                    <span class="dropdown-item text-muted">No courier found</span>
                                @endforelse

                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item bulk-courier-action text-danger" href="#"
                                   data-url="{{ route('admin.orders.assign_courier_bulk') }}"
                                   data-name="No Courier"
                                   data-courier-id="none">
                                    <i class="fas fa-times-circle mr-1"></i> Remove Courier
                                </a>
                            </div>
                        </div>

                        @if($canDeleteOrders)
                            {{-- Trash page-e Permanent Delete Selected button show hobe na.
                                 Trash permanently clear korar jonno only Empty Trash button thakbe. --}}
                            <button type="button"
                                    class="btn btn-danger btn-sm mr-2 mb-1"
                                    id="btnDeleteSelected"
                                    style="{{ !empty($isTrash) ? 'display:none;' : '' }}">
                                <i class="fas fa-trash mr-1"></i> Delete Selected
                            </button>
                        @endif

                        <div class="dropdown d-inline-block mr-2 mb-1">
                            <button class="btn btn-info btn-sm dropdown-toggle" type="button" id="changeStatusDropdown"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-exchange-alt mr-1"></i> Change Status
                            </button>

                            <div class="dropdown-menu" aria-labelledby="changeStatusDropdown">
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_pending">
                                    <i class="far fa-circle mr-1"></i> Pending
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_confirmed">
                                    <i class="far fa-check-circle mr-1"></i> Confirmed
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_complete_invoice">
                                    <i class="fas fa-file-invoice-dollar mr-1"></i> Complete Invoice
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_processing">
                                    <i class="fas fa-spinner mr-1"></i> Processing / New
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_shipped">
                                    <i class="fas fa-truck mr-1"></i> Shipped
                                </a>
                                <a class="dropdown-item bulk-status-action text-warning" href="#" data-action="status_courier_pending">
                                    <i class="fas fa-hourglass-half mr-1"></i> Courier Pending
                                </a>
                                <a class="dropdown-item bulk-status-action text-danger" href="#" data-action="status_courier_cancelled">
                                    <i class="fas fa-ban mr-1"></i> Courier Cancel
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_delivered">
                                    <i class="fas fa-check-double mr-1"></i> Delivered
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_stock_out">
                                    <i class="fas fa-box-open mr-1 text-warning"></i> Stock Out
                                </a>
                                <a class="dropdown-item bulk-status-action text-danger" href="#" data-action="status_cancelled">
                                    <i class="fas fa-times-circle mr-1"></i> Cancelled
                                </a>
                                <a class="dropdown-item bulk-status-action text-danger" href="#" data-action="status_fake">
                                    <i class="fas fa-ban mr-1"></i> Fake
                                </a>

                                @if(($orderFields ?? collect())->count())
                                    <div class="dropdown-divider"></div>
                                    <h6 class="dropdown-header">Move to Order Field</h6>
                                    @foreach($orderFields as $field)
                                        <a class="dropdown-item bulk-status-action" href="#" data-action="field_{{ $field->id }}">
                                            <i class="fas fa-tag mr-1" style="color: {{ $field->color ?: '#2563eb' }}"></i>
                                            {{ $field->name }}
                                        </a>
                                    @endforeach
                                    <a class="dropdown-item bulk-status-action" href="#" data-action="field_none">
                                        <i class="fas fa-unlink mr-1"></i> Remove Order Field
                                    </a>
                                @endif

                                <div class="dropdown-divider"></div>
                                <h6 class="dropdown-header">Move to Static Order List</h6>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="order_list_1">
                                    <i class="fas fa-list-ol mr-1 text-primary"></i> Order List 1
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="order_list_2">
                                    <i class="fas fa-list-ol mr-1 text-success"></i> Order List 2
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="order_list_none">
                                    <i class="fas fa-unlink mr-1"></i> Remove From Order List
                                </a>
                            </div>
                        </div>

                        @if($canDeleteOrders)
                            <div class="dropdown d-inline-block mr-2 mb-1">
                                <button class="btn btn-warning btn-sm dropdown-toggle" type="button" id="syncOrderDropdown" data-toggle="dropdown">
                                    <i class="fas fa-sync-alt mr-1"></i>
                                    {{ $isApiOrdersView ? 'Website Orders Sync' : 'Employees Sync' }}
                                </button>
                                <div class="dropdown-menu" aria-labelledby="syncOrderDropdown">
                                    @if($isApiOrdersView)
                                        <h6 class="dropdown-header">Website Orders Sync</h6>

                                        @forelse($externalWebsites ?? collect() as $externalWebsite)
                                            @php
                                                // A receive-only integration is still a valid connected website.
                                                // Outgoing sync actions, however, require the send direction itself
                                                // to be configured and connected. Keep these two concepts separate.
                                                $websiteReceiveConnected = $externalWebsite->receive_orders
                                                    && $externalWebsite->inbound_connection_status === 'connected';
                                                $websiteSendConnected = $externalWebsite->canSendOrders()
                                                    && $externalWebsite->outbound_connection_status === 'connected';
                                                $websiteConnectionReady = $websiteReceiveConnected || $websiteSendConnected;
                                                $websiteSyncReady = $websiteSendConnected;
                                                $syncProgress = $websiteSyncProgress[$externalWebsite->id] ?? [];
                                                $syncMissingCount = (int) ($syncProgress['remaining'] ?? 0);
                                                $syncFailedCount = (int) ($syncProgress['failed_total'] ?? 0);
                                            @endphp

                                            <div class="dropdown-item-text small d-flex justify-content-between align-items-center font-weight-bold">
                                                <span>{{ $externalWebsite->domain_host }}</span>
                                                <span class="badge {{ $websiteConnectionReady ? 'badge-success' : 'badge-secondary' }}">
                                                    {{ $websiteConnectionReady ? 'Connected' : 'Not Connected' }}
                                                </span>
                                            </div>

                                            @if($websiteSyncReady)
                                                <div class="dropdown-item-text small pt-0 pb-1">
                                                    <span class="badge badge-warning mr-1">
                                                        Missing: {{ $syncMissingCount }}
                                                    </span>
                                                    <span class="badge badge-danger">
                                                        Failed: {{ $syncFailedCount }}
                                                    </span>
                                                </div>
                                            @endif

                                            @if($websiteReceiveConnected)
                                                <button type="button"
                                                        class="dropdown-item api-manual-receive-sync"
                                                        data-url="{{ route('admin.external-websites.manual-receive-sync', $externalWebsite) }}"
                                                        data-website-name="{{ $externalWebsite->name }}"
                                                        data-domain="{{ $externalWebsite->domain_host }}">
                                                    <i class="fas fa-cloud-download-alt text-success mr-1"></i>
                                                    Manual Receive Sync
                                                </button>
                                                <span class="dropdown-item-text small text-muted pt-0">
                                                    Recover missing/failed orders from {{ $externalWebsite->domain_host }}.
                                                </span>
                                            @endif

                                            @if($websiteSyncReady)
                                                <form action="{{ route('admin.external-websites.sync-existing-orders', $externalWebsite) }}"
                                                      method="POST"
                                                      class="api-form-sync-existing"
                                                      data-website-name="{{ $externalWebsite->name }}">
                                                    @csrf
                                                    <input type="hidden" name="limit" value="20">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="fas fa-cloud-upload-alt text-info mr-1"></i>
                                                        Sync Missing Orders ({{ $syncMissingCount }})
                                                    </button>
                                                </form>

                                                <form action="{{ route('admin.external-websites.refresh-synced-orders', $externalWebsite) }}"
                                                      method="POST"
                                                      class="api-form-refresh-synced-orders"
                                                      data-website-name="{{ $externalWebsite->name }}">
                                                    @csrf
                                                    <input type="hidden" name="limit" value="20">
                                                    <input type="hidden" name="cursor" value="0">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="fas fa-history text-primary mr-1"></i>
                                                        Refresh Synced Order Data
                                                    </button>
                                                </form>

                                                <form action="{{ route('admin.external-websites.retry-failed-orders', $externalWebsite) }}"
                                                      method="POST"
                                                      class="api-form-retry-failed-orders"
                                                      data-website-name="{{ $externalWebsite->name }}">
                                                    @csrf
                                                    <input type="hidden" name="limit" value="100">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="fas fa-redo-alt text-danger mr-1"></i>
                                                        Retry Failed Orders ({{ $syncFailedCount }})
                                                    </button>
                                                </form>
                                            @else
                                                <span class="dropdown-item-text small text-muted">
                                                    @if($websiteReceiveConnected)
                                                        Receive connection is active. Use Manual Receive Sync above to recover orders; outgoing send actions remain disabled until Send Orders is configured.
                                                    @else
                                                        Enable Send Orders, save the receiver endpoint/token, then connect this website first.
                                                    @endif
                                                </span>
                                            @endif

                                            @unless($loop->last)
                                                <div class="dropdown-divider"></div>
                                            @endunless
                                        @empty
                                            <span class="dropdown-item text-muted">No website integration found</span>
                                        @endforelse

                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="{{ route('admin.external-websites.index') }}">
                                            <i class="fas fa-cog mr-1 text-secondary"></i> Manage Website Connections
                                        </a>
                                    @else
                                        <h6 class="dropdown-header">Employees Sync</h6>
                                        <a class="dropdown-item manual-auto-employee-sync text-success"
                                           href="#"
                                           data-url="{{ route('admin.orders.assign_unassigned') }}">
                                            <i class="fas fa-magic mr-1"></i> Run Auto Employee Sync
                                        </a>
                                        <span class="dropdown-item-text small text-muted">
                                            Assigns local unassigned orders automatically.
                                        </span>
                                        <div class="dropdown-divider"></div>
                                        @forelse($employees ?? collect() as $employee)
                                            <a class="dropdown-item bulk-assign-employee-action" href="#" data-employee-id="{{ $employee->id }}" data-name="{{ $employee->name }}">
                                                <i class="fas fa-user-check mr-1 text-primary"></i> {{ $employee->name }}
                                            </a>
                                        @empty
                                            <span class="dropdown-item text-muted">No employee found</span>
                                        @endforelse
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item bulk-assign-employee-action text-danger" href="#" data-employee-id="" data-name="Unassigned">
                                            <i class="fas fa-user-times mr-1"></i> Remove Employee
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="dropdown d-inline-block mr-2 mb-1">
                            <button class="btn btn-outline-dark btn-sm dropdown-toggle" type="button" id="selectCourierDropdown"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-truck-loading mr-1"></i> Select Courier
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="selectCourierDropdown">
                                @forelse($couriers ?? [] as $courier)
                                    <a class="dropdown-item bulk-assign-courier-action" href="#"
                                       data-courier-id="{{ $courier->id }}"
                                       data-name="{{ $courier->name }}">
                                        <i class="fas fa-truck mr-1 text-muted"></i>
                                        {{ $courier->name }}
                                    </a>
                                @empty
                                    <span class="dropdown-item text-muted">No courier found</span>
                                @endforelse

                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item bulk-assign-courier-action text-danger" href="#"
                                   data-courier-id="none"
                                   data-name="No Courier">
                                    <i class="fas fa-times-circle mr-1"></i> Remove Courier
                                </a>
                            </div>
                        </div>

                        @if($canDeleteOrders)
                            <div class="dropdown d-inline-block mr-2 mb-1">
                                <button class="btn btn-outline-warning btn-sm dropdown-toggle shadow-none"
                                        type="button"
                                        id="syncWebhookDropdown"
                                        data-toggle="dropdown"
                                        aria-haspopup="true"
                                        aria-expanded="false">
                                    <i class="fas fa-sync-alt mr-1"></i> Sync Webhook
                                </button>

                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="syncWebhookDropdown">
                                    @php
                                        $webhookReturnTo = request()->getRequestUri();
                                        $webhookSource = $isApiOrdersView ? 'api-orders' : 'orders';
                                        $steadfastWebhookUrl = route('command.sync-steadfast-statuses', [
                                            'source' => $webhookSource,
                                            'return_to' => $webhookReturnTo,
                                        ]);
                                        $pathaoWebhookUrl = route('command.sync-pathao-statuses', [
                                            'source' => $webhookSource,
                                            'return_to' => $webhookReturnTo,
                                        ]);
                                    @endphp
                                    <a class="dropdown-item sync-webhook-action"
                                       href="{{ $steadfastWebhookUrl }}"
                                       data-name="SteadFast"
                                       data-url="{{ $steadfastWebhookUrl }}">
                                        <i class="fas fa-shipping-fast mr-1 text-primary"></i> Sync SteadFast
                                    </a>
                                    <a class="dropdown-item sync-webhook-action"
                                       href="{{ $pathaoWebhookUrl }}"
                                       data-name="Pathao"
                                       data-url="{{ $pathaoWebhookUrl }}">
                                        <i class="fas fa-route mr-1 text-success"></i> Sync Pathao
                                    </a>
                                </div>
                            </div>
                            <button class="btn btn-danger btn-sm mr-2 mb-1 shadow-none"
                                    id="btnEmptyTrash"
                                    type="button"
                                    style="{{ !empty($isTrash) ? '' : 'display:none;' }}">
                                <i class="fas fa-broom mr-1"></i> Empty Trash
                            </button>
                        @endif
                        <div class="dropdown d-inline-block mr-2 mb-1">
                            <button class="btn btn-outline-primary btn-sm dropdown-toggle"
                                    type="button"
                                    id="websiteOrderFilterDropdown"
                                    data-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false">
                                <i class="fas fa-globe mr-1"></i>
                                <span id="websiteOrderFilterLabel">{{ $selectedWebsiteLabel }}</span>
                            </button>

                            <div class="dropdown-menu dropdown-menu-right website-filter-menu p-2"
                                 aria-labelledby="websiteOrderFilterDropdown"
                                 style="min-width: 250px; max-height: 330px; overflow-y: auto;">
                                <h6 class="dropdown-header px-2">Order Source Website</h6>

                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox"
                                           class="custom-control-input"
                                           id="websiteFilterAll"
                                           value="all"
                                           @checked($selectedWebsiteFilters->contains('all'))>
                                    <label class="custom-control-label font-weight-bold" for="websiteFilterAll">
                                        All Websites
                                    </label>
                                </div>

                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox"
                                           class="custom-control-input website-filter-checkbox"
                                           id="websiteFilterLocal"
                                           value="local"
                                           data-label="{{ $localWebsiteName ?? request()->getHost() }}"
                                           @checked($selectedWebsiteFilters->contains('all') || $selectedWebsiteFilters->contains('local'))>
                                    <label class="custom-control-label" for="websiteFilterLocal">
                                        <i class="fas fa-home mr-1 text-success"></i>
                                        {{ $localWebsiteName ?? request()->getHost() }}
                                    </label>
                                </div>

                                @foreach($externalWebsites ?? collect() as $externalWebsite)
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox"
                                               class="custom-control-input website-filter-checkbox"
                                               id="websiteFilter{{ $externalWebsite->id }}"
                                               value="{{ $externalWebsite->id }}"
                                               data-label="{{ $externalWebsite->domain_host }}"
                                               @checked($selectedWebsiteFilters->contains('all') || $selectedWebsiteFilters->contains((string) $externalWebsite->id))>
                                        <label class="custom-control-label" for="websiteFilter{{ $externalWebsite->id }}">
                                            <i class="fas fa-globe-americas mr-1 text-primary"></i>
                                            {{ $externalWebsite->domain_host }}
                                            @if(! $externalWebsite->status)
                                                <span class="badge badge-secondary ml-1">Inactive</span>
                                            @endif
                                        </label>
                                    </div>
                                @endforeach

                                <div class="dropdown-divider"></div>
                                <button type="button" class="btn btn-primary btn-sm btn-block" id="btnApplyWebsiteFilter">
                                    <i class="fas fa-filter mr-1"></i> Apply Website Filter
                                </button>
                            </div>
                        </div>
                        @if($canDeleteOrders)
                            <button class="btn btn-outline-danger btn-sm mr-2 mb-1 shadow-none" id="btnToggleTrash" type="button">
                                @if(!empty($isTrash))
                                    <i class="fas fa-list mr-1"></i> Active List
                                @else
                                    <i class="fas fa-trash-alt mr-1"></i> Trash Bin
                                @endif
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div id="content-wrapper" style="min-height: 400px; position: relative;">
            @include('admin.orders.partials.table', [
                'orders' => $orders,
                'isTrash' => $isTrash ?? false,
                'couriers' => $couriers ?? collect(),
                'courierServices' => $courierServices ?? [],
                'orderFields' => $orderFields ?? collect(),
                'orderStatuses' => $orderStatuses ?? [],
                'duplicateCustomerCounts' => $duplicateCustomerCounts ?? [],
                'duplicateIpCounts' => $duplicateIpCounts ?? [],
            ])
        </div>
    </div>
</div>

{{-- Add Order Field Modal --}}
@if(auth()->user()->isAdmin())
<div class="modal fade" id="addOrderFieldModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="addOrderFieldForm">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-plus-circle text-primary mr-1"></i>
                    Add Order Field
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold">Field Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Example: Modhu" required>
                </div>
                <div class="form-group mb-0">
                    <label class="font-weight-bold">Card Color</label>
                    <input type="color" name="color" class="form-control" value="#2563eb" style="height: 42px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Save Field
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@section('footer')
<strong>
    © Copyright 2026 All rights reserved |
    This website developed by
    <a href="https://deshbajar.com/" target="_blank">DESHBAJAR</a>
</strong>
@endsection

@section('plugins.Sweetalert2', true)

@section('css')
<style>
/*
|--------------------------------------------------------------------------
| Equal Size Order Stats Cards
|--------------------------------------------------------------------------
| Bootstrap auto columns can look uneven when label text wraps differently.
| This grid keeps every card same width + same height without changing the UI.
*/
#orderStatsCards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-left: 0;
    margin-right: 0;
}

#orderStatsCards > [class*="col-"] {
    width: auto;
    max-width: none;
    flex: none;
    padding-left: 0;
    padding-right: 0;
    margin-bottom: 0 !important;
    display: flex;
}

.order-stat-card {
    width: 100%;
    height: 82px;
    min-height: 82px;
    max-height: 82px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #e9eef5;
    border-radius: 12px;
    padding: 12px 14px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, .05);
    color: #111827;
    transition: .2s ease;
    overflow: hidden;
    box-sizing: border-box;
}

.order-stat-card > div {
    min-width: 0;
    flex: 1 1 auto;
}

.order-stat-card h4 {
    font-size: 22px;
    font-weight: 800;
    margin: 0;
    line-height: 1;
}

.order-stat-card p {
    min-height: 30px;
    margin: 5px 0 0;
    font-size: 13px;
    line-height: 1.15;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
}

.order-stat-card i {
    flex: 0 0 30px;
    width: 30px;
    text-align: right;
    color: #3b82f6;
    font-size: 28px;
    opacity: .85;
}

@media (max-width: 575.98px) {
    #orderStatsCards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.order-stat-card:hover,
.order-stat-card.active {
    border-color: #2563eb;
    box-shadow: 0 6px 20px rgba(37, 99, 235, .15);
    transform: translateY(-1px);
}

.dynamic-field-card.active,
.dynamic-field-card:hover {
    border-color: var(--field-color);
}

.dynamic-field-card i {
    color: var(--field-color);
}

.badge-primary-soft {
    color: #1d4ed8;
    background: #eff6ff;
}

.dropdown-menu {
    border: 0;
    box-shadow: 0 10px 30px rgba(15, 23, 42, .15);
    border-radius: 8px;
}
#orderFilterForm {
    transition: .2s ease;
}
</style>
@endsection

@section('js')
<script>
$(document).ready(function() {
    const csrfToken = '{{ csrf_token() }}';
    const isAdminUser = @json(auth()->user()->isAdmin());
    const canDeleteOrders = @json($canDeleteOrders);
    let currentStatusView = '{{ $currentStatusView ?? 'new' }}';
    let currentView = "{{ isset($isTrash) && $isTrash ? 'trash' : 'active' }}";
    let currentBaseUrl = "{{ isset($isTrash) && $isTrash ? route('admin.orders.index') : url()->current() }}";
    let adminNoteTimers = {};
    let pendingSelectLimit = null;

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    });

    function swalConfirmed(result) {
        return result.isConfirmed || result.value;
    }

    function showToast(type, message) {
        Swal.fire({
            icon: type,
            type: type,
            title: message,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2200,
            timerProgressBar: true,
            toast: true
        });
    }

    const commandToastParams = new URLSearchParams(window.location.search);
    const commandToastType = commandToastParams.get('toast_type');
    const commandToastMessage = commandToastParams.get('toast_message');

    if (commandToastType && commandToastMessage) {
        showToast(commandToastType === 'error' ? 'error' : 'success', commandToastMessage);
        commandToastParams.delete('toast_type');
        commandToastParams.delete('toast_message');

        const cleanedQuery = commandToastParams.toString();
        const cleanedUrl = window.location.pathname + (cleanedQuery ? '?' + cleanedQuery : '');
        window.history.replaceState({}, document.title, cleanedUrl);
    }

    function htmlEscape(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function numberText(value) {
        let number = Number(value || 0);
        return Number.isNaN(number) ? '0' : number.toLocaleString('en-US');
    }

    function getBaseUrl() {
        return currentView === 'trash' ? "{{ route('admin.orders.trashed') }}" : currentBaseUrl;
    }

    function getQueryParams(page = 1) {
        let params = {
            page: page
        };

        $('#orderFilterForm').serializeArray().forEach(function(input) {
            params[input.name] = input.value;
        });

        return params;
    }

    function cleanQueryParams(params) {
        let query = {};

        Object.keys(params || {}).forEach(function(key) {
            let value = params[key];

            if (value === null || value === undefined || value === '' || value === 'all') {
                return;
            }

            if (key === 'page' && Number(value) === 1) {
                return;
            }

            query[key] = value;
        });

        return query;
    }

    function syncBrowserUrl(page = 1) {
        if (! window.history || ! window.history.replaceState) {
            return;
        }

        let baseUrl = getBaseUrl();
        let params = cleanQueryParams(getQueryParams(page));
        let searchParams = new URLSearchParams(params).toString();

        window.history.replaceState({}, '', baseUrl + (searchParams ? '?' + searchParams : ''));
    }

    function updateStats(stats) {
        $('#stat_all').text(stats.all ?? 0);
        $('#stat_new').text(stats.new ?? 0);
        $('#stat_pending').text(stats.pending ?? 0);
        $('#stat_completed').text(stats.completed ?? 0);
        $('#stat_shipped').text(stats.shipped ?? 0);
        $('#stat_courier_pending').text(stats.courier_pending ?? 0);
        $('#stat_courier_cancelled').text(stats.courier_cancelled ?? 0);
        $('#stat_courier_delivered').text(stats.courier_delivered ?? 0);
        $('#stat_cancelled').text(stats.cancelled ?? 0);
        $('#stat_delivered').text(stats.delivered ?? 0);
        $('#stat_stock_out').text(stats.stock_out ?? 0);
        $('#stat_order_list_1').text(stats.order_list_1 ?? 0);
        $('#stat_order_list_2').text(stats.order_list_2 ?? 0);
        $('#stat_invoice_pending').text(stats.invoice_pending ?? 0);
        $('#stat_invoice_complete').text(stats.invoice_complete ?? 0);

        if (stats.fields) {
            stats.fields.forEach(function(field) {
                $('.stat_field_' + field.id).text(field.count ?? 0);
            });
        }
    }

    function selectedIds() {
        return $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();
    }

    function updateSelectedCount() {
        const selectedCount = selectedIds().length;
        $('#selectedCount').text(selectedCount);

        $('.bulk-select-limit-action').each(function() {
            const $action = $(this);
            const limit = Number($action.data('limit') || 0);
            const $icon = $action.find('.bulk-select-limit-icon');
            const isExactSelection = selectedCount > 0 && selectedCount === limit;

            $icon
                .toggleClass('far fa-square text-muted', !isExactSelection)
                .toggleClass('fas fa-check-square text-primary', isExactSelection);
        });
    }

    function selectFirstVisibleOrders(limit) {
        const checkboxes = $('.row-checkbox');
        const totalVisible = checkboxes.length;
        const maxSelect = Math.min(limit, totalVisible);

        $('#check_all').prop('checked', false);
        checkboxes.prop('checked', false);
        checkboxes.slice(0, maxSelect).prop('checked', true);
        updateSelectedCount();

        if (! maxSelect) {
            Swal.fire('Notice', 'No orders found for selection.', 'info');
            return;
        }

        if (totalVisible < limit) {
            showToast('info', 'Only ' + totalVisible + ' orders are available in this view, so ' + totalVisible + ' orders selected.');
        } else {
            if (currentView === 'trash') {
                showToast('info', maxSelect + ' trash orders selected. Permanent delete selected button removed; use Empty Trash if needed.');
            } else {
                showToast('success', maxSelect + ' orders selected. Now choose Print / Export / Send Courier / Change Status / Select Courier.');
            }
        }
    }

    function updateUIState() {
        if (currentView === 'trash') {
            $('#view-label').text('Trash Bin').attr('class', 'badge badge-danger ml-2 border');
            $('#btnToggleTrash')
                .html('<i class="fas fa-list mr-1"></i> Active List')
                .removeClass('btn-outline-danger')
                .addClass('btn-outline-primary');
            if (canDeleteOrders) {
                $('#btnEmptyTrash').show();
            }
            $('#btnDeleteSelected').hide();
            $('#sendCourierDropdown, #selectCourierDropdown').prop('disabled', true).addClass('disabled');
            $('.bulk-courier-action, .bulk-assign-courier-action, .bulk-assign-employee-action').addClass('disabled');
        } else {
            $('#view-label').text('Active List').attr('class', 'badge badge-primary-soft ml-2 border');
            $('#btnToggleTrash')
                .html('<i class="fas fa-trash-alt mr-1"></i> Trash Bin')
                .removeClass('btn-outline-primary')
                .addClass('btn-outline-danger');
            if (canDeleteOrders) {
                $('#btnEmptyTrash').hide();
                $('#btnDeleteSelected')
                    .show()
                    .html('<i class="fas fa-trash mr-1"></i> Delete Selected')
                    .removeClass('btn-outline-danger')
                    .addClass('btn-danger');
            } else {
                $('#btnDeleteSelected, #btnEmptyTrash').hide();
            }
            $('#sendCourierDropdown, #selectCourierDropdown').prop('disabled', false).removeClass('disabled');
            $('.bulk-courier-action, .bulk-assign-courier-action, .bulk-assign-employee-action').removeClass('disabled');
        }
    }

    function reloadTable(page = 1) {
        $('#content-wrapper').css('opacity', '0.6');

        $.ajax({
            url: getBaseUrl(),
            type: 'GET',
            data: getQueryParams(page),
            success: function(res) {
                if (res.status && res.html) {
                    $('#content-wrapper').html(res.html).css('opacity', '1');
                    if (res.stats) updateStats(res.stats);
                    updateUIState();
                    updateSelectedCount();

                    if (pendingSelectLimit) {
                        selectFirstVisibleOrders(pendingSelectLimit);
                        pendingSelectLimit = null;
                    }

                    syncBrowserUrl(page);
                } else {
                    $('#content-wrapper').css('opacity', '1');
                    showToast('error', 'Failed to fetch orders.');
                }
            },
            error: function(xhr) {
                $('#content-wrapper').css('opacity', '1');
                showToast('error', xhr.responseJSON?.message || 'Failed to fetch orders.');
            }
        });
    }

    function runBulkAction(action) {
        let ids = selectedIds();

        if (!ids.length) {
            Swal.fire('Notice', 'Please select at least one order.', 'info');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `This action will apply to ${ids.length} selected orders.`,
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Proceed',
            confirmButtonColor: '#2563eb'
        }).then((result) => {
            if (!swalConfirmed(result)) return;

            $.ajax({
                url: "{{ route('admin.orders.multiple_action') }}",
                type: 'POST',
                data: { ids: ids, action: action },
                beforeSend: function() { $('#content-wrapper').css('opacity', '0.6'); },
                success: function(res) {
                    if (res.status) {
                        showToast('success', res.message || 'Bulk action completed successfully.');
                        reloadTable();
                    } else {
                        $('#content-wrapper').css('opacity', '1');
                        showToast('error', res.message || 'Bulk action failed.');
                    }
                },
                error: function(xhr) {
                    $('#content-wrapper').css('opacity', '1');
                    let message = xhr.responseJSON?.message || 'Bulk action failed.';
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        message = Object.values(xhr.responseJSON.errors)[0][0];
                    }
                    showToast('error', message);
                }
            });
        });
    }

    function updateNoteStatus(textarea, statusClass, message) {
        let row = textarea.closest('td');
        let statusBox = row.find('.admin-note-status');
        statusBox.removeClass('saving saved error').addClass(statusClass).text(message);
    }

    function saveAdminNote(textarea) {
        let url = textarea.data('url');
        let note = textarea.val();
        let original = textarea.attr('data-original') ?? '';

        if (note === original) {
            updateNoteStatus(textarea, '', 'Auto save enabled');
            return;
        }

        updateNoteStatus(textarea, 'saving', 'Saving...');

        $.ajax({
            url: url,
            type: 'PATCH',
            data: { admin_note: note },
            success: function(res) {
                if (res.status) {
                    textarea.attr('data-original', note);
                    updateNoteStatus(textarea, 'saved', 'Saved');
                    setTimeout(function() { updateNoteStatus(textarea, '', 'Auto save enabled'); }, 1500);
                } else {
                    updateNoteStatus(textarea, 'error', res.message || 'Save failed');
                }
            },
            error: function(xhr) {
                updateNoteStatus(textarea, 'error', xhr.responseJSON?.message || 'Save failed');
            }
        });
    }

    function renderFraudCheckerHtml(res) {
        let order = res.order || {};
        let data = res.data || {};
        let couriers = data.couriers || [];
        let rows = '';

        function resolveCancelCount(item) {
            let total = Number(item.total || 0);
            let success = Number(item.success || 0);
            let explicitCancel = item.cancel ?? item.cancelled ?? item.canceled ?? item.return ?? item.returned ?? item.failed;
            let cancel = Number(explicitCancel || 0);

            if (total > 0 && success >= 0 && success < total && (!explicitCancel || cancel === 0)) {
                cancel = Math.max(cancel, total - success);
            }

            return cancel;
        }

        let topTotal = Number(data.total || 0);
        let topSuccess = Number(data.success || 0);
        let topCancel = Number(data.cancel || data.cancelled || data.canceled || data.return || data.returned || data.failed || 0);

        if (topTotal > 0 && topSuccess >= 0 && topSuccess < topTotal && topCancel === 0) {
            topCancel = Math.max(topCancel, topTotal - topSuccess);
        }

        if (couriers.length) {
            couriers.forEach(function(item) {
                const cancelCount = resolveCancelCount(item);

                rows += `
                    <tr>
                        <td class="text-left font-weight-bold">${htmlEscape(item.courier || '-')}</td>
                        <td>${numberText(item.total)}</td>
                        <td class="text-success font-weight-bold">${numberText(item.success)}</td>
                        <td class="text-danger font-weight-bold">${numberText(cancelCount)}</td>
                        <td>${htmlEscape(item.success_ratio || 0)}%</td>
                    </tr>`;
            });
        } else {
            rows = `<tr><td colspan="5" class="text-center text-muted py-3">No courier history found.</td></tr>`;
        }

        return `
            <div class="text-left fraud-checker-popup">
                <div class="fraud-header-box mb-3">
                    <strong>Invoice:</strong> #${htmlEscape(order.invoice_id || '-')}<br>
                    <strong>Customer:</strong> ${htmlEscape(order.customer_name || '-')}<br>
                    <strong>Phone:</strong> ${htmlEscape(data.phone || order.phone || '-')}
                </div>
                <div class="row text-center mb-3">
                    <div class="col-3"><div class="border rounded p-2"><small>Total</small><div class="h5 mb-0">${numberText(topTotal)}</div></div></div>
                    <div class="col-3"><div class="border rounded p-2"><small>Success</small><div class="h5 mb-0 text-success">${numberText(topSuccess)}</div></div></div>
                    <div class="col-3"><div class="border rounded p-2"><small>Cancel</small><div class="h5 mb-0 text-danger">${numberText(topCancel)}</div></div></div>
                    <div class="col-3"><div class="border rounded p-2"><small>Success</small><div class="h5 mb-0">${htmlEscape(data.success_ratio || 0)}%</div></div></div>
                </div>
                <table class="table table-sm table-bordered mb-0 text-center">
                    <thead class="bg-light"><tr><th class="text-left">Courier</th><th>Total</th><th>Success</th><th>Cancelled</th><th>Success Ratio</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }

    function updateFraudCheckSummary(button, data) {
        let total = Number(data.total || 0);
        let success = Number(data.success || 0);
        let cancel = Number(data.cancel || data.cancelled || data.canceled || data.return || data.returned || data.failed || 0);

        if (total > 0 && success >= 0 && success < total && cancel === 0) {
            cancel = Math.max(cancel, total - success);
        }

        button.closest('.fraud-check-controls').find('.fraud-check-summary').html(`
            <span class="badge badge-success">Success: ${numberText(success)}</span>
            <span class="badge badge-danger ml-1">Cancel: ${numberText(cancel)}</span>
        `);
    }

    function submitSelectedInvoices(ids) {
        let form = $('<form>', { method: 'POST', action: "{{ route('admin.orders.selected_invoices') }}", target: '_blank' });
        form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
        ids.forEach(function(id) { form.append($('<input>', { type: 'hidden', name: 'ids[]', value: id })); });
        $('body').append(form);
        form.submit();
        form.remove();
    }

    function submitExportOrders(type) {
        let ids = selectedIds();

        if (!ids.length) {
            Swal.fire('Notice', 'Please select at least one order for export.', 'info');
            return;
        }

        let form = $('<form>', {
            method: 'POST',
            action: "{{ route('admin.orders.export') }}",
            target: '_blank'
        });

        form.append($('<input>', {
            type: 'hidden',
            name: '_token',
            value: '{{ csrf_token() }}'
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'type',
            value: type || 'default'
        }));

        ids.forEach(function(id) {
            form.append($('<input>', {
                type: 'hidden',
                name: 'ids[]',
                value: id
            }));
        });

        $('body').append(form);
        form.submit();
        form.remove();
    }

    function bulkCourierSend(url, emptyMessage, extraData = {}) {
        let ids = selectedIds();
        if (!ids.length) {
            Swal.fire('Notice', emptyMessage || 'Please select at least one order.', 'info');
            return;
        }

        let requestData = Object.assign({ ids: ids }, extraData || {});

        $.ajax({
            url: url,
            type: 'POST',
            data: requestData,
            beforeSend: function() { $('#content-wrapper').css('opacity', '0.6'); },
            success: function(res) {
                $('#content-wrapper').css('opacity', '1');
                if (res.status) {
                    showToast('success', res.message || 'Completed successfully.');
                    reloadTable();
                } else {
                    showToast('error', res.message || 'Failed.');
                }
            },
            error: function(xhr) {
                $('#content-wrapper').css('opacity', '1');
                showToast('error', xhr.responseJSON?.message || 'Failed.');
            }
        });
    }

    function assignCourierToSelected(courierId, courierName) {
        let ids = selectedIds();

        if (!ids.length) {
            Swal.fire('Notice', 'Please select at least one order for courier assign.', 'info');
            return;
        }

        Swal.fire({
            title: 'Assign Courier?',
            text: courierId === 'none'
                ? `Courier will be removed from ${ids.length} selected orders.`
                : `${courierName} will be assigned to ${ids.length} selected orders.`,
            icon: 'question',
            type: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Assign',
            confirmButtonColor: '#111827'
        }).then((result) => {
            if (!swalConfirmed(result)) return;

            bulkCourierSend(
                "{{ route('admin.orders.assign_courier_bulk') }}",
                'Please select at least one order for courier assign.',
                { courier_id: courierId }
            );
        });
    }

    function singleAjaxAction(button, successCallback) {
        let url = button.data('url');
        let oldHtml = button.html();

        $.ajax({
            url: url,
            type: button.data('method') || 'POST',
            beforeSend: function() { button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>'); },
            success: function(res) {
                button.prop('disabled', false).html(oldHtml);
                if (res.status) {
                    showToast('success', res.message || 'Completed successfully.');
                    if (successCallback) successCallback(res); else reloadTable();
                } else {
                    showToast('error', res.message || 'Action failed.');
                }
            },
            error: function(xhr) {
                button.prop('disabled', false).html(oldHtml);
                showToast('error', xhr.responseJSON?.message || 'Action failed.');
            }
        });
    }

    function loadOrderFieldsMenu() {
        const addItem = $('.nav-sidebar a').filter(function() {
            return $.trim($(this).text()).toLowerCase() === 'add order field';
        }).first();

        if (!addItem.length) return;

        $.get("{{ route('admin.orders.order_fields') }}", function(res) {
            if (!res.status) return;

            $('.dynamic-order-field-menu-item').remove();

            (res.fields || []).forEach(function(field) {
                const html = `
                    <li class="nav-item dynamic-order-field-menu-item">
                        <a href="${field.url}" class="nav-link">
                            <i class="fas fa-fw fa-tag nav-icon" style="color:${field.color || '#2563eb'}"></i>
                            <p>${htmlEscape(field.name)} <span class="badge badge-light right">${field.count || 0}</span></p>
                        </a>
                    </li>`;
                addItem.closest('li.nav-item').before(html);
            });
        });
    }

    $(document).on('change', '#check_all', function() {
        $('.row-checkbox').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });

    $(document).on('change', '.row-checkbox', updateSelectedCount);

    $(document).on('click', '.website-filter-menu', function(e) {
        e.stopPropagation();
    });

    $(document).on('change', '#websiteFilterAll', function() {
        const selectAll = $(this).is(':checked');

        // "All Websites" is a visual select-all control. Keep every source
        // checkbox in sync so the admin can immediately see what is selected.
        $('.website-filter-checkbox').prop('checked', selectAll);
    });

    $(document).on('change', '.website-filter-checkbox', function() {
        const $websiteCheckboxes = $('.website-filter-checkbox');
        const totalWebsites = $websiteCheckboxes.length;
        const selectedWebsites = $websiteCheckboxes.filter(':checked').length;

        // If every source is selected, reflect that state on "All Websites".
        // Unchecking any one source automatically clears the All checkbox.
        $('#websiteFilterAll').prop(
            'checked',
            totalWebsites === 0 || selectedWebsites === totalWebsites
        );
    });

    $(document).on('click', '#btnApplyWebsiteFilter', function(e) {
        e.preventDefault();

        let values = [];
        let labels = [];

        if ($('#websiteFilterAll').is(':checked')) {
            values = ['all'];
        } else {
            $('.website-filter-checkbox:checked').each(function() {
                values.push(String($(this).val()));
                labels.push(String($(this).data('label') || 'Website'));
            });
        }

        if (! values.length) {
            values = ['all'];
        }

        $('#filter_external_website_ids').val(values.join(','));

        let label = 'All Websites';
        if (values[0] !== 'all') {
            label = labels.length === 1 ? labels[0] : labels.length + ' Websites';
        }

        $('#websiteOrderFilterLabel').text(label);
        $('#websiteOrderFilterDropdown').dropdown('hide');
        reloadTable(1);
    });

    /*
    |--------------------------------------------------------------------------
    | Filter + Search
    |--------------------------------------------------------------------------
    | Direct click binding অনেক সময় কাজ না করলে delegated binding কাজ করবে।
    | Form submit fallback থাকার কারণে JS fail করলেও normal GET filter চলবে।
    */
    $(document).on('submit', '#orderFilterForm', function(e) {
        e.preventDefault();
        reloadTable(1);
    });

    $(document).on(
        'change',
        '#filter_order_status, #filter_payment_status, #filter_courier_id, #filter_fake_status, #filter_employee, #filter_order_field, #filter_date_from, #filter_date_to',
        function() {
            reloadTable(1);
        }
    );

    let searchTypingTimer;

    $(document).on('keyup', '#table_search', function(e) {
        clearTimeout(searchTypingTimer);

        if (e.key === 'Enter' || e.which === 13) {
            reloadTable(1);
            return;
        }

        searchTypingTimer = setTimeout(function() {
            reloadTable(1);
        }, 500);
    });

    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();

        let page = new URL($(this).attr('href'), window.location.origin)
            .searchParams
            .get('page') || 1;

        reloadTable(page);
    });

    function apiOrdersReloadAfterWebsiteSync() {
        window.location.href = window.location.pathname + window.location.search;
    }

    function runManualReceiveSync(button) {
        const $button = $(button);
        const websiteName = String($button.data('website-name') || 'website');
        const sourceDomain = String($button.data('domain') || websiteName);
        const actionUrl = String($button.data('url') || '');
        const batchSize = 20;
        let initialMissing = 0;
        let initialFailed = 0;
        let remainingMissing = 0;
        let remainingFailed = 0;
        let sentNow = 0;
        let failedDuringMissingSync = 0;
        let retriedSent = 0;
        let iterations = 0;

        if (! actionUrl) {
            showToast('error', 'Manual receive sync route was not found.');
            return;
        }

        Swal.fire({
            title: 'Manual Receive Sync',
            html: 'Checking missing/failed orders from <strong>' + htmlEscape(sourceDomain) + '</strong>...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });

        function requestRemote(action, limit) {
            return $.ajax({
                url: actionUrl,
                type: 'POST',
                dataType: 'json',
                headers: { 'Accept': 'application/json' },
                data: {
                    _token: csrfToken,
                    action: action,
                    limit: limit || batchSize
                }
            });
        }

        function finishSync() {
            const stillHasProblems = remainingMissing > 0 || remainingFailed > 0;

            Swal.fire({
                icon: stillHasProblems ? 'warning' : 'success',
                title: stillHasProblems ? 'Manual Sync Completed With Pending Issues' : 'Manual Receive Sync Completed',
                html:
                    'Website: <strong>' + htmlEscape(websiteName) + '</strong><br>' +
                    'Missing before: <strong>' + numberText(initialMissing) + '</strong><br>' +
                    'Failed before: <strong>' + numberText(initialFailed) + '</strong><br>' +
                    'Sent now: <strong class="text-success">' + numberText(sentNow + retriedSent) + '</strong><br>' +
                    'Still missing: <strong class="text-warning">' + numberText(remainingMissing) + '</strong><br>' +
                    'Still failed: <strong class="text-danger">' + numberText(remainingFailed) + '</strong>',
                confirmButtonText: 'Back to API Orders'
            }).then(apiOrdersReloadAfterWebsiteSync);
        }

        function retryFailedOnce() {
            if (remainingFailed <= 0) {
                finishSync();
                return;
            }

            Swal.update({
                html:
                    'Website: <strong>' + htmlEscape(sourceDomain) + '</strong><br>' +
                    'Missing remaining: ' + numberText(remainingMissing) + '<br>' +
                    'Retrying failed orders: ' + numberText(remainingFailed)
            });

            requestRemote('retry_failed', Math.min(Math.max(remainingFailed, 1), 500))
                .done(function(response) {
                    const data = response.data || {};
                    retriedSent += Number(data.sent || 0);
                    remainingMissing = Number(data.remaining || remainingMissing || 0);
                    remainingFailed = Number(data.failed_total ?? data.failed ?? remainingFailed ?? 0);
                    finishSync();
                })
                .fail(function(xhr) {
                    Swal.fire(
                        'Failed Order Retry Error',
                        xhr.responseJSON?.message || 'Missing orders were checked, but failed-order retry could not be completed.',
                        'error'
                    ).then(apiOrdersReloadAfterWebsiteSync);
                });
        }

        function syncMissingBatches() {
            if (remainingMissing <= 0) {
                retryFailedOnce();
                return;
            }

            iterations++;

            if (iterations > 1000) {
                Swal.fire(
                    'Manual Sync Stopped',
                    'Safety limit reached. Please run Manual Receive Sync again.',
                    'warning'
                ).then(apiOrdersReloadAfterWebsiteSync);
                return;
            }

            Swal.update({
                html:
                    'Website: <strong>' + htmlEscape(sourceDomain) + '</strong><br>' +
                    'Recovering missing orders...<br>' +
                    'Remaining: ' + numberText(remainingMissing) + '<br>' +
                    'Failed: ' + numberText(remainingFailed)
            });

            requestRemote('sync_missing', batchSize)
                .done(function(response) {
                    const data = response.data || {};
                    sentNow += Number(data.sent || 0);
                    failedDuringMissingSync += Number(data.failed || 0);
                    remainingMissing = Number(data.remaining || 0);
                    remainingFailed = Number(data.failed_total || 0);
                    syncMissingBatches();
                })
                .fail(function(xhr) {
                    Swal.fire(
                        'Manual Receive Sync Failed',
                        xhr.responseJSON?.message || 'Could not recover missing orders from the source website.',
                        'error'
                    ).then(apiOrdersReloadAfterWebsiteSync);
                });
        }

        requestRemote('progress', 1)
            .done(function(response) {
                const data = response.data || {};
                initialMissing = Number(data.remaining || 0);
                initialFailed = Number(data.failed_total || 0);
                remainingMissing = initialMissing;
                remainingFailed = initialFailed;

                if (initialMissing <= 0 && initialFailed <= 0) {
                    finishSync();
                    return;
                }

                syncMissingBatches();
            })
            .fail(function(xhr) {
                Swal.fire(
                    'Manual Receive Sync Failed',
                    xhr.responseJSON?.message || 'Could not connect to the source website for manual sync.',
                    'error'
                );
            });
    }

    $(document).on('click', '.api-manual-receive-sync', function(event) {
        event.preventDefault();
        const button = this;
        const websiteName = String($(button).data('website-name') || 'this website');

        Swal.fire({
            title: 'Recover missing orders?',
            text: 'The source website will resend orders that are missing or previously failed for ' + websiteName + '. Existing imported orders will not be duplicated.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Manual Sync'
        }).then(function(result) {
            if (swalConfirmed(result)) {
                runManualReceiveSync(button);
            }
        });
    });

    function runApiWebsiteMissingSync(form) {
        const $form = $(form);
        const websiteName = String($form.data('website-name') || 'website');
        const action = String($form.attr('action') || '');
        const batchSize = Number($form.find('input[name="limit"]').val() || 20);
        let totalSent = 0;
        let totalFailed = 0;
        let totalSkipped = 0;
        let iterations = 0;

        Swal.fire({
            title: 'Syncing Website Orders',
            html: 'Syncing missing local orders to <strong>' + htmlEscape(websiteName) + '</strong>...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });

        function runBatch() {
            iterations++;

            if (iterations > 1000) {
                Swal.fire('Sync Stopped', 'Safety limit reached. Please run Website Orders Sync again.', 'warning')
                    .then(apiOrdersReloadAfterWebsiteSync);
                return;
            }

            $.ajax({
                url: action,
                type: 'POST',
                dataType: 'json',
                headers: { 'Accept': 'application/json' },
                data: {
                    _token: csrfToken,
                    limit: batchSize
                },
                success: function(response) {
                    const data = response.data || {};
                    totalSent += Number(data.sent || 0);
                    totalFailed += Number(data.failed || 0);
                    totalSkipped += Number(data.skipped || 0);
                    const remaining = Number(data.remaining || 0);

                    if (remaining > 0) {
                        Swal.update({
                            html:
                                'Website: <strong>' + htmlEscape(websiteName) + '</strong><br>' +
                                'Sent: ' + totalSent + '<br>' +
                                'Failed: ' + totalFailed + '<br>' +
                                'Skipped: ' + totalSkipped + '<br>' +
                                'Remaining: ' + remaining
                        });
                        runBatch();
                        return;
                    }

                    Swal.fire({
                        icon: totalFailed > 0 ? 'warning' : 'success',
                        title: totalFailed > 0 ? 'Website Sync Completed With Errors' : 'Website Sync Completed',
                        html:
                            'Website: <strong>' + htmlEscape(websiteName) + '</strong><br>' +
                            'Sent: ' + totalSent + '<br>' +
                            'Failed: ' + totalFailed + '<br>' +
                            'Skipped: ' + totalSkipped,
                        confirmButtonText: 'Back to API Orders'
                    }).then(apiOrdersReloadAfterWebsiteSync);
                },
                error: function(xhr) {
                    Swal.fire(
                        'Sync Failed',
                        xhr.responseJSON?.message || 'Website order sync failed. Please try again.',
                        'error'
                    ).then(apiOrdersReloadAfterWebsiteSync);
                }
            });
        }

        runBatch();
    }

    $(document).on('submit', '.api-form-sync-existing', function(event) {
        event.preventDefault();
        const form = this;
        const websiteName = String($(form).data('website-name') || 'this website');

        Swal.fire({
            title: 'Sync missing orders?',
            text: 'All unsynced local orders will be sent to ' + websiteName + ' in safe batches. Imported API orders will not be sent again.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Sync Orders'
        }).then(function(result) {
            if (swalConfirmed(result)) {
                runApiWebsiteMissingSync(form);
            }
        });
    });

    function runApiWebsiteRefresh(form) {
        const $form = $(form);
        const websiteName = String($form.data('website-name') || 'website');
        const action = String($form.attr('action') || '');
        const batchSize = Number($form.find('input[name="limit"]').val() || 20);
        let cursor = Number($form.find('input[name="cursor"]').val() || 0);
        let refreshed = 0;
        let failed = 0;
        let iterations = 0;

        Swal.fire({
            title: 'Refreshing Synced Orders',
            html: 'Refreshing already synced order data for <strong>' + htmlEscape(websiteName) + '</strong>...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });

        function runBatch() {
            iterations++;

            if (iterations > 1000) {
                Swal.fire('Refresh Stopped', 'Safety limit reached. Please run refresh again.', 'warning')
                    .then(apiOrdersReloadAfterWebsiteSync);
                return;
            }

            $.ajax({
                url: action,
                type: 'POST',
                dataType: 'json',
                headers: { 'Accept': 'application/json' },
                data: {
                    _token: csrfToken,
                    limit: batchSize,
                    cursor: cursor
                },
                success: function(response) {
                    const data = response.data || {};
                    refreshed += Number(data.refreshed || 0);
                    failed += Number(data.failed || 0);
                    cursor = Number(data.next_cursor || cursor);
                    const remaining = Number(data.remaining || 0);

                    if (remaining > 0) {
                        Swal.update({
                            html:
                                'Website: <strong>' + htmlEscape(websiteName) + '</strong><br>' +
                                'Refreshed: ' + refreshed + '<br>' +
                                'Failed: ' + failed + '<br>' +
                                'Remaining: ' + remaining
                        });
                        runBatch();
                        return;
                    }

                    Swal.fire({
                        icon: failed > 0 ? 'warning' : 'success',
                        title: failed > 0 ? 'Refresh Completed With Errors' : 'Synced Orders Refreshed',
                        html:
                            'Website: <strong>' + htmlEscape(websiteName) + '</strong><br>' +
                            'Refreshed: ' + refreshed + '<br>' +
                            'Failed: ' + failed,
                        confirmButtonText: 'Back to API Orders'
                    }).then(apiOrdersReloadAfterWebsiteSync);
                },
                error: function(xhr) {
                    Swal.fire(
                        'Refresh Failed',
                        xhr.responseJSON?.message || 'Synced order refresh failed. Please try again.',
                        'error'
                    ).then(apiOrdersReloadAfterWebsiteSync);
                }
            });
        }

        runBatch();
    }

    $(document).on('submit', '.api-form-refresh-synced-orders', function(event) {
        event.preventDefault();
        const form = this;
        const websiteName = String($(form).data('website-name') || 'this website');

        Swal.fire({
            title: 'Refresh already synced orders?',
            text: 'Existing synced orders will be refreshed for ' + websiteName + ' without creating duplicates.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Refresh'
        }).then(function(result) {
            if (swalConfirmed(result)) {
                runApiWebsiteRefresh(form);
            }
        });
    });

    $(document).on('submit', '.api-form-retry-failed-orders', function(event) {
        event.preventDefault();
        const $form = $(this);
        const websiteName = String($form.data('website-name') || 'this website');
        const action = String($form.attr('action') || '');
        const limit = Number($form.find('input[name="limit"]').val() || 100);

        Swal.fire({
            title: 'Retry failed orders?',
            text: 'Failed website-order sync attempts will be retried for ' + websiteName + '.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Retry'
        }).then(function(result) {
            if (! swalConfirmed(result)) {
                return;
            }

            Swal.fire({
                title: 'Retrying Failed Orders',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: action,
                type: 'POST',
                dataType: 'json',
                headers: { 'Accept': 'application/json' },
                data: {
                    _token: csrfToken,
                    limit: limit
                },
                success: function(response) {
                    const data = response.data || {};
                    Swal.fire({
                        icon: Number(data.failed || 0) > 0 ? 'warning' : 'success',
                        title: 'Retry Finished',
                        html:
                            'Website: <strong>' + htmlEscape(websiteName) + '</strong><br>' +
                            'Sent: ' + Number(data.sent || 0) + '<br>' +
                            'Still failed: ' + Number(data.failed || 0),
                        confirmButtonText: 'Back to API Orders'
                    }).then(apiOrdersReloadAfterWebsiteSync);
                },
                error: function(xhr) {
                    Swal.fire(
                        'Retry Failed',
                        xhr.responseJSON?.message || 'Failed-order retry could not be completed.',
                        'error'
                    ).then(apiOrdersReloadAfterWebsiteSync);
                }
            });
        });
    });

    $(document).on('click', '.sync-webhook-action', function(e) {
        e.preventDefault();

        const syncName = String($(this).data('name') || 'Courier');
        const syncUrl = String($(this).data('url') || '');

        if (!syncUrl) {
            showToast('error', 'Sync route পাওয়া যায়নি।');
            return;
        }

        Swal.fire({
            title: 'Sync ' + syncName + ' webhook/status?',
            text: 'Latest courier statuses will be synchronized using the existing command route.',
            icon: 'question',
            type: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Sync Now',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (swalConfirmed(result)) {
                const targetUrl = new URL(syncUrl, window.location.origin);
                targetUrl.searchParams.set(
                    'return_to',
                    window.location.pathname + window.location.search
                );
                window.location.href = targetUrl.toString();
            }
        });
    });

    $(document).on('click', '#btnToggleTrash', function() {
        currentView = currentView === 'trash' ? 'active' : 'trash';
        reloadTable(1);
    });

    $(document).on('click', '.bulk-status-action', function(e) {
        e.preventDefault();
        runBulkAction($(this).data('action'));
    });

    $('#btnDeleteSelected').on('click', function() {
        if (!canDeleteOrders) {
            Swal.fire('Permission denied', 'Employee cannot delete orders.', 'warning');
            return;
        }

        if (currentView === 'trash') {
            Swal.fire('Notice', 'Trash page থেকে bulk permanent delete বন্ধ করা হয়েছে। Empty Trash button ব্যবহার করুন।', 'info');
            return;
        }

        runBulkAction('delete');
    });

    $('#btnPrintSelectedInvoice').on('click', function() {
        let ids = selectedIds();
        if (!ids.length) {
            Swal.fire('Notice', 'Please select at least one order.', 'info');
            return;
        }
        submitSelectedInvoices(ids);
    });

    $(document).on('click', '.export-orders-action', function(e) {
        e.preventDefault();
        submitExportOrders($(this).data('type') || 'default');
    });

    $(document).on('click', '.bulk-courier-action', function(e) {
        e.preventDefault();

        if ($(this).hasClass('disabled')) {
            return;
        }

        let url = $(this).data('url');
        let name = $(this).data('name') || 'Courier';
        let courierId = $(this).data('courier-id');
        let extraData = {};

        if (courierId !== undefined) {
            extraData.courier_id = courierId;
        }

        bulkCourierSend(url, 'Please select at least one order for ' + name + '.', extraData);
    });

    $(document).on('click', '.bulk-assign-courier-action', function(e) {
        e.preventDefault();

        if ($(this).hasClass('disabled')) {
            return;
        }

        assignCourierToSelected($(this).data('courier-id'), $(this).data('name') || 'Courier');
    });


    $(document).on('click', '.manual-auto-employee-sync', function(e) {
        e.preventDefault();

        if (!isAdminUser) {
            Swal.fire('Permission denied', 'Only admin can run Auto Employee Sync.', 'warning');
            return;
        }

        const syncUrl = String($(this).data('url') || '');

        if (!syncUrl) {
            showToast('error', 'Auto Employee Sync route was not found.');
            return;
        }

        Swal.fire({
            title: 'Run Auto Employee Sync?',
            text: 'All local unassigned active orders will be assigned automatically using the existing round-robin employee sequence.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Sync Now',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (!swalConfirmed(result)) {
                return;
            }

            $.ajax({
                url: syncUrl,
                type: 'POST',
                data: { _token: csrfToken },
                beforeSend: function() {
                    $('#content-wrapper').css('opacity', '0.6');
                },
                success: function(res) {
                    showToast(res.status ? 'success' : 'error', res.message || 'Auto Employee Sync completed.');
                    reloadTable();
                },
                error: function(xhr) {
                    $('#content-wrapper').css('opacity', '1');
                    showToast('error', xhr.responseJSON?.message || 'Auto Employee Sync failed.');
                }
            });
        });
    });

    $(document).on('click', '.bulk-assign-employee-action', function(e) {
        e.preventDefault();

        if (!isAdminUser) {
            Swal.fire('Permission denied', 'Only admin can assign employees.', 'warning');
            return;
        }

        let ids = selectedIds();
        if (!ids.length) {
            Swal.fire('Notice', 'Please select at least one order.', 'info');
            return;
        }

        let employeeId = $(this).data('employee-id') || '';
        let name = $(this).data('name') || 'Employee';

        Swal.fire({
            title: 'Assign selected orders?',
            text: 'Selected orders will be assigned to ' + name + '.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, assign'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: "{{ route('admin.orders.assign_employee_bulk') }}",
                type: 'POST',
                data: {
                    _token: csrfToken,
                    ids: ids,
                    assigned_employee_id: employeeId
                },
                success: function(res) {
                    showToast(res.status ? 'success' : 'error', res.message || 'Employee assign updated.');
                    reloadTable();
                },
                error: function(xhr) {
                    showToast('error', xhr.responseJSON?.message || 'Employee assign failed.');
                }
            });
        });
    });

    $(document).on('click', '.bulk-select-limit-action', function(e) {
        e.preventDefault();

        const limit = Number($(this).data('limit') || 0);

        if (!limit) {
            return;
        }

        const visibleRows = $('.row-checkbox').length;
        const currentPerPage = Number($('#filter_per_page').val() || 20);

        if (visibleRows < limit && currentPerPage < limit) {
            pendingSelectLimit = limit;
            $('#filter_per_page').val(limit);
            reloadTable(1);
            return;
        }

        selectFirstVisibleOrders(limit);
    });

    $('#btnEmptyTrash').on('click', function() {
        if (!canDeleteOrders) {
            Swal.fire('Permission denied', 'Employee cannot empty trash.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Empty trash permanently?',
            text: 'All trash orders will be permanently deleted. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, empty trash'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: "{{ route('admin.orders.empty_trash') }}",
                type: 'DELETE',
                data: {_token: csrfToken},
                success: function(res) {
                    showToast(res.status ? 'success' : 'error', res.message || 'Trash emptied.');
                    reloadTable();
                },
                error: function(xhr) {
                    showToast('error', xhr.responseJSON?.message || 'Empty trash failed.');
                }
            });
        });
    });


    $(document).on('change', '.order-status-inline-select', function() {
        let select = $(this);
        let url = select.data('url');
        let oldStatus = select.attr('data-original') || 'processing';
        let newStatus = select.val();

        if (!url || newStatus === oldStatus) {
            return;
        }

        select.prop('disabled', true);

        $.ajax({
            url: url,
            type: 'PATCH',
            data: {
                _token: csrfToken,
                order_status: newStatus
            },
            success: function(res) {
                if (res.status) {
                    select.attr('data-original', newStatus);
                    showToast('success', res.message || 'Order status updated successfully.');
                    reloadTable();
                } else {
                    select.val(oldStatus);
                    showToast('error', res.message || 'Status update failed.');
                }
            },
            error: function(xhr) {
                select.val(oldStatus);
                showToast('error', xhr.responseJSON?.message || 'Status update failed.');
            },
            complete: function() {
                select.prop('disabled', false);
            }
        });
    });

    $(document).on('input', '.admin-note-input', function() {
        let textarea = $(this);
        let orderId = textarea.data('order-id');
        clearTimeout(adminNoteTimers[orderId]);
        updateNoteStatus(textarea, 'saving', 'Waiting...');
        adminNoteTimers[orderId] = setTimeout(function() { saveAdminNote(textarea); }, 700);
    });

    $(document).on('blur', '.admin-note-input', function() { saveAdminNote($(this)); });

    $(document).on('click', '.btnDelete, .btnRestore, .btnForceDelete', function() {
        let button = $(this);
        let isDelete = button.hasClass('btnDelete') || button.hasClass('btnForceDelete');

        Swal.fire({
            title: isDelete ? 'Are you sure?' : 'Restore order?',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Proceed'
        }).then(function(result) {
            if (!swalConfirmed(result)) return;
            button.data('method', button.hasClass('btnDelete') || button.hasClass('btnForceDelete') ? 'DELETE' : 'POST');
            singleAjaxAction(button);
        });
    });

    $(document).on('click', '.btnSendSteadfast, .btnSyncSteadfast, .btnSendPathao', function() {
        singleAjaxAction($(this));
    });

    $(document).on('click', '.btnFraudCheck', function() {
        let button = $(this);
        let oldHtml = button.html();

        $.ajax({
            url: button.data('url'),
            type: 'POST',
            beforeSend: function() {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            },
            success: function(res) {
                button.prop('disabled', false).html(oldHtml);

                if (res.status) {
                    updateFraudCheckSummary(button, res.data || {});

                    Swal.fire({
                        title: 'Fraud Check Result',
                        html: renderFraudCheckerHtml(res),
                        width: 850
                    });
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Fraud Check Failed',
                    text: res.message || 'Unable to fetch courier fraud data right now. Please try again later.'
                });
            },
            error: function(xhr) {
                button.prop('disabled', false).html(oldHtml);

                Swal.fire({
                    icon: 'error',
                    title: 'Fraud Check Failed',
                    text: xhr.responseJSON?.message || 'Unable to fetch courier fraud data right now. Please try again later.'
                });
            }
        });
    });

    $(document).on('click', '.js-add-order-field, .nav-sidebar a', function(e) {
        const text = $.trim($(this).text()).toLowerCase();
        if (text !== 'add order field') return;
        e.preventDefault();
        $('#addOrderFieldModal').modal('show');
    });

    $('#addOrderFieldForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const button = form.find('[type="submit"]');
        const oldHtml = button.html();

        $.ajax({
            url: "{{ route('admin.orders.order_fields.store') }}",
            type: 'POST',
            data: form.serialize(),
            beforeSend: function() { button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...'); },
            success: function(res) {
                button.prop('disabled', false).html(oldHtml);
                if (res.status) {
                    $('#addOrderFieldModal').modal('hide');
                    form[0].reset();
                    showToast('success', res.message || 'Order field created successfully.');
                    setTimeout(function() { window.location.href = res.field.url; }, 700);
                } else {
                    showToast('error', res.message || 'Field create failed.');
                }
            },
            error: function(xhr) {
                button.prop('disabled', false).html(oldHtml);
                let message = xhr.responseJSON?.message || 'Field create failed.';
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    message = Object.values(xhr.responseJSON.errors)[0][0];
                }
                showToast('error', message);
            }
        });
    });

    updateUIState();
    loadOrderFieldsMenu();
});
</script>
@endsection
