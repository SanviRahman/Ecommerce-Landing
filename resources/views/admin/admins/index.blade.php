@extends('adminlte::page')

@section('title', $title ?? 'Admins / Employees')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Admins / Employees' }}</h1>

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
<div class="row">
    <div class="col-12">

        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center flex-wrap">
                <button type="button" class="btn btn-primary mt-2" id="addAdminBtn">
                    <i class="fas fa-plus-circle mr-1"></i> Add New
                </button>

                <div class="ml-auto mt-2">
                    <div class="input-group" style="min-width: 300px;">
                        <input type="text" id="searchAdmin" class="form-control"
                            placeholder="Search name / email / role">

                        <div class="input-group-append">
                            <span class="input-group-text bg-white">
                                <i class="fa fa-search text-muted"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body table-responsive">
                <div id="admin-table-wrapper">
                    @include('admin.admins.partials.table', ['admins' => $admins])
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="adminModal" tabindex="-1" aria-hidden="true" data-backdrop="static" data-keyboard="false">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">User</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body" id="admin-modal-body">
                <div class="text-center py-5">
                    <i class="fa fa-spinner fa-spin"></i> Loading...
                </div>
            </div>

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

@section('plugins.Datatables', true)
@section('plugins.Sweetalert2', true)

@push('css')
<style>
.swal2-container {
    z-index: 999999 !important;
}

.admin-photo {
    width: 70px;
    height: 70px;
    object-fit: cover;
}

.validation-error {
    font-size: 13px;
}

.breadcrumb-item+.breadcrumb-item::before {
    content: ">";
}
</style>
@endpush

@section('js')
<script>
$(document).ready(function() {

    function showToast(type, message) {
        Swal.fire({
            icon: type,
            type: type, // SweetAlert2 v8 এর জন্য এটি যোগ করা হলো
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

    function initDataTable() {
        if ($.fn.DataTable.isDataTable('#datatable')) {
            $('#datatable').DataTable().destroy();
        }

        $('#datatable').DataTable({
            responsive: true,
            autoWidth: false,
            searching: false,
            ordering: true
        });
    }

    initDataTable();

    function reloadTable(searchValue = '', callback = null) {
        $.ajax({
            url: "{{ route('admin.users.index') }}",
            type: "GET",
            data: {
                ajax: 1,
                search: searchValue
            },
            success: function(response) {
                if (response.status && response.html) {
                    $('#admin-table-wrapper').html(response.html);
                    initDataTable();

                    if (typeof callback === 'function') {
                        callback();
                    }
                } else {
                    showToast('error', 'Failed to reload table');
                }
            },
            error: function() {
                showToast('error', 'Failed to reload table');
            }
        });
    }

    function openModal(title, url) {
        $('.modal-title').text(title);

        $('#admin-modal-body').html(`
                    <div class="text-center py-5">
                        <i class="fa fa-spinner fa-spin"></i> Loading...
                    </div>
                `);

        $('#adminModal').modal({
            backdrop: 'static',
            keyboard: false
        });

        $.ajax({
            url: url,
            type: "GET",
            success: function(response) {
                if (response.status && response.html) {
                    $('#admin-modal-body').html(response.html);
                } else {
                    showToast('error', 'Content not found');
                }
            },
            error: function() {
                showToast('error', 'Failed to load content');
            }
        });
    }

    $(document).on('click', '#addAdminBtn', function() {
        openModal('Create Admin / Employee', "{{ route('admin.users.create') }}");
    });

    $(document).on('click', '.viewAdminBtn', function() {
        openModal('View User', $(this).data('url'));
    });

    $(document).on('click', '.editAdminBtn', function() {
        openModal('Edit User', $(this).data('url'));
    });

    let searchTimer = null;

    $(document).on('keyup', '#searchAdmin', function() {
        let value = $(this).val();

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function() {
            reloadTable(value);
        }, 300);
    });

    $(document).on('submit', '#adminForm', function(e) {
        e.preventDefault();

        let form = $(this);
        let formData = new FormData(this);
        let currentSearch = $('#searchAdmin').val() || '';

        form.find('.validation-error').remove();
        form.find('.is-invalid').removeClass('is-invalid');

        $.ajax({
            url: form.attr('action'),
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,

            beforeSend: function() {
                form.find('button[type="submit"]').prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin mr-1"></i> Processing...');
            },

            complete: function() {
                form.find('button[type="submit"]').prop('disabled', false)
                    .html('<i class="fas fa-save mr-1"></i> Save');
            },

            success: function(response) {
                if (response.status) {
                    $('#adminModal').modal('hide');

                    reloadTable(currentSearch, function() {
                        showToast('success', response.message ||
                            'Saved successfully');
                    });
                } else {
                    showToast('error', response.message || 'Something went wrong');
                }
            },

            error: function(xhr) {
                if (xhr.status === 422) {
                    let errors = xhr.responseJSON.errors;

                    if (xhr.responseJSON.message && !errors) {
                        showToast('error', xhr.responseJSON.message);
                        return;
                    }

                    $.each(errors, function(field, messages) {
                        let input = form.find('[name="' + field + '"]');

                        input.addClass('is-invalid');

                        if (input.closest('.input-group').length) {
                            input.closest('.input-group').after(
                                '<small class="text-danger validation-error d-block mt-1">' +
                                messages[0] + '</small>'
                            );
                        } else {
                            input.after(
                                '<small class="text-danger validation-error d-block mt-1">' +
                                messages[0] + '</small>'
                            );
                        }
                    });

                    let firstError = Object.values(errors)[0][0];
                    showToast('error', firstError);
                } else {
                    showToast('error', 'Something went wrong');
                }
            }
        });
    });

    $(document).on('click', '.deleteAdminBtn', function(e) {
        e.preventDefault();

        let url = $(this).data('url');
        let currentSearch = $('#searchAdmin').val() || '';

        Swal.fire({
            title: 'Are you sure?',
            text: "This user will be deleted.",
            icon: 'warning',
            type: 'warning', // SweetAlert2 v8 এর জন্য এটি যোগ করা হলো
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            // ফিক্স: result.value (v8 এর জন্য) অথবা result.isConfirmed (v9+ এর জন্য)
            if (result.isConfirmed || result.value) {
                $.ajax({
                    url: url,
                    type: "POST",
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        _method: "DELETE"
                    },
                    success: function(response) {
                        if (response.status) {
                            reloadTable(currentSearch, function() {
                                showToast('success', response.message ||
                                    'Deleted successfully');
                            });
                        } else {
                            showToast('error', response.message || 'Delete failed');
                        }
                    },
                    error: function(xhr) {
                        let message = 'Delete failed';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        showToast('error', message);
                    }
                });
            }
        });
    });

    $(document).on('change', '.changeAdminStatus', function() {
        let url = $(this).data('url');
        let isActive = $(this).is(':checked') ? 1 : 0;
        let currentSearch = $('#searchAdmin').val() || '';

        $.ajax({
            url: url,
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                _method: "PATCH",
                is_active: isActive
            },
            success: function(response) {
                if (response.status) {
                    reloadTable(currentSearch, function() {
                        showToast('success', response.message || 'Status updated');
                    });
                } else {
                    showToast('error', response.message || 'Status update failed');
                }
            },
            error: function(xhr) {
                let message = 'Status update failed';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                showToast('error', message);
                reloadTable(currentSearch);
            }
        });
    });

});
</script>
@endsection