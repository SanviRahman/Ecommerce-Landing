@extends('adminlte::page')

@section('title', $title ?? 'Reviews')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Customer Reviews' }}</h1>
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
        <div>
            @if(isset($isTrash) && $isTrash)
                <a href="{{ route('admin.reviews.index') }}" class="btn btn-outline-primary btn-sm shadow-none">
                    <i class="fas fa-list mr-1"></i> Active Reviews
                </a>
            @else
                <a href="{{ route('admin.reviews.trashed') }}" class="btn btn-outline-danger btn-sm shadow-none mr-2">
                    <i class="fas fa-trash-alt mr-1"></i> Trash Bin
                </a>
                <button type="button" class="btn btn-primary btn-sm shadow-none btnModal" data-url="{{ route('admin.reviews.create') }}">
                    <i class="fas fa-plus mr-1"></i> Add Review
                </button>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="mb-0 font-weight-bold text-dark">
                <i class="fas fa-star mr-2 text-warning"></i> 
                {{ $title ?? 'Review List' }}
                <span class="badge badge-primary-soft ml-2 border">
                    {{ isset($isTrash) && $isTrash ? 'Trash Bin' : 'Active List' }}
                </span>
            </h5>
        </div>

        <div class="card-body p-0">
            {{-- Filter Area --}}
            <div class="px-4 py-3 border-top bg-white">
                <div class="row">
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Status</label>
                        <select id="filter_status" class="form-control border-0 bg-light shadow-none">
                            <option value="all">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Rating</label>
                        <select id="filter_rating" class="form-control border-0 bg-light shadow-none">
                            <option value="all">All Ratings</option>
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Campaign</label>
                        <select id="filter_campaign" class="form-control border-0 bg-light shadow-none">
                            <option value="all">All Campaigns</option>
                            @foreach($campaigns as $camp)
                                <option value="{{ $camp->id }}">{{ $camp->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase">Search</label>
                        <input type="text" id="table_search" class="form-control shadow-none" placeholder="Name, location, text...">
                    </div>

                    <div class="col-md-2 col-sm-12 mb-2 d-flex align-items-end">
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
                        <option value="active" class="text-success">Mark as Active</option>
                        <option value="inactive" class="text-warning">Mark as Inactive</option>
                        <option disabled>──────────</option>
                        <option value="delete" class="text-danger">Move to Trash</option>
                    @endif
                </select>

                <button class="btn btn-primary btn-sm px-3 shadow-none" id="btnApplyBulk" style="border-radius: 5px; font-size: 11px;">
                    APPLY
                </button>
            </div>

            {{-- Data Table Content --}}
            <div id="content-wrapper" style="min-height: 300px; position: relative;">
                @include('admin.reviews.partials.table', ['reviews' => $reviews, 'isTrash' => $isTrash ?? false])
            </div>
        </div>
    </div>

    {{-- Universal AJAX Modal --}}
    <div class="modal fade" id="ajaxModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
@endsection

@section('plugins.Sweetalert2', true)

@section('css')
    <style>
        .badge-primary-soft { background-color: #eef2ff; color: #4338ca; }
        .shadow-xs { box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .cursor-pointer { cursor: pointer; }
        .breadcrumb-item + .breadcrumb-item::before { content: ">"; }
        .bg-light-red { background-color: #fffafa; }
        .btn-white { background: #fff; border: none; transition: 0.2s; }
        .btn-white:hover { background: #f8f9fa; transform: translateY(-1px); }
        .text-orange { color: #ffc107; }
    </style>
@endsection

@section('js')
    <script>
        $(document).ready(function () {
            let currentUrl = window.location.href.split('?')[0];

            function showToast(type, message) {
                Swal.fire({
                    icon: type,
                    title: message,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2200,
                    toast: true
                });
            }

            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

            function getQueryParams(page = 1) {
                return {
                    page: page,
                    search: $('#table_search').val(),
                    status: $('#filter_status').val(),
                    rating: $('#filter_rating').val(),
                    campaign_id: $('#filter_campaign').val(),
                };
            }

            function reloadTable(page = 1) {
                $('#content-wrapper').css('opacity', '0.5');
                $.ajax({
                    url: currentUrl,
                    type: 'GET',
                    data: getQueryParams(page),
                    success: function (res) {
                        if (res.status) {
                            $('#content-wrapper').html(res.html).css('opacity', '1');
                        }
                    }
                });
            }

            $('#filter_status, #filter_rating, #filter_campaign').change(function () { reloadTable(1); });
            $('#btnFilter').click(function () { reloadTable(1); });

            let typeTimer;
            $('#table_search').keyup(function () {
                clearTimeout(typeTimer);
                typeTimer = setTimeout(() => reloadTable(1), 500);
            });

            $(document).on('click', '.pagination a', function (e) {
                e.preventDefault();
                let page = $(this).attr('href').split('page=')[1];
                reloadTable(page);
            });

            $(document).on('change', '#check_all', function () {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Open AJAX Modal
            $(document).on('click', '.btnModal', function (e) {
                e.preventDefault();
                let url = $(this).data('url') || $(this).attr('href');
                
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function (res) {
                        if (res.status) {
                            $('#ajaxModal .modal-content').html(res.html);
                            $('#ajaxModal').modal('show');
                        }
                    }
                });
            });

            // Submit AJAX Form (with Image Upload Support)
            $(document).on('submit', '#ajaxForm', function (e) {
                e.preventDefault();
                
                let form = $(this)[0];
                let formData = new FormData(form);
                let url = $(this).attr('action');
                let btn = $(this).find('button[type="submit"]');
                let originalText = btn.html();

                btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').remove();

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        if (res.status) {
                            $('#ajaxModal').modal('hide');
                            reloadTable();
                            showToast('success', res.message);
                        }
                    },
                    error: function (xhr) {
                        btn.html(originalText).prop('disabled', false);
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function (key, value) {
                                let field = $('[name="' + key + '"]');
                                field.addClass('is-invalid');
                                field.after('<span class="invalid-feedback d-block">' + value[0] + '</span>');
                            });
                        } else {
                            showToast('error', xhr.responseJSON.message || 'Something went wrong!');
                        }
                    }
                });
            });

            // Delete / Restore / Force Delete Action
            $(document).on('click', '.btnAction', function () {
                let url = $(this).data('url');
                let actionType = $(this).data('action'); 
                let title = actionType === 'restore' ? 'Restore Review?' : (actionType === 'force_delete' ? 'Delete Permanently?' : 'Move to Trash?');
                let method = actionType === 'restore' ? 'POST' : 'DELETE';

                Swal.fire({
                    title: title,
                    icon: actionType === 'restore' ? 'question' : 'warning',
                    showCancelButton: true,
                    confirmButtonColor: actionType === 'restore' ? '#28a745' : '#d33',
                    confirmButtonText: 'Yes, Proceed'
                }).then((result) => {
                    if (result.value || result.isConfirmed) {
                        $.ajax({
                            url: url,
                            type: 'POST',
                            data: { _method: method, _token: '{{ csrf_token() }}' },
                            success: function (res) {
                                if (res.status) {
                                    reloadTable();
                                    showToast('success', res.message);
                                } else {
                                    Swal.fire('Error', res.message || 'Action failed!', 'error');
                                }
                            },
                            error: function (xhr) {
                                let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Something went wrong!';
                                Swal.fire('Error', msg, 'error');
                            }
                        });
                    }
                });
            });

            // Bulk Actions
            $('#btnApplyBulk').click(function () {
                let action = $('#bulk_action').val();
                let ids = $('.row-checkbox:checked').map(function () { return $(this).val(); }).get();

                if (!ids.length || !action) {
                    Swal.fire('Notice', 'Please select rows and an action.', 'info');
                    return;
                }

                Swal.fire({
                    title: 'Apply Bulk Action?',
                    text: `Action: ${action} on ${ids.length} reviews.`,
                    icon: 'warning',
                    showCancelButton: true
                }).then((result) => {
                    if (result.value || result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('admin.reviews.multiple_action') }}",
                            type: "POST",
                            data: { _token: '{{ csrf_token() }}', ids: ids, action: action },
                            success: function (res) {
                                if (res.status) {
                                    reloadTable();
                                    showToast('success', res.message);
                                } else {
                                    Swal.fire('Error', res.message || 'Bulk action failed!', 'error');
                                }
                            },
                            error: function (xhr) {
                                let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Bulk action failed!';
                                Swal.fire('Error', msg, 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection