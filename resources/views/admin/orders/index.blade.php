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
@endphp

{{-- Top Small Stats --}}
<div class="row mb-3" id="orderStatsCards">
    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.all') }}"
           class="order-stat-card text-decoration-none {{ $currentStatusView === 'all' ? 'active' : '' }}">
            <div>
                <h4 id="stat_all">{{ $stats['all'] ?? 0 }}</h4>
                <p>All Orders</p>
            </div>
            <i class="fas fa-shopping-cart"></i>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.index') }}"
           class="order-stat-card text-decoration-none {{ $currentStatusView === 'new' ? 'active' : '' }}">
            <div>
                <h4 id="stat_new">{{ $stats['new'] ?? 0 }}</h4>
                <p>New Orders</p>
            </div>
            <i class="fas fa-shopping-cart"></i>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.pending') }}"
           class="order-stat-card text-decoration-none {{ $currentStatusView === 'pending' ? 'active' : '' }}">
            <div>
                <h4 id="stat_pending">{{ $stats['pending'] ?? 0 }}</h4>
                <p>Pending Orders</p>
            </div>
            <i class="fas fa-shopping-cart"></i>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.delivered') }}"
           class="order-stat-card text-decoration-none {{ $currentStatusView === 'completed' ? 'active' : '' }}">
            <div>
                <h4 id="stat_completed">{{ $stats['completed'] ?? 0 }}</h4>
                <p>Complete Orders</p>
            </div>
            <i class="fas fa-shopping-cart"></i>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.cancelled') }}"
           class="order-stat-card text-decoration-none {{ $currentStatusView === 'cancelled' ? 'active' : '' }}">
            <div>
                <h4 id="stat_cancelled">{{ $stats['cancelled'] ?? 0 }}</h4>
                <p>Cancelled Orders</p>
            </div>
            <i class="fas fa-shopping-cart"></i>
        </a>
    </div>

    @foreach($orderFields ?? [] as $field)
        <div class="col-lg col-md-4 col-sm-6 mb-2 order-dynamic-field-card" data-field-id="{{ $field->id }}">
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
                @if(auth()->user()->isAdmin())
                    <button class="btn btn-outline-info btn-sm px-3 shadow-none mr-1" id="btnSteadfastBalance">
                        <i class="fas fa-wallet mr-1"></i> SteadFast Balance
                    </button>

                    <button class="btn btn-outline-success btn-sm px-3 shadow-none mr-1" id="btnAssignUnassigned">
                        <i class="fas fa-random mr-1"></i> Assign Unassigned
                    </button>

                    <button class="btn btn-outline-danger btn-sm px-3 shadow-none" id="btnToggleTrash">
                        <i class="fas fa-trash-alt mr-1"></i> Trash Bin
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        {{-- Filter Section --}}
        <form id="orderFilterForm"
              class="px-4 py-3 border-top bg-white"
              method="GET"
              action="{{ url()->current() }}">
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
        @if(auth()->user()->isAdmin())
            <div class="px-4 py-2 bg-light border-top border-bottom">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center flex-wrap">
                        <span class="badge badge-dark px-3 py-2 mr-2 mb-1">
                            Total <span id="selectedCount">0</span> Orders
                        </span>

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

                        <button type="button" class="btn btn-danger btn-sm mr-2 mb-1" id="btnDeleteSelected">
                            <i class="fas fa-trash mr-1"></i> Delete Selected
                        </button>

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

                        <button type="button" class="btn btn-warning btn-sm mr-2 mb-1" id="btnAssignUnassignedTwo">
                            <i class="fas fa-sync-alt mr-1"></i> Sync Order
                        </button>

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
.order-stat-card {
    min-height: 82px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #e9eef5;
    border-radius: 12px;
    padding: 15px 20px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, .05);
    color: #111827;
    transition: .2s ease;
}

.order-stat-card h4 {
    font-size: 24px;
    font-weight: 800;
    margin: 0;
    line-height: 1;
}

.order-stat-card p {
    margin: 6px 0 0;
    font-weight: 700;
    color: #111827;
}

.order-stat-card i {
    color: #3b82f6;
    font-size: 30px;
    opacity: .85;
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
</style>
@endsection

@section('js')
<script>
$(document).ready(function() {
    let currentView = "{{ isset($isTrash) && $isTrash ? 'trash' : 'active' }}";
    let currentBaseUrl = "{{ url()->current() }}";
    let adminNoteTimers = {};

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
        $('#stat_cancelled').text(stats.cancelled ?? 0);

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

    function updateUIState() {
        if (currentView === 'trash') {
            $('#view-label').text('Trash Bin').attr('class', 'badge badge-danger ml-2 border');
            $('#btnToggleTrash')
                .html('<i class="fas fa-list mr-1"></i> Active List')
                .removeClass('btn-outline-danger')
                .addClass('btn-outline-primary');
            $('#sendCourierDropdown, #selectCourierDropdown').prop('disabled', true).addClass('disabled');
            $('.bulk-courier-action, .bulk-assign-courier-action').addClass('disabled');
        } else {
            $('#view-label').text('Active List').attr('class', 'badge badge-primary-soft ml-2 border');
            $('#btnToggleTrash')
                .html('<i class="fas fa-trash-alt mr-1"></i> Trash Bin')
                .removeClass('btn-outline-primary')
                .addClass('btn-outline-danger');
            $('#sendCourierDropdown, #selectCourierDropdown').prop('disabled', false).removeClass('disabled');
            $('.bulk-courier-action, .bulk-assign-courier-action').removeClass('disabled');
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

        if (couriers.length) {
            couriers.forEach(function(item) {
                rows += `
                    <tr>
                        <td class="text-left font-weight-bold">${htmlEscape(item.courier || '-')}</td>
                        <td>${numberText(item.total)}</td>
                        <td class="text-success font-weight-bold">${numberText(item.success)}</td>
                        <td class="text-danger font-weight-bold">${numberText(item.cancel)}</td>
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
                    <div class="col-3"><div class="border rounded p-2"><small>Total</small><div class="h5 mb-0">${numberText(data.total)}</div></div></div>
                    <div class="col-3"><div class="border rounded p-2"><small>Success</small><div class="h5 mb-0 text-success">${numberText(data.success)}</div></div></div>
                    <div class="col-3"><div class="border rounded p-2"><small>Cancel</small><div class="h5 mb-0 text-danger">${numberText(data.cancel)}</div></div></div>
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
        '#filter_order_status, #filter_payment_status, #filter_courier_service, #filter_fake_status, #filter_employee, #filter_order_field, #filter_date_from, #filter_date_to',
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

    $('#btnDeleteSelected').on('click', function() { runBulkAction('delete'); });

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

    $('#btnAssignUnassigned, #btnAssignUnassignedTwo').on('click', function() {
        singleAjaxAction($(this).attr('id') === 'btnAssignUnassigned' ? $('#btnAssignUnassigned') : $('#btnAssignUnassignedTwo'), function() {
            reloadTable();
        });
    });

    $('#btnAssignUnassigned, #btnAssignUnassignedTwo').data('url', "{{ route('admin.orders.assign_unassigned') }}");

    $('#btnSteadfastBalance').on('click', function() {
        $.ajax({
            url: "{{ route('admin.orders.steadfast.balance') }}",
            type: 'GET',
            success: function(res) {
                if (res.status) {
                    Swal.fire('SteadFast Balance', '<pre class="text-left mb-0">' + htmlEscape(JSON.stringify(res.data, null, 2)) + '</pre>', 'info');
                } else {
                    showToast('error', res.message || 'Balance fetch failed.');
                }
            },
            error: function(xhr) { showToast('error', xhr.responseJSON?.message || 'Balance fetch failed.'); }
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
        $.ajax({
            url: button.data('url'),
            type: 'POST',
            beforeSend: function() { button.prop('disabled', true); },
            success: function(res) {
                button.prop('disabled', false);
                if (res.status) {
                    Swal.fire({ title: 'Fraud Check Result', html: renderFraudCheckerHtml(res), width: 850 });
                } else {
                    showToast('error', res.message || 'Fraud check failed.');
                }
            },
            error: function(xhr) {
                button.prop('disabled', false);
                showToast('error', xhr.responseJSON?.message || 'Fraud check failed.');
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