@extends('adminlte::page')

@section('title', $title ?? 'Blocked Customers')
@section('plugins.Sweetalert2', true)

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Blocked Customers' }}</h1>
        <ol class="breadcrumb mt-2 mb-0 bg-transparent p-0">
            @foreach($breadcrumb ?? [] as $item)
                <li class="breadcrumb-item">
                    <a href="{{ $item['url'] }}">{{ $item['text'] }}</a>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="mt-2 mt-md-0">
        @if($isTrash ?? false)
            <a href="{{ route('admin.blocked-customers.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-list mr-1"></i> Active List
            </a>
        @else
            <a href="{{ route('admin.blocked-customers.trash') }}" class="btn btn-outline-danger btn-sm mr-2">
                <i class="fas fa-trash-alt mr-1"></i> Trash Bin
            </a>
            <button type="button"
                    class="btn btn-primary btn-sm btn-block-modal"
                    data-url="{{ route('admin.blocked-customers.create') }}">
                <i class="fas fa-user-slash mr-1"></i> Add Block Rule
            </button>
        @endif
    </div>
</div>
@endsection

@section('content')
<div class="card shadow-sm border-0" style="border-radius: 12px;">
    <div class="card-header bg-white border-0 py-3">
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-ban text-danger mr-2"></i>{{ $title ?? 'Blocked Customers' }}
            <span class="badge {{ ($isTrash ?? false) ? 'badge-danger' : 'badge-primary' }} ml-2">
                {{ ($isTrash ?? false) ? 'Trash' : 'Phone / IP Block List' }}
            </span>
        </h5>
    </div>

    <div class="card-body p-0">
        <div class="px-4 py-3 border-top bg-white">
            <div class="row">
                <div class="col-lg-3 col-md-4 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Status</label>
                    <select id="filterStatus" class="form-control shadow-none">
                        <option value="all">All Status</option>
                        <option value="1">Blocked</option>
                        <option value="0">Unblocked</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-4 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Block Type</label>
                    <select id="filterBlockType" class="form-control shadow-none">
                        <option value="all">Phone / IP / Both</option>
                        <option value="phone">Phone Only</option>
                        <option value="ip">IP Only</option>
                        <option value="both">Phone + IP</option>
                    </select>
                </div>

                <div class="col-lg-4 col-md-4 mb-2">
                    <label class="small font-weight-bold text-muted text-uppercase">Search</label>
                    <input type="text"
                           id="blockSearch"
                           class="form-control shadow-none"
                           placeholder="Customer, phone, IP, invoice, reason...">
                </div>

                <div class="col-lg-2 col-md-12 mb-2 d-flex align-items-end">
                    <button type="button" class="btn btn-dark btn-block" id="btnBlockFilter">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>

        <div id="blockedCustomerTableWrapper" style="min-height: 300px;">
            @include('admin.blocked-customers.partials.table', [
                'blockedCustomers' => $blockedCustomers,
                'isTrash' => $isTrash ?? false,
            ])
        </div>
    </div>
</div>

<div class="modal fade" id="blockedCustomerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;"></div>
    </div>
</div>
@endsection

@section('css')
<style>
.blocked-customer-table td,
.blocked-customer-table th {
    vertical-align: middle;
}

.blocked-code {
    display: inline-block;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    word-break: break-all;
}

.block-reason {
    max-width: 260px;
    white-space: normal;
}

.btn-white {
    background: #fff;
    border: 0;
}
</style>
@endsection

@section('js')
<script>
$(document).ready(function () {
    const listUrl = @json(url()->current());
    let searchTimer = null;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    function toast(icon, message) {
        Swal.fire({
            icon: icon,
            title: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2400
        });
    }

    function validationMessage(xhr) {
        const errors = xhr.responseJSON && xhr.responseJSON.errors
            ? xhr.responseJSON.errors
            : null;

        if (errors) {
            return Object.values(errors).flat().join('\n');
        }

        return (xhr.responseJSON && xhr.responseJSON.message)
            ? xhr.responseJSON.message
            : 'The operation could not be completed.';
    }

    function queryData(page) {
        return {
            page: page || 1,
            search: $('#blockSearch').val(),
            status: $('#filterStatus').val(),
            block_type: $('#filterBlockType').val()
        };
    }

    function reloadTable(page) {
        $('#blockedCustomerTableWrapper').css('opacity', '0.5');

        $.get(listUrl, queryData(page))
            .done(function (response) {
                if (response.status) {
                    $('#blockedCustomerTableWrapper').html(response.html);
                }
            })
            .always(function () {
                $('#blockedCustomerTableWrapper').css('opacity', '1');
            });
    }

    $('#btnBlockFilter, #filterStatus, #filterBlockType').on('click change', function () {
        reloadTable(1);
    });

    $('#blockSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            reloadTable(1);
        }, 450);
    });

    $(document).on('click', '.pagination a', function (event) {
        event.preventDefault();
        const url = new URL($(this).attr('href'), window.location.origin);
        reloadTable(url.searchParams.get('page') || 1);
    });

    $(document).on('click', '.btn-block-modal', function (event) {
        event.preventDefault();
        const url = $(this).data('url') || $(this).attr('href');

        $.get(url).done(function (response) {
            if (response.status) {
                $('#blockedCustomerModal .modal-content').html(response.html);
                $('#blockedCustomerModal').modal('show');
            }
        });
    });

    $(document).on('submit', '#blockedCustomerAjaxForm', function (event) {
        event.preventDefault();

        const form = $(this);
        const button = form.find('button[type="submit"]');
        const originalText = button.html();

        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
        form.find('.ajax-validation-errors').addClass('d-none').empty();

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize()
        }).done(function (response) {
            $('#blockedCustomerModal').modal('hide');
            toast('success', response.message || 'Saved successfully.');
            reloadTable(1);
        }).fail(function (xhr) {
            form.find('.ajax-validation-errors')
                .removeClass('d-none')
                .text(validationMessage(xhr));
        }).always(function () {
            button.prop('disabled', false).html(originalText);
        });
    });

    $(document).on('click', '.btn-block-action', function () {
        const button = $(this);
        const action = button.data('action');
        const url = button.data('url');
        const isDanger = ['delete', 'force-delete'].includes(action);
        const question = button.data('confirm') || 'Are you sure?';

        Swal.fire({
            icon: isDanger ? 'warning' : 'question',
            title: question,
            showCancelButton: true,
            confirmButtonText: 'Yes, continue',
            confirmButtonColor: isDanger ? '#dc3545' : '#2563eb'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            const request = {
                url: url,
                method: 'POST',
                data: {}
            };

            if (action === 'delete' || action === 'force-delete') {
                request.data._method = 'DELETE';
            }

            if (action === 'activate' || action === 'unblock') {
                request.data._method = 'PATCH';
                request.data.status = action === 'activate' ? 1 : 0;
            }

            $.ajax(request)
                .done(function (response) {
                    toast('success', response.message || 'Updated successfully.');
                    reloadTable(1);
                })
                .fail(function (xhr) {
                    Swal.fire('Error', validationMessage(xhr), 'error');
                });
        });
    });
});
</script>
@endsection
