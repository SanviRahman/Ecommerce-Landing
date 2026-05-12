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

{{-- Top Small Stats --}}
<div class="row mb-3" id="orderStatsCards">
    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.index') }}" class="order-stat-card text-decoration-none">
            <div>
                <h4 id="stat_all">{{ $stats['all'] ?? 0 }}</h4>
                <p>All Orders</p>
            </div>
            <i class="fas fa-shopping-cart"></i>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.processing') }}" class="order-stat-card text-decoration-none">
            <div>
                <h4 id="stat_processing">{{ $stats['processing'] ?? 0 }}</h4>
                <p>Processing Orders</p>
            </div>
            <i class="fas fa-spinner"></i>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.delivered') }}" class="order-stat-card text-decoration-none">
            <div>
                <h4 id="stat_delivered">{{ $stats['delivered'] ?? 0 }}</h4>
                <p>Delivered Orders</p>
            </div>
            <i class="fas fa-check-circle"></i>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.cancelled') }}" class="order-stat-card text-decoration-none">
            <div>
                <h4 id="stat_cancelled">{{ $stats['cancelled'] ?? 0 }}</h4>
                <p>Cancelled Orders</p>
            </div>
            <i class="fas fa-times-circle"></i>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-2">
        <a href="{{ route('admin.orders.fake') }}" class="order-stat-card text-decoration-none">
            <div>
                <h4 id="stat_fake">{{ $stats['fake'] ?? 0 }}</h4>
                <p>Fake Orders</p>
            </div>
            <i class="fas fa-ban"></i>
        </a>
    </div>
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
        <div class="px-4 py-3 border-top bg-white">
            <div class="row">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Order Status</label>
                    <select id="filter_order_status" class="form-control border-0 bg-light shadow-none">
                        <option value="all">All Status</option>
                        @foreach($orderStatuses ?? [] as $status)
                            <option value="{{ $status }}">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Payment</label>
                    <select id="filter_payment_status" class="form-control border-0 bg-light shadow-none">
                        <option value="all">All Payment</option>
                        @foreach($paymentStatuses ?? [] as $status)
                            <option value="{{ $status }}">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Courier</label>
                    <select id="filter_courier_service" class="form-control border-0 bg-light shadow-none">
                        <option value="all">All Courier</option>
                        @foreach($courierServices ?? [] as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Fake Status</label>
                    <select id="filter_fake_status" class="form-control border-0 bg-light shadow-none">
                        <option value="all">All Orders</option>
                        <option value="real">Real Orders</option>
                        <option value="fake">Fake Orders</option>
                    </select>
                </div>

                @if(auth()->user()->isAdmin())
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Employee</label>
                        <select id="filter_employee" class="form-control border-0 bg-light shadow-none">
                            <option value="all">All Employees</option>
                            <option value="unassigned">Unassigned</option>

                            @foreach($employees ?? [] as $employee)
                                <option value="{{ $employee->id }}">
                                    {{ $employee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" id="filter_employee" value="all">
                @endif

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Date From</label>
                    <input type="date" id="filter_date_from" class="form-control border-0 bg-light shadow-none">
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Date To</label>
                    <input type="date" id="filter_date_to" class="form-control border-0 bg-light shadow-none">
                </div>

                <div class="col-md-8 col-sm-8 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Search</label>
                    <input type="text"
                           id="table_search"
                           class="form-control shadow-none"
                           placeholder="Search invoice, customer, phone, address, courier, employee...">
                </div>

                <div class="col-md-2 col-sm-4 mb-2 d-flex align-items-end">
                    <button class="btn btn-dark btn-block shadow-none" id="btnFilter">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </div>
            </div>
        </div>

        {{-- Bulk Action Bar --}}
        @if(auth()->user()->isAdmin())
            <div class="px-4 py-2 bg-light border-top border-bottom">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center flex-wrap">
                        <span class="badge badge-dark px-3 py-2 mr-2">
                            Total <span id="selectedCount">0</span> Orders
                        </span>

                        <button type="button"
                                class="btn btn-secondary btn-sm mr-2"
                                id="btnPrintSelectedInvoice">
                            <i class="fas fa-print mr-1"></i> Print Selected Invoice
                        </button>

                        <button type="button"
                                class="btn btn-primary btn-sm mr-2"
                                id="btnSendSelectedSteadfast">
                            <i class="fas fa-paper-plane mr-1"></i> Send SteadFast
                        </button>

                        <button type="button"
                                class="btn btn-success btn-sm mr-2"
                                id="btnSendSelectedPathao">
                            <i class="fas fa-shipping-fast mr-1"></i> Send Pathao
                        </button>

                        <button type="button"
                                class="btn btn-danger btn-sm mr-2"
                                id="btnDeleteSelected">
                            <i class="fas fa-trash mr-1"></i> Delete Selected
                        </button>

                        <div class="dropdown d-inline-block mr-2">
                            <button class="btn btn-info btn-sm dropdown-toggle"
                                    type="button"
                                    id="changeStatusDropdown"
                                    data-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false">
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
                                    <i class="fas fa-spinner mr-1"></i> Processing
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
                            </div>
                        </div>

                        <button type="button"
                                class="btn btn-warning btn-sm mr-2"
                                id="btnAssignUnassignedTwo">
                            <i class="fas fa-sync-alt mr-1"></i> Sync Order
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <div id="content-wrapper" style="min-height: 400px; position: relative;">
            @include('admin.orders.partials.table', [
                'orders' => $orders,
                'isTrash' => $isTrash ?? false,
                'courierServices' => $courierServices ?? [],
            ])
        </div>
    </div>
</div>

@endsection

@section('footer')
    <strong>
        © Copyright 2026 All rights reserved |
        This website developed by
        <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
    </strong>
@endsection

@section('plugins.Sweetalert2', true)

@section('js')
<script>
$(document).ready(function () {
    let currentView = "{{ isset($isTrash) && $isTrash ? 'trash' : 'active' }}";
    let adminNoteTimers = {};

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

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    });

    function getBaseUrl() {
        return currentView === 'trash'
            ? "{{ route('admin.orders.trashed') }}"
            : "{{ route('admin.orders.index') }}";
    }

    function getQueryParams(page = 1) {
        return {
            page: page,
            search: $('#table_search').val(),
            order_status: $('#filter_order_status').val(),
            payment_status: $('#filter_payment_status').val(),
            courier_service: $('#filter_courier_service').val(),
            fake_status: $('#filter_fake_status').val(),
            assigned_employee_id: $('#filter_employee').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        };
    }

    function updateStats(stats) {
        $('#stat_all').text(stats.all ?? 0);
        $('#stat_processing').text(stats.processing ?? 0);
        $('#stat_delivered').text(stats.delivered ?? 0);
        $('#stat_cancelled').text(stats.cancelled ?? 0);
        $('#stat_fake').text(stats.fake ?? 0);
    }

    function selectedIds() {
        return $('.row-checkbox:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function updateSelectedCount() {
        $('#selectedCount').text(selectedIds().length);
    }

    function updateUIState() {
        if (currentView === 'trash') {
            $('#view-label')
                .text('Trash Bin')
                .attr('class', 'badge badge-danger ml-2 border');

            $('#btnToggleTrash')
                .html('<i class="fas fa-list mr-1"></i> Active List')
                .removeClass('btn-outline-danger')
                .addClass('btn-outline-primary');

            $('#btnSendSelectedSteadfast').prop('disabled', true).addClass('disabled');
            $('#btnSendSelectedPathao').prop('disabled', true).addClass('disabled');
        } else {
            $('#view-label')
                .text('Active List')
                .attr('class', 'badge badge-primary-soft ml-2 border');

            $('#btnToggleTrash')
                .html('<i class="fas fa-trash-alt mr-1"></i> Trash Bin')
                .removeClass('btn-outline-primary')
                .addClass('btn-outline-danger');

            $('#btnSendSelectedSteadfast').prop('disabled', false).removeClass('disabled');
            $('#btnSendSelectedPathao').prop('disabled', false).removeClass('disabled');
        }
    }

    function reloadTable(page = 1) {
        $('#content-wrapper').css('opacity', '0.6');

        $.ajax({
            url: getBaseUrl(),
            type: 'GET',
            data: getQueryParams(page),
            success: function (res) {
                if (res.status && res.html) {
                    $('#content-wrapper').html(res.html).css('opacity', '1');

                    if (res.stats) {
                        updateStats(res.stats);
                    }

                    updateUIState();
                    updateSelectedCount();
                } else {
                    $('#content-wrapper').css('opacity', '1');
                    showToast('error', 'Failed to fetch orders.');
                }
            },
            error: function (xhr) {
                $('#content-wrapper').css('opacity', '1');

                let message = xhr.responseJSON?.message || 'Failed to fetch orders.';
                showToast('error', message);
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
            if (swalConfirmed(result)) {
                $.ajax({
                    url: "{{ route('admin.orders.multiple_action') }}",
                    type: "POST",
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids,
                        action: action
                    },
                    beforeSend: function () {
                        $('#content-wrapper').css('opacity', '0.6');
                    },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Bulk action completed successfully.');
                            reloadTable();
                        } else {
                            $('#content-wrapper').css('opacity', '1');
                            showToast('error', res.message || 'Bulk action failed.');
                        }
                    },
                    error: function (xhr) {
                        $('#content-wrapper').css('opacity', '1');

                        let message = xhr.responseJSON?.message || 'Bulk action failed.';

                        if (xhr.status === 422 && xhr.responseJSON?.errors) {
                            message = Object.values(xhr.responseJSON.errors)[0][0];
                        }

                        showToast('error', message);
                    }
                });
            }
        });
    }

    function updateNoteStatus(textarea, statusClass, message) {
        let row = textarea.closest('td');
        let statusBox = row.find('.admin-note-status');

        statusBox
            .removeClass('saving saved error')
            .addClass(statusClass)
            .text(message);
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
            data: {
                _token: '{{ csrf_token() }}',
                admin_note: note
            },
            success: function (res) {
                if (res.status) {
                    textarea.attr('data-original', note);
                    updateNoteStatus(textarea, 'saved', 'Saved');

                    setTimeout(function () {
                        updateNoteStatus(textarea, '', 'Auto save enabled');
                    }, 1500);
                } else {
                    updateNoteStatus(textarea, 'error', res.message || 'Save failed');
                }
            },
            error: function (xhr) {
                let message = xhr.responseJSON?.message || 'Save failed';

                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    message = Object.values(xhr.responseJSON.errors)[0][0];
                }

                updateNoteStatus(textarea, 'error', message);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */
    $('#filter_order_status, #filter_payment_status, #filter_courier_service, #filter_fake_status, #filter_employee, #filter_date_from, #filter_date_to').on('change', function () {
        reloadTable(1);
    });

    $('#btnFilter').on('click', function () {
        reloadTable(1);
    });

    let typeTimer;

    $('#table_search').on('keyup', function () {
        clearTimeout(typeTimer);

        typeTimer = setTimeout(function () {
            reloadTable(1);
        }, 500);
    });

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.pagination a', function (e) {
        e.preventDefault();

        let page = $(this).attr('href').split('page=')[1];
        reloadTable(page);
    });

    /*
    |--------------------------------------------------------------------------
    | Trash Toggle
    |--------------------------------------------------------------------------
    */
    $('#btnToggleTrash').on('click', function () {
        currentView = currentView === 'active' ? 'trash' : 'active';
        reloadTable(1);
    });

    /*
    |--------------------------------------------------------------------------
    | Checkbox
    |--------------------------------------------------------------------------
    */
    $(document).on('change', '#check_all', function () {
        $('.row-checkbox').prop('checked', $(this).prop('checked'));
        updateSelectedCount();
    });

    $(document).on('change', '.row-checkbox', function () {
        updateSelectedCount();

        let total = $('.row-checkbox').length;
        let checked = $('.row-checkbox:checked').length;

        $('#check_all').prop('checked', total > 0 && total === checked);
    });

    /*
    |--------------------------------------------------------------------------
    | Bulk Delete
    |--------------------------------------------------------------------------
    */
    $('#btnDeleteSelected').on('click', function () {
        runBulkAction('delete');
    });

    /*
    |--------------------------------------------------------------------------
    | Bulk Status Change
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.bulk-status-action', function (e) {
        e.preventDefault();

        let action = $(this).data('action');
        runBulkAction(action);
    });

    /*
    |--------------------------------------------------------------------------
    | Selected Invoice Print
    |--------------------------------------------------------------------------
    */
    $('#btnPrintSelectedInvoice').on('click', function () {
        let ids = selectedIds();

        if (!ids.length) {
            Swal.fire('Notice', 'Please select at least one order.', 'info');
            return;
        }

        let form = $('<form>', {
            method: 'POST',
            action: "{{ route('admin.orders.selected_invoices') }}",
            target: '_blank'
        });

        form.append($('<input>', {
            type: 'hidden',
            name: '_token',
            value: '{{ csrf_token() }}'
        }));

        ids.forEach(function (id) {
            form.append($('<input>', {
                type: 'hidden',
                name: 'ids[]',
                value: id
            }));
        });

        $('body').append(form);
        form.submit();
        form.remove();
    });

    /*
    |--------------------------------------------------------------------------
    | Send Selected Orders To SteadFast
    |--------------------------------------------------------------------------
    */
    $('#btnSendSelectedSteadfast').on('click', function () {
        let ids = selectedIds();

        if (!ids.length) {
            Swal.fire('Notice', 'Please select at least one order.', 'info');
            return;
        }

        Swal.fire({
            title: 'Assign & send selected orders to SteadFast?',
            text: `Selected orders: ${ids.length}. No Courier orders will be auto assigned to SteadFast before sending.`,
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Assign & Send',
            confirmButtonColor: '#2563eb'
        }).then((result) => {
            if (swalConfirmed(result)) {
                $.ajax({
                    url: "{{ route('admin.orders.send_steadfast_bulk') }}",
                    type: "POST",
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    beforeSend: function () {
                        $('#content-wrapper').css('opacity', '0.6');
                    },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Orders sent to SteadFast successfully.');
                            reloadTable();
                        } else {
                            $('#content-wrapper').css('opacity', '1');
                            showToast('error', res.message || 'SteadFast send failed.');
                        }
                    },
                    error: function (xhr) {
                        $('#content-wrapper').css('opacity', '1');

                        let message = xhr.responseJSON?.message || 'SteadFast send failed.';

                        if (xhr.status === 422 && xhr.responseJSON?.errors) {
                            message = Object.values(xhr.responseJSON.errors)[0][0];
                        }

                        showToast('error', message);
                    }
                });
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Send Selected Orders To Pathao
    |--------------------------------------------------------------------------
    */
    $('#btnSendSelectedPathao').on('click', function () {
        let ids = selectedIds();

        if (!ids.length) {
            Swal.fire('Notice', 'Please select at least one order.', 'info');
            return;
        }

        Swal.fire({
            title: 'Assign & send selected orders to Pathao?',
            text: `Selected orders: ${ids.length}. No Courier orders will be auto assigned to Pathao before sending.`,
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Assign & Send',
            confirmButtonColor: '#16a34a'
        }).then((result) => {
            if (swalConfirmed(result)) {
                $.ajax({
                    url: "{{ route('admin.orders.send_pathao_bulk') }}",
                    type: "POST",
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    beforeSend: function () {
                        $('#content-wrapper').css('opacity', '0.6');
                    },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Orders sent to Pathao successfully.');
                            reloadTable();
                        } else {
                            $('#content-wrapper').css('opacity', '1');
                            showToast('error', res.message || 'Pathao send failed.');
                        }
                    },
                    error: function (xhr) {
                        $('#content-wrapper').css('opacity', '1');

                        let message = xhr.responseJSON?.message || 'Pathao send failed.';

                        if (xhr.status === 422 && xhr.responseJSON?.errors) {
                            message = Object.values(xhr.responseJSON.errors)[0][0];
                        }

                        showToast('error', message);
                    }
                });
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Send Single Order To SteadFast
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btnSendSteadfast', function () {
        let button = $(this);
        let url = button.data('url');
        let oldButtonHtml = button.html();

        Swal.fire({
            title: 'Send this order to SteadFast?',
            text: 'A new SteadFast consignment will be created.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send',
            confirmButtonColor: '#2563eb'
        }).then((result) => {
            if (swalConfirmed(result)) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    beforeSend: function () {
                        $('#content-wrapper').css('opacity', '0.6');
                    },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Order sent to SteadFast.');
                            reloadTable();
                        } else {
                            $('#content-wrapper').css('opacity', '1');
                            button.prop('disabled', false).html(oldButtonHtml);
                            showToast('error', res.message || 'SteadFast send failed.');
                        }
                    },
                    error: function (xhr) {
                        $('#content-wrapper').css('opacity', '1');
                        button.prop('disabled', false).html(oldButtonHtml);

                        let message = xhr.responseJSON?.message || 'SteadFast send failed.';
                        showToast('error', message);
                    }
                });
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Send Single Order To Pathao
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btnSendPathao', function () {
        let button = $(this);
        let url = button.data('url');
        let oldButtonHtml = button.html();

        Swal.fire({
            title: 'Send this order to Pathao?',
            text: 'A new Pathao order will be created.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send',
            confirmButtonColor: '#16a34a'
        }).then((result) => {
            if (swalConfirmed(result)) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    beforeSend: function () {
                        $('#content-wrapper').css('opacity', '0.6');
                    },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Order sent to Pathao.');
                            reloadTable();
                        } else {
                            $('#content-wrapper').css('opacity', '1');
                            button.prop('disabled', false).html(oldButtonHtml);
                            showToast('error', res.message || 'Pathao send failed.');
                        }
                    },
                    error: function (xhr) {
                        $('#content-wrapper').css('opacity', '1');
                        button.prop('disabled', false).html(oldButtonHtml);

                        let message = xhr.responseJSON?.message || 'Pathao send failed.';
                        showToast('error', message);
                    }
                });
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Sync Single SteadFast Status
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btnSyncSteadfast', function () {
        let url = $(this).data('url');

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            beforeSend: function () {
                $('#content-wrapper').css('opacity', '0.6');
            },
            success: function (res) {
                if (res.status) {
                    showToast('success', res.message || 'SteadFast status synced.');
                    reloadTable();
                } else {
                    $('#content-wrapper').css('opacity', '1');
                    showToast('error', res.message || 'SteadFast sync failed.');
                }
            },
            error: function (xhr) {
                $('#content-wrapper').css('opacity', '1');

                let message = xhr.responseJSON?.message || 'SteadFast sync failed.';
                showToast('error', message);
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | SteadFast Balance Check
    |--------------------------------------------------------------------------
    */
    $('#btnSteadfastBalance').on('click', function () {
        $.ajax({
            url: "{{ route('admin.orders.steadfast.balance') }}",
            type: "GET",
            success: function (res) {
                if (res.status) {
                    let balance = res.data.current_balance
                        ?? res.data.balance
                        ?? res.data.data
                        ?? JSON.stringify(res.data);

                    Swal.fire({
                        icon: 'success',
                        type: 'success',
                        title: 'SteadFast Balance',
                        text: 'Balance: ' + balance
                    });
                } else {
                    showToast('error', res.message || 'Balance fetch failed.');
                }
            },
            error: function (xhr) {
                let message = xhr.responseJSON?.message || 'Balance fetch failed.';
                showToast('error', message);
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Note Auto Save
    |--------------------------------------------------------------------------
    */
    $(document).on('input', '.admin-note-input', function () {
        let textarea = $(this);
        let orderId = textarea.data('order-id');

        clearTimeout(adminNoteTimers[orderId]);

        updateNoteStatus(textarea, 'saving', 'Typing...');

        adminNoteTimers[orderId] = setTimeout(function () {
            saveAdminNote(textarea);
        }, 800);
    });

    $(document).on('blur', '.admin-note-input', function () {
        let textarea = $(this);
        let orderId = textarea.data('order-id');

        clearTimeout(adminNoteTimers[orderId]);
        saveAdminNote(textarea);
    });

    /*
    |--------------------------------------------------------------------------
    | Single Delete / Restore / Force Delete
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btnDelete, .btnRestore, .btnForceDelete', function () {
        let url = $(this).data('url');
        let isRestore = $(this).hasClass('btnRestore');
        let isForce = $(this).hasClass('btnForceDelete');

        Swal.fire({
            title: isRestore ? 'Restore order?' : (isForce ? 'Purge forever?' : 'Move to trash?'),
            text: isForce ? 'This action cannot be undone!' : 'You can recover this later.',
            icon: isRestore ? 'question' : 'warning',
            type: isRestore ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Proceed',
            confirmButtonColor: isRestore ? '#28a745' : '#dc3545'
        }).then((result) => {
            if (swalConfirmed(result)) {
                $.ajax({
                    url: url,
                    type: isRestore ? 'POST' : 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (res) {
                        if (res.status) {
                            reloadTable();
                            showToast('success', res.message);
                        } else {
                            showToast('error', res.message || 'Action failed.');
                        }
                    },
                    error: function (xhr) {
                        let message = xhr.responseJSON?.message || 'Action failed.';
                        showToast('error', message);
                    }
                });
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Assign Unassigned
    |--------------------------------------------------------------------------
    */
    $('#btnAssignUnassigned, #btnAssignUnassignedTwo').on('click', function () {
        Swal.fire({
            title: 'Assign unassigned orders?',
            text: 'All unassigned orders will be distributed to active employees.',
            icon: 'question',
            type: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, assign now'
        }).then((result) => {
            if (swalConfirmed(result)) {
                $.ajax({
                    url: "{{ route('admin.orders.assign_unassigned') }}",
                    type: "POST",
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (res) {
                        if (res.status) {
                            reloadTable();
                            showToast('success', res.message);
                        } else {
                            showToast('error', res.message || 'Assignment failed.');
                        }
                    },
                    error: function (xhr) {
                        let message = xhr.responseJSON?.message || 'Assignment failed.';
                        showToast('error', message);
                    }
                });
            }
        });
    });

    updateUIState();
    updateSelectedCount();
});
</script>
@endsection

@section('css')
<style>
.badge-primary-soft {
    background-color: #eef2ff;
    color: #4338ca;
}

.shadow-xs {
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.cursor-pointer {
    cursor: pointer;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: ">";
}

.swal2-container {
    z-index: 999999 !important;
}

.order-stat-card {
    background: #ffffff;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    padding: 13px 15px;
    min-height: 82px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #111827;
    transition: .2s;
}

.order-stat-card:hover {
    transform: translateY(-2px);
    color: #111827;
    box-shadow: 0 8px 18px rgba(0,0,0,0.08);
}

.order-stat-card h4 {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
}

.order-stat-card p {
    margin: 3px 0 0;
    font-size: 12px;
    color: #6b7280;
}

.order-stat-card i {
    font-size: 26px;
    color: #2563eb;
    opacity: .8;
}

.dropdown-menu {
    z-index: 9999;
}
</style>
@endsection