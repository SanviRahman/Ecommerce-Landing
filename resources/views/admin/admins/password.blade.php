@extends('adminlte::page')

@section('title', $title ?? 'Change Password')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Change Password' }}</h1>

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
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">

            <form id="passwordForm">
                @csrf

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h4 class="mb-1 font-weight-bold text-primary">
                                    Change Password
                                </h4>

                                <p class="text-muted mb-0 small">
                                    Update your account password to keep your admin account secure.
                                </p>
                            </div>

                            <div class="text-right mt-2 mt-md-0">
                                <span class="badge badge-soft-info px-3 py-2 text-uppercase">
                                    <i class="fas fa-shield-alt mr-1"></i> Security
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">

                        <div class="alert alert-light border-left-primary mb-4 shadow-sm">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle text-primary mr-3 mt-1 fa-lg"></i>

                                <div>
                                    <strong class="d-block mb-1">Password Tips</strong>
                                    <span class="text-muted small">
                                        Use at least 6 characters. For better security, use letters, numbers and symbols.
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Current Password --}}
                        <div class="form-group">
                            <label for="current_password" class="font-weight-semibold">
                                Current Password <span class="text-danger">*</span>
                            </label>

                            <div class="input-group mb-1">
                                <input id="current_password"
                                       name="current_password"
                                       type="password"
                                       class="form-control password-input"
                                       placeholder="Enter current password">

                                <div class="input-group-append">
                                    <span class="input-group-text bg-white toggle-password"
                                          data-target="#current_password"
                                          style="cursor: pointer;">
                                        <i class="fas fa-eye text-muted"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- New Password --}}
                        <div class="form-group">
                            <label for="password" class="font-weight-semibold">
                                New Password <span class="text-danger">*</span>
                            </label>

                            <div class="input-group mb-1">
                                <input id="password"
                                       name="password"
                                       type="password"
                                       class="form-control password-input"
                                       placeholder="Enter new password">

                                <div class="input-group-append">
                                    <span class="input-group-text bg-white toggle-password"
                                          data-target="#password"
                                          style="cursor: pointer;">
                                        <i class="fas fa-eye text-muted"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Confirm Password --}}
                        <div class="form-group mb-0">
                            <label for="password_confirmation" class="font-weight-semibold">
                                Confirm New Password <span class="text-danger">*</span>
                            </label>

                            <div class="input-group mb-1">
                                <input id="password_confirmation"
                                       name="password_confirmation"
                                       type="password"
                                       class="form-control password-input"
                                       placeholder="Confirm new password">

                                <div class="input-group-append">
                                    <span class="input-group-text bg-white toggle-password"
                                          data-target="#password_confirmation"
                                          style="cursor: pointer;">
                                        <i class="fas fa-eye text-muted"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="card-footer bg-light d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted mb-2 mb-md-0">
                            <i class="fas fa-lock mr-1 text-success"></i>
                            Your password will be securely encrypted.
                        </small>

                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="submitBtn">
                            <i class="fas fa-check-circle mr-1"></i> Update Password
                        </button>
                    </div>

                </div>
            </form>

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

@push('css')
    <style>
        .font-weight-semibold {
            font-weight: 600;
        }

        .border-left-primary {
            border-left: 4px solid #007bff !important;
        }

        .badge-soft-info {
            background-color: #e1f5fe;
            color: #01579b;
            border: 1px solid #b3e5fc;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .validation-error {
            font-size: 13px;
        }

        .input-group-text:hover i {
            color: #007bff !important;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
        }

        .swal2-container {
            z-index: 999999 !important;
        }
    </style>
@endpush

@section('js')
    <script>
        $(document).ready(function () {

            function showToast(type, message) {
                Swal.fire({
                    icon: type,
                    title: message,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true,
                    toast: true
                });
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $('.toggle-password').on('click', function () {
                let input = $($(this).data('target'));
                let icon = $(this).find('i');

                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            $('#passwordForm').on('submit', function (e) {
                e.preventDefault();

                let form = $(this);
                let btn = $('#submitBtn');

                form.find('.validation-error').remove();
                form.find('.is-invalid').removeClass('is-invalid');

                $.ajax({
                    url: "{{ route('admin.change-password.update') }}",
                    type: "POST",
                    data: form.serialize(),

                    beforeSend: function () {
                        btn.prop('disabled', true)
                            .html('<i class="fas fa-spinner fa-spin mr-1"></i> Processing...');
                    },

                    complete: function () {
                        btn.prop('disabled', false)
                            .html('<i class="fas fa-check-circle mr-1"></i> Update Password');
                    },

                    success: function (response) {
                        if (response.status) {
                            form[0].reset();

                            $('.toggle-password i')
                                .removeClass('fa-eye-slash')
                                .addClass('fa-eye');

                            $('.password-input').attr('type', 'password');

                            showToast('success', response.message || 'Password updated successfully.');
                        } else {
                            showToast('error', response.message || 'Something went wrong.');
                        }
                    },

                    error: function (xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;

                            $.each(errors, function (field, messages) {
                                let input = form.find('[name="' + field + '"]');

                                input.addClass('is-invalid');

                                if (input.closest('.input-group').length) {
                                    input.closest('.input-group').after(
                                        '<small class="text-danger validation-error d-block mt-1">' +
                                        messages[0] +
                                        '</small>'
                                    );
                                } else {
                                    input.after(
                                        '<small class="text-danger validation-error d-block mt-1">' +
                                        messages[0] +
                                        '</small>'
                                    );
                                }
                            });

                            let firstError = Object.values(errors)[0][0];

                            showToast('error', firstError);
                        } else {
                            showToast('error', 'Something went wrong.');
                        }
                    }
                });
            });

        });
    </script>
@endsection