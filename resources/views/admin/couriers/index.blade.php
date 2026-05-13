@extends('adminlte::page')

@section('title', $title ?? 'Courier Manage')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Courier Manage' }}</h1>

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

        <button type="button" class="btn btn-primary btn-sm px-3" data-toggle="modal" data-target="#courierCreateModal">
            <i class="fas fa-plus mr-1"></i>
            Add New Courier
        </button>
    </div>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-1"></i>
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>Please fix the following errors:</strong>

        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>

        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

<div class="card shadow-sm border-0" style="border-radius: 12px;">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-truck mr-2 text-primary"></i>
            Total {{ $couriers->total() }} Courier
        </h5>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light small text-uppercase text-muted">
                    <tr>
                        <th width="70" class="text-center">SL</th>
                        <th>Courier Name</th>
                        <th>Code</th>
                        <th>Merchant ID</th>
                        <th>Phone Number</th>
                        <th>Status</th>
                        <th width="130" class="text-right pr-4">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($couriers as $courier)
                        <tr>
                            <td class="text-center">
                                {{ $loop->iteration + ($couriers->currentPage() - 1) * $couriers->perPage() }}
                            </td>

                            <td>
                                <div class="font-weight-bold text-dark">
                                    {{ $courier->name }}
                                </div>
                            </td>

                            <td>
                                <span class="badge badge-secondary">
                                    {{ $courier->code }}
                                </span>
                            </td>

                            <td>
                                {{ $courier->merchant_id ?: '-' }}
                            </td>

                            <td>
                                {{ $courier->phone_number ?: '-' }}
                            </td>

                            <td>
                                @if($courier->status)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>

                            <td class="text-right pr-4">
                                <button type="button"
                                        class="btn btn-sm btn-info btnEditCourier"
                                        data-toggle="modal"
                                        data-target="#courierEditModal"
                                        data-update-url="{{ route('admin.couriers.update', $courier->id) }}"
                                        data-name="{{ e($courier->name) }}"
                                        data-code="{{ e($courier->code) }}"
                                        data-merchant-id="{{ e($courier->merchant_id) }}"
                                        data-phone-number="{{ e($courier->phone_number) }}"
                                        data-status="{{ $courier->status ? 1 : 0 }}"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <form action="{{ route('admin.couriers.destroy', $courier->id) }}"
                                      method="POST"
                                      class="d-inline courier-delete-form">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-truck fa-2x mb-2"></i>
                                <div>No courier found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($couriers->hasPages())
            <div class="px-4 py-3 border-top">
                {{ $couriers->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="courierCreateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('admin.couriers.store') }}" method="POST" class="modal-content">
            @csrf

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle mr-1"></i>
                    Add New Courier
                </h5>

                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label>Courier Name <span class="text-danger">*</span></label>
                    <input type="text"
                           name="name"
                           id="create_name"
                           class="form-control"
                           placeholder="Example: RedX Courier"
                           required>
                </div>

                <div class="form-group">
                    <label>Courier Code</label>
                    <input type="text"
                           name="code"
                           id="create_code"
                           class="form-control"
                           placeholder="Example: redx">

                    <small class="text-muted">
                        Auto generate হবে। চাইলে custom code দিতে পারো।
                    </small>
                </div>

                <div class="form-group">
                    <label>Merchant ID</label>
                    <input type="text"
                           name="merchant_id"
                           id="create_merchant_id"
                           class="form-control"
                           placeholder="Example: 1001">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text"
                           name="phone_number"
                           id="create_phone_number"
                           class="form-control"
                           placeholder="Example: 017xxxxxxxx">
                </div>

                <div class="custom-control custom-switch">
                    <input type="checkbox"
                           name="status"
                           value="1"
                           class="custom-control-input"
                           id="create_status"
                           checked>

                    <label class="custom-control-label" for="create_status">
                        Active Status
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    Close
                </button>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save mr-1"></i>
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="courierEditModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="#" method="POST" class="modal-content" id="courierEditForm">
            @csrf
            @method('PUT')

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit mr-1"></i>
                    Edit Courier
                </h5>

                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label>Courier Name <span class="text-danger">*</span></label>
                    <input type="text"
                           name="name"
                           id="edit_name"
                           class="form-control"
                           required>
                </div>

                <div class="form-group">
                    <label>Courier Code</label>
                    <input type="text"
                           name="code"
                           id="edit_code"
                           class="form-control">

                    <small class="text-muted">
                        Example: redx, pathao, steadfast, sundarban
                    </small>
                </div>

                <div class="form-group">
                    <label>Merchant ID</label>
                    <input type="text"
                           name="merchant_id"
                           id="edit_merchant_id"
                           class="form-control"
                           placeholder="Example: 1001">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text"
                           name="phone_number"
                           id="edit_phone_number"
                           class="form-control"
                           placeholder="Example: 017xxxxxxxx">
                </div>

                <div class="custom-control custom-switch">
                    <input type="checkbox"
                           name="status"
                           value="1"
                           class="custom-control-input"
                           id="edit_status">

                    <label class="custom-control-label" for="edit_status">
                        Active Status
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    Close
                </button>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save mr-1"></i>
                    Update
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('plugins.Sweetalert2', true)

@section('js')
<script>
$(document).ready(function () {
    function slugifyCourierCode(text) {
        return String(text || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    $('#create_name').on('keyup change', function () {
        const codeInput = $('#create_code');

        if (! codeInput.data('manual')) {
            codeInput.val(slugifyCourierCode($(this).val()));
        }
    });

    $('#create_code').on('keyup change', function () {
        $(this).data('manual', true);
        $(this).val(slugifyCourierCode($(this).val()));
    });

    $('#edit_code').on('keyup change', function () {
        $(this).val(slugifyCourierCode($(this).val()));
    });

    $(document).on('click', '.btnEditCourier', function () {
        const button = $(this);

        $('#courierEditForm').attr('action', button.data('update-url'));
        $('#edit_name').val(button.data('name') || '');
        $('#edit_code').val(button.data('code') || '');
        $('#edit_merchant_id').val(button.data('merchant-id') || '');
        $('#edit_phone_number').val(button.data('phone-number') || '');
        $('#edit_status').prop('checked', Number(button.data('status')) === 1);
    });

    $('.courier-delete-form').on('submit', function (e) {
        e.preventDefault();

        const form = this;

        Swal.fire({
            title: 'Delete this courier?',
            text: 'This courier will be moved to trash.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed || result.value) {
                form.submit();
            }
        });
    });
});
</script>
@endsection

@section('css')
<style>
.breadcrumb-item + .breadcrumb-item::before {
    content: ">";
}

.swal2-container {
    z-index: 999999 !important;
}

.form-control {
    box-shadow: none !important;
}

.custom-control-label {
    cursor: pointer;
}

.table td,
.table th {
    vertical-align: middle;
}
</style>
@endsection