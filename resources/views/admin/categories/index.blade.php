@extends('adminlte::page')

@section('title', $title ?? 'Category Management')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Category Management' }}</h1>

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
                        <i class="fas fa-list mr-2 text-primary"></i>
                        {{ $title ?? 'Category Management' }}

                        <span id="view-label" class="badge badge-primary-soft ml-2 border">
                            {{ isset($isTrash) && $isTrash ? 'Trash Bin' : 'Active List' }}
                        </span>
                    </h5>
                </div>

                <div class="col-md-6 mt-2 mt-md-0 text-md-right">
                    <button class="btn btn-outline-danger btn-sm px-3 shadow-none" id="btnToggleTrash">
                        <i class="fas fa-trash-alt mr-1"></i> Trash Bin
                    </button>

                    <button class="btn btn-primary btn-sm px-4 shadow-sm ml-2" id="btnAddCategory" style="border-radius: 8px;">
                        <i class="fas fa-plus-circle mr-1"></i> Add Category
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            {{-- Filter --}}
            <div class="px-4 py-3 border-top bg-white">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Status</label>
                        <select id="filter_status" class="form-control border-0 bg-light shadow-none">
                            <option value="all">All Status</option>
                            <option value="1">Active Only</option>
                            <option value="0">Inactive Only</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Front View</label>
                        <select id="filter_front_view" class="form-control border-0 bg-light shadow-none">
                            <option value="all">All Categories</option>
                            <option value="1">Front View Only</option>
                            <option value="0">Not Front View</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-sm-8 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Search</label>
                        <input type="text"
                               id="table_search"
                               class="form-control shadow-none"
                               placeholder="Search category name or slug...">
                    </div>

                    <div class="col-md-2 col-sm-4 mb-2 d-flex align-items-end">
                        <button class="btn btn-dark btn-block shadow-none" id="btnFilter">
                            <i class="fas fa-search mr-1"></i> Filter
                        </button>
                    </div>
                </div>
            </div>

            {{-- Bulk Action --}}
            <div class="px-4 py-2 bg-light d-flex align-items-center border-top border-bottom flex-wrap">
                <div class="custom-control custom-checkbox mr-3">
                    <span class="small text-muted font-weight-bold">SELECT ALL</span>
                </div>

                <select id="bulk_action" class="form-control form-control-sm w-auto mr-2 shadow-none border-0 font-weight-bold text-muted bg-transparent">
                    <option value="">Bulk Actions</option>

                    <option value="delete" class="opt-active text-danger">Move to Trash</option>
                    <option value="active" class="opt-active text-success">Set Active</option>
                    <option value="inactive" class="opt-active text-warning">Set Inactive</option>
                    <option value="front_view" class="opt-active">Mark Front View</option>
                    <option value="remove_front_view" class="opt-active">Remove Front View</option>

                    <option value="restore" class="opt-trash d-none text-success">Restore Selected</option>
                    <option value="force_delete" class="opt-trash d-none text-danger">Purge Permanently</option>
                </select>

                <button class="btn btn-primary btn-sm px-3 shadow-none" id="btnApplyBulk" style="border-radius: 5px; font-size: 11px;">
                    APPLY
                </button>
            </div>

            {{-- Table --}}
            <div id="content-wrapper" style="min-height: 400px; position: relative;">
                @include('admin.categories.partials.table', [
                    'categories' => $categories,
                    'isTrash' => $isTrash ?? false,
                ])
            </div>
        </div>
    </div>

    {{-- AJAX Modal --}}
    <div class="modal fade" id="ajaxModal" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title font-weight-bold text-primary">Category Configuration</h6>
                    <button type="button" class="close px-4 outline-none" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body p-4" id="modal-body"></div>
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
                    ? "{{ route('admin.categories.trashed') }}"
                    : "{{ route('admin.categories.index') }}";
            }

            function getQueryParams(page = 1) {
                return {
                    page: page,
                    search: $('#table_search').val(),
                    status: $('#filter_status').val(),
                    front_view: $('#filter_front_view').val()
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
                            showToast('error', 'Failed to fetch categories.');
                        }
                    },
                    error: function () {
                        $('#content-wrapper').css('opacity', '1');
                        showToast('error', 'Failed to fetch categories.');
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

                    $('#btnAddCategory').addClass('d-none');
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

                    $('#btnAddCategory').removeClass('d-none');
                }
            }

            $('#filter_status, #filter_front_view').on('change', function () {
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

            $('#btnAddCategory').on('click', function () {
                $.ajax({
                    url: "{{ route('admin.categories.create') }}",
                    type: "GET",
                    dataType: "json",
                    beforeSend: function () {
                        $('#btnAddCategory').prop('disabled', true);
                    },
                    complete: function () {
                        $('#btnAddCategory').prop('disabled', false);
                    },
                    success: function (res) {
                        if (res.status && res.html) {
                            $('#modal-body').html(res.html);
                            $('#ajaxModal').modal('show');
                        } else {
                            Swal.fire('Error', 'Category form could not be loaded.', 'error');
                        }
                    },
                    error: function (xhr) {
                        let message = 'Category form load failed.';

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        Swal.fire('Error', message, 'error');
                    }
                });
            });

            $(document).on('click', '.btnEdit', function () {
                let url = $(this).data('url');

                $.ajax({
                    url: url,
                    type: "GET",
                    dataType: "json",
                    success: function (res) {
                        if (res.status && res.html) {
                            $('#modal-body').html(res.html);
                            $('#ajaxModal').modal('show');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Category edit form load failed.', 'error');
                    }
                });
            });

            $(document).on('submit', '#categoryForm', function (e) {
                e.preventDefault();

                let form = $(this);
                let formData = new FormData(this);
                let btn = form.find('button[type="submit"]');

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,

                    beforeSend: function () {
                        btn.prop('disabled', true)
                            .prepend('<i class="fas fa-spinner fa-spin mr-1"></i> ');
                    },

                    complete: function () {
                        btn.prop('disabled', false);
                        btn.find('i.fa-spinner').remove();
                    },

                    success: function (res) {
                        if (res.status) {
                            $('#ajaxModal').modal('hide');
                            reloadTable();
                            showToast('success', res.message);
                        } else {
                            Swal.fire('Error', res.message || 'Category save failed.', 'error');
                        }
                    },

                    error: function (xhr) {
                        let message = 'Validation error.';

                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            message = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        Swal.fire('Error', message, 'error');
                    }
                });
            });

            $(document).on('change', '#check_all', function () {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });

            $(document).on('click', '.btnDelete, .btnRestore, .btnForceDelete', function () {
                let url = $(this).data('url');
                let isRestore = $(this).hasClass('btnRestore');
                let isForce = $(this).hasClass('btnForceDelete');

                Swal.fire({
                    title: isRestore ? 'Restore category?' : (isForce ? 'Purge forever?' : 'Move to trash?'),
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
                            error: function (xhr) {
                                let message = 'Action failed.';

                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    message = xhr.responseJSON.message;
                                }

                                Swal.fire('Error', message, 'error');
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
                    text: `Action: ${action} on ${ids.length} categories.`,
                    icon: 'warning',
                    showCancelButton: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('admin.categories.multiple_action') }}",
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

                                Swal.fire('Error', message, 'error');
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