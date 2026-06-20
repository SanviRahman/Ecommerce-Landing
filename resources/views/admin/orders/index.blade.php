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
    $canBulkManageOrders = auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isEmployee());
    $canDeleteOrders = auth()->check() && auth()->user()->isAdmin();
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
                @if(auth()->user()->isAdmin() && empty($isTrash))
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
            <div class="row">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Order Status</label>
                    <select id="filter_order_status"
                            name="order_status"
                            class="form-control border-0 bg-light shadow-none">
                        <option value="all" @selected(request('order_status', 'all') === 'all')>All Status</option>
                        @foreach($orderStatuses ?? [] as $status)
                            <option value="{{ $status }}" @selected(request('order_status') === $status)>
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
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
                           placeholder="Search invoice, customer, phone, address, product, courier, employee...">
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
                                        <i class="fas fa-check-square text-primary mr-1"></i> Select {{ $selectLimit }} Orders
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
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_processing">
                                    <i class="fas fa-spinner mr-1"></i> Processing / New
                                </a>
                                <a class="dropdown-item bulk-status-action" href="#" data-action="status_shipped">
                                    <i class="fas fa-truck mr-1"></i> Shipped
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
                                <i class="fas fa-sync-alt mr-1"></i> Sync Order
                            </button>
                            <div class="dropdown-menu" aria-labelledby="syncOrderDropdown">
                                <h6 class="dropdown-header">Assign Employee</h6>
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
                            <button class="btn btn-outline-danger btn-sm mr-2 mb-1 shadow-none" id="btnToggleTrash" type="button">
                                @if(!empty($isTrash))
                                    <i class="fas fa-list mr-1"></i> Active List
                                @else
                                    <i class="fas fa-trash-alt mr-1"></i> Trash Bin
                                @endif
                            </button>

                            <button class="btn btn-danger btn-sm mr-2 mb-1 shadow-none"
                                    id="btnEmptyTrash"
                                    type="button"
                                    style="{{ !empty($isTrash) ? '' : 'display:none;' }}">
                                <i class="fas fa-broom mr-1"></i> Empty Trash
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
                'duplicatePhoneCounts' => $duplicatePhoneCounts ?? [],
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
    <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
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
        $('#selectedCount').text(selectedIds().length);
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
