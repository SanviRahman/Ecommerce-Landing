@extends('adminlte::page')

@section('title', $title ?? 'Landing Page Manage')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Landing Page Manage' }}</h1>

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
                    <i class="fas fa-bullhorn mr-2 text-primary"></i>
                    {{ $title ?? 'Landing Page Manage' }}

                    <span id="view-label" class="badge badge-primary-soft ml-2 border">
                        {{ isset($isTrash) && $isTrash ? 'Trash Bin' : 'Active List' }}
                    </span>
                </h5>
            </div>

            <div class="col-md-6 mt-2 mt-md-0 text-md-right">
                <button class="btn btn-outline-danger btn-sm px-3 shadow-none" id="btnToggleTrash">
                    <i class="fas fa-trash-alt mr-1"></i> Trash Bin
                </button>

                <a href="{{ route('admin.campaigns.create') }}"
                   class="btn btn-primary btn-sm px-4 shadow-sm ml-2"
                   id="btnAddCampaign"
                   style="border-radius: 8px;">
                    <i class="fas fa-plus-circle mr-1"></i> Add Landing Page
                </a>
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

                <div class="col-md-7 col-sm-8 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Search</label>
                    <input type="text"
                           id="table_search"
                           class="form-control shadow-none"
                           placeholder="Search landing page title, slug or description...">
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

            <select id="bulk_action"
                    class="form-control form-control-sm w-auto mr-2 shadow-none border-0 font-weight-bold text-muted bg-transparent">
                <option value="">Bulk Actions</option>

                <option value="delete" class="opt-active text-danger">Move to Trash</option>
                <option value="active" class="opt-active text-success">Set Active</option>
                <option value="inactive" class="opt-active text-warning">Set Inactive</option>

                <option value="restore" class="opt-trash d-none text-success">Restore Selected</option>
                <option value="force_delete" class="opt-trash d-none text-danger">Purge Permanently</option>
            </select>

            <button class="btn btn-primary btn-sm px-3 shadow-none"
                    id="btnApplyBulk"
                    style="border-radius: 5px; font-size: 11px;">
                APPLY
            </button>
        </div>

        {{-- Table --}}
        <div id="content-wrapper" style="min-height: 400px; position: relative;">
            @include('admin.campaigns.partials.table', [
                'campaigns' => $campaigns,
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
$(document).ready(function() {
    let currentView = "{{ isset($isTrash) && $isTrash ? 'trash' : 'active' }}";

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
            ? "{{ route('admin.campaigns.trashed') }}"
            : "{{ route('admin.campaigns.index') }}";
    }

    function getQueryParams(page = 1) {
        return {
            page: page,
            search: $('#table_search').val(),
            status: $('#filter_status').val()
        };
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
                    updateUIState();
                } else {
                    $('#content-wrapper').css('opacity', '1');
                    showToast('error', 'Failed to fetch landing pages.');
                }
            },
            error: function() {
                $('#content-wrapper').css('opacity', '1');
                showToast('error', 'Failed to fetch landing pages.');
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

            $('#btnAddCampaign').addClass('d-none');
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

            $('#btnAddCampaign').removeClass('d-none');
        }
    }

    $('#filter_status').on('change', function() {
        reloadTable(1);
    });

    $('#btnFilter').on('click', function() {
        reloadTable(1);
    });

    let typeTimer;
    $('#table_search').on('keyup', function() {
        clearTimeout(typeTimer);

        typeTimer = setTimeout(function() {
            reloadTable(1);
        }, 500);
    });

    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();

        let page = $(this).attr('href').split('page=')[1];
        reloadTable(page);
    });

    $('#btnToggleTrash').on('click', function() {
        currentView = currentView === 'active' ? 'trash' : 'active';
        reloadTable(1);
    });

    $(document).on('change', '#check_all', function() {
        $('.row-checkbox').prop('checked', $(this).prop('checked'));
    });

    $(document).on('change', '.row-checkbox', function() {
        let total = $('.row-checkbox').length;
        let checked = $('.row-checkbox:checked').length;

        $('#check_all').prop('checked', total > 0 && total === checked);
    });

    $(document).on('click', '.btnDelete, .btnRestore, .btnForceDelete', function() {
        let url = $(this).data('url');
        let isRestore = $(this).hasClass('btnRestore');
        let isForce = $(this).hasClass('btnForceDelete');

        Swal.fire({
            title: isRestore ? 'Restore landing page?' : (isForce ? 'Purge forever?' : 'Move to trash?'),
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
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        if (res.status) {
                            reloadTable();
                            showToast('success', res.message);
                        } else {
                            showToast('error', res.message || 'Action failed.');
                        }
                    },
                    error: function(xhr) {
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

    $('#btnApplyBulk').on('click', function() {
        let action = $('#bulk_action').val();
        let ids = $('.row-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (!ids.length || !action) {
            Swal.fire('Notice', 'Please select rows and an action.', 'info');
            return;
        }

        Swal.fire({
            title: 'Apply Bulk Action?',
            text: `Action: ${action} on ${ids.length} landing pages.`,
            icon: 'warning',
            type: 'warning',
            showCancelButton: true
        }).then((result) => {
            if (result.isConfirmed || result.value) {
                $.ajax({
                    url: "{{ route('admin.campaigns.multiple_action') }}",
                    type: "POST",
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids,
                        action: action
                    },
                    success: function(res) {
                        if (res.status) {
                            reloadTable();
                            showToast('success', res.message);
                            $('#bulk_action').val('');
                        } else {
                            showToast('error', res.message || 'Bulk action failed.');
                        }
                    },
                    error: function(xhr) {
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
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.cursor-pointer {
    cursor: pointer;
}

.breadcrumb-item+.breadcrumb-item::before {
    content: ">";
}

.swal2-container {
    z-index: 999999 !important;
}
</style>
@endsection