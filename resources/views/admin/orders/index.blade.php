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

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        {{-- Header --}}
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

                    <div class="col-md-10 col-sm-8 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Search</label>
                        <input type="text"
                               id="table_search"
                               class="form-control shadow-none"
                               placeholder="Search invoice, customer, phone, address, employee...">
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
                <div class="px-4 py-2 bg-light d-flex align-items-center border-top border-bottom flex-wrap">
                    <div class="custom-control custom-checkbox mr-3">
                        <span class="small text-muted font-weight-bold">SELECT ALL</span>
                    </div>

                    <select id="bulk_action" class="form-control form-control-sm w-auto mr-2 shadow-none border-0 font-weight-bold text-muted bg-transparent">
                        <option value="">Bulk Actions</option>

                        <option value="delete" class="opt-active text-danger">Move to Trash</option>
                        <option value="mark_fake" class="opt-active text-danger">Mark Fake</option>
                        <option value="restore_fake" class="opt-active text-success">Restore Fake</option>

                        <option value="restore" class="opt-trash d-none text-success">Restore Selected</option>
                        <option value="force_delete" class="opt-trash d-none text-danger">Purge Permanently</option>
                    </select>

                    <button class="btn btn-primary btn-sm px-3 shadow-none" id="btnApplyBulk" style="border-radius: 5px; font-size: 11px;">
                        APPLY
                    </button>
                </div>
            @endif

            {{-- Table Content Wrapper --}}
            <div id="content-wrapper" style="min-height: 400px; position: relative;">
                @include('admin.orders.partials.table', [
                    'orders' => $orders,
                    'isTrash' => $isTrash ?? false,
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

            function showToast(type, message) {
                Swal.fire({
                    icon: type,
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
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
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
                    fake_status: $('#filter_fake_status').val(),
                    assigned_employee_id: $('#filter_employee').val(),
                    date_from: $('#filter_date_from').val(),
                    date_to: $('#filter_date_to').val()
                };
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
                            updateUIState();
                        } else {
                            $('#content-wrapper').css('opacity', '1');
                            showToast('error', 'Failed to fetch orders.');
                        }
                    },
                    error: function () {
                        $('#content-wrapper').css('opacity', '1');
                        showToast('error', 'Failed to fetch orders.');
                    }
                });
            }

            function updateUIState() {
                if (currentView === 'trash') {
                    $('.opt-active').addClass('d-none');
                    $('.opt-trash').removeClass('d-none');

                    $('#view-label')
                        .text('Trash Bin')
                        .attr('class', 'badge badge-danger ml-2 border');

                    $('#btnToggleTrash')
                        .html('<i class="fas fa-list mr-1"></i> Active List')
                        .removeClass('btn-outline-danger')
                        .addClass('btn-outline-primary');
                } else {
                    $('.opt-trash').addClass('d-none');
                    $('.opt-active').removeClass('d-none');

                    $('#view-label')
                        .text('Active List')
                        .attr('class', 'badge badge-primary-soft ml-2 border');

                    $('#btnToggleTrash')
                        .html('<i class="fas fa-trash-alt mr-1"></i> Trash Bin')
                        .removeClass('btn-outline-primary')
                        .addClass('btn-outline-danger');
                }
            }

            $('#filter_order_status, #filter_payment_status, #filter_fake_status, #filter_employee, #filter_date_from, #filter_date_to').on('change', function () {
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

            $(document).on('click', '.pagination a', function (e) {
                e.preventDefault();

                let page = $(this).attr('href').split('page=')[1];
                reloadTable(page);
            });

            $('#btnToggleTrash').on('click', function () {
                currentView = currentView === 'active' ? 'trash' : 'active';
                reloadTable(1);
            });

            $(document).on('change', '#check_all', function () {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });

            $(document).on('click', '.btnDelete, .btnRestore, .btnForceDelete', function () {
                let url = $(this).data('url');
                let isRestore = $(this).hasClass('btnRestore');
                let isForce = $(this).hasClass('btnForceDelete');

                Swal.fire({
                    title: isRestore ? 'Restore order?' : (isForce ? 'Purge forever?' : 'Move to trash?'),
                    text: isForce ? 'This action cannot be undone!' : 'You can recover this later.',
                    icon: isRestore ? 'question' : 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Proceed',
                    confirmButtonColor: isRestore ? '#28a745' : '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed) {
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
                            error: function () {
                                showToast('error', 'Action failed.');
                            }
                        });
                    }
                });
            });

            $('#btnApplyBulk').on('click', function () {
                let action = $('#bulk_action').val();
                let ids = $('.row-checkbox:checked').map(function () {
                    return $(this).val();
                }).get();

                if (!ids.length || !action) {
                    Swal.fire('Notice', 'Please select rows and an action.', 'info');
                    return;
                }

                Swal.fire({
                    title: 'Apply Bulk Action?',
                    text: `Action: ${action} on ${ids.length} orders.`,
                    icon: 'warning',
                    showCancelButton: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('admin.orders.multiple_action') }}",
                            type: "POST",
                            data: {
                                _token: '{{ csrf_token() }}',
                                ids: ids,
                                action: action
                            },
                            success: function (res) {
                                if (res.status) {
                                    reloadTable();
                                    showToast('success', res.message);
                                } else {
                                    showToast('error', res.message || 'Bulk action failed.');
                                }
                            },
                            error: function (xhr) {
                                let message = 'Bulk action failed.';

                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    message = xhr.responseJSON.message;
                                }

                                showToast('error', message);
                            }
                        });
                    }
                });
            });

            $('#btnAssignUnassigned').on('click', function () {
                Swal.fire({
                    title: 'Assign unassigned orders?',
                    text: 'All unassigned orders will be distributed to active employees.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, assign now'
                }).then((result) => {
                    if (result.isConfirmed) {
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
                            error: function () {
                                showToast('error', 'Assignment failed.');
                            }
                        });
                    }
                });
            });

            updateUIState();
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
    </style>
@endsection