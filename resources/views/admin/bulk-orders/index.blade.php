@extends('adminlte::page')

@section('title', $title ?? 'Bulk Orders')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Bulk Orders' }}</h1>

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

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-header bg-white py-3 border-0">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0 font-weight-bold text-dark">
                        <i class="fas fa-boxes mr-2 text-primary"></i>
                        {{ $title ?? 'Bulk Orders' }}

                        <span id="view-label" class="badge badge-primary-soft ml-2 border">
                            {{ isset($isTrash) && $isTrash ? 'Trash Bin' : 'Active List' }}
                        </span>
                    </h5>
                </div>

                <div class="col-md-6 mt-2 mt-md-0 text-md-right">
                    @if($viewType !== 'trashed')
                        <button class="btn btn-outline-danger btn-sm px-3 shadow-none" id="btnToggleTrash">
                            <i class="fas fa-trash-alt mr-1"></i> Trash Bin
                        </button>
                    @else
                        <a href="{{ route('admin.bulk-orders.index') }}" class="btn btn-outline-primary btn-sm px-3 shadow-none">
                            <i class="fas fa-list mr-1"></i> Active List
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            {{-- Filter Area --}}
            <div class="px-4 py-3 border-top bg-white">
                <div class="row">
                    {{-- Only show status filter if we are on the main 'index' page or 'trash' page --}}
                    @if(in_array($viewType, ['index', 'trashed']))
                        <div class="col-md-3 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted text-uppercase">Status</label>
                            <select id="filter_status" class="form-control border-0 bg-light shadow-none">
                                <option value="all">All Statuses</option>
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="quoted">Quoted</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    @endif

                    <div class="col-md-{{ in_array($viewType, ['index', 'trashed']) ? '7' : '10' }} col-sm-12 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Search</label>
                        <input type="text" id="table_search" class="form-control shadow-none" placeholder="Search customer, phone, company, product...">
                    </div>

                    <div class="col-md-2 col-sm-6 mb-2 d-flex align-items-end">
                        <button class="btn btn-dark btn-block shadow-none" id="btnFilter">
                            <i class="fas fa-search mr-1"></i> Filter
                        </button>
                    </div>
                </div>
            </div>

            {{-- Bulk Action Area --}}
            <div class="px-4 py-2 bg-light d-flex align-items-center border-top border-bottom flex-wrap">
                <div class="custom-control custom-checkbox mr-3">
                    <span class="small text-muted font-weight-bold">SELECT ALL</span>
                </div>

                <select id="bulk_action" class="form-control form-control-sm w-auto mr-2 shadow-none border-0 font-weight-bold text-muted bg-transparent">
                    <option value="">Bulk Actions</option>

                    @if(isset($isTrash) && $isTrash)
                        <option value="restore" class="text-success">Restore Selected</option>
                        <option value="force_delete" class="text-danger">Purge Permanently</option>
                    @else
                        <option value="delete" class="text-danger">Move to Trash</option>
                        <option disabled>──────────</option>
                        <option value="status_new" class="text-info">Mark as New</option>
                        <option value="status_contacted" class="text-primary">Mark as Contacted</option>
                        <option value="status_quoted" class="text-warning">Mark as Quoted</option>
                        <option value="status_confirmed" class="text-success">Mark as Confirmed</option>
                        <option value="status_cancelled" class="text-danger">Mark as Cancelled</option>
                    @endif
                </select>

                <button class="btn btn-primary btn-sm px-3 shadow-none" id="btnApplyBulk" style="border-radius: 5px; font-size: 11px;">
                    APPLY
                </button>
            </div>

            {{-- Data Table --}}
            <div id="content-wrapper" style="min-height: 400px; position: relative;">
                @include('admin.bulk-orders.partials.table', [
                    'bulkOrders' => $bulkOrders,
                    'isTrash' => $isTrash ?? false,
                    'viewType' => $viewType ?? 'index',
                ])
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <strong>
        © Copyright 2026 All rights reserved | This website developed by <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
    </strong>
@endsection

@section('plugins.Sweetalert2', true)

@section('js')
    <script>
        $(document).ready(function () {
            // Determine current URL for AJAX calls to keep pagination in context
            let currentUrl = window.location.href.split('?')[0];

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
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            function getQueryParams(page = 1) {
                let params = {
                    page: page,
                    search: $('#table_search').val(),
                };
                // Only add status if the element exists on this page
                if ($('#filter_status').length) {
                    params.status = $('#filter_status').val();
                }
                return params;
            }

            function reloadTable(page = 1) {
                $('#content-wrapper').css('opacity', '0.6');

                $.ajax({
                    url: currentUrl,
                    type: 'GET',
                    data: getQueryParams(page),
                    success: function (res) {
                        if (res.status && res.html) {
                            $('#content-wrapper').html(res.html).css('opacity', '1');
                        } else {
                            $('#content-wrapper').css('opacity', '1');
                            showToast('error', 'Failed to fetch bulk orders.');
                        }
                    },
                    error: function () {
                        $('#content-wrapper').css('opacity', '1');
                        showToast('error', 'Failed to fetch bulk orders.');
                    }
                });
            }

            $('#filter_status').on('change', function () { reloadTable(1); });
            $('#btnFilter').on('click', function () { reloadTable(1); });

            let typeTimer;
            $('#table_search').on('keyup', function () {
                clearTimeout(typeTimer);
                typeTimer = setTimeout(function () { reloadTable(1); }, 500);
            });

            $(document).on('click', '.pagination a', function (e) {
                e.preventDefault();
                let page = $(this).attr('href').split('page=')[1];
                reloadTable(page);
            });

            $('#btnToggleTrash').on('click', function () {
                window.location.href = "{{ route('admin.bulk-orders.trashed') }}";
            });

            // Check All
            $(document).on('change', '#check_all', function () {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Delete / Restore / Force Delete Action
            $(document).on('click', '.btnDelete, .btnRestore, .btnForceDelete', function () {
                let url = $(this).data('url');
                let isRestore = $(this).hasClass('btnRestore');
                let isForce = $(this).hasClass('btnForceDelete');

                Swal.fire({
                    title: isRestore ? 'Restore request?' : (isForce ? 'Purge forever?' : 'Move to trash?'),
                    text: isForce ? 'This action cannot be undone!' : 'You can recover this later.',
                    icon: isRestore ? 'question' : 'warning',
                    type: isRestore ? 'question' : 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Proceed',
                    confirmButtonColor: isRestore ? '#28a745' : '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed || result.value) {
                        $.ajax({
                            url: url,
                            type: isRestore ? 'POST' : 'DELETE',
                            data: { _token: '{{ csrf_token() }}' },
                            success: function (res) {
                                if (res.status) {
                                    reloadTable();
                                    showToast('success', res.message);
                                } else {
                                    showToast('error', res.message || 'Action failed.');
                                }
                            },
                            error: function (xhr) {
                                let message = 'Action failed.';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    message = xhr.responseJSON.message;
                                }
                                showToast('error', message);
                            }
                        });
                    }
                });
            });

            // Bulk Action Apply
            $('#btnApplyBulk').on('click', function () {
                let action = $('#bulk_action').val();
                let ids = $('.row-checkbox:checked').map(function () { return $(this).val(); }).get();

                if (!ids.length || !action) {
                    Swal.fire('Notice', 'Please select rows and an action.', 'info');
                    return;
                }

                Swal.fire({
                    title: 'Apply Bulk Action?',
                    text: `Action: ${action} on ${ids.length} requests.`,
                    icon: 'warning',
                    showCancelButton: true
                }).then((result) => {
                    if (result.isConfirmed || result.value) {
                        $.ajax({
                            url: "{{ route('admin.bulk-orders.multiple_action') }}",
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
        });
    </script>
@endsection

@section('css')
    <style>
        .badge-primary-soft { background-color: #eef2ff; color: #4338ca; }
        .shadow-xs { box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .cursor-pointer { cursor: pointer; }
        .breadcrumb-item + .breadcrumb-item::before { content: ">"; }
        .swal2-container { z-index: 999999 !important; }
        .bg-light-red { background-color: #fffafa; }
        .btn-white { background: #fff; border: none; transition: 0.2s; }
        .btn-white:hover { background: #f8f9fa; transform: translateY(-1px); }
    </style>
@endsection