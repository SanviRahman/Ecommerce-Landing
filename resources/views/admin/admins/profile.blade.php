@extends('adminlte::page')

@section('title', $title ?? 'Profile')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Profile' }}</h1>

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
        {{-- Profile Preview --}}
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body text-center pt-5">

                    <div class="position-relative d-inline-block">
                        <img id="profilePreview"
                             src="{{ $admin->image_url }}"
                             class="rounded-circle border shadow-sm mb-3"
                             style="width: 150px; height: 150px; object-fit: cover;"
                             alt="{{ $admin->name }}">

                        <label for="photo"
                               class="btn btn-sm btn-primary position-absolute"
                               style="bottom: 15px; right: 0; border-radius: 50%; cursor: pointer;">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>

                    <h4 class="font-weight-bold mb-1">{{ $admin->name }}</h4>
                    <p class="text-muted small mb-3">{{ $admin->email }}</p>

                    @if($admin->is_active)
                        <span class="badge badge-success px-3 py-2">Active</span>
                    @else
                        <span class="badge badge-danger px-3 py-2">Inactive</span>
                    @endif

                    <hr class="my-4">

                    <div class="text-left">
                        <p class="small text-uppercase font-weight-bold text-muted mb-2">
                            Account Details
                        </p>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Joined:</span>
                            <span>
                                {{ $admin->created_at ? $admin->created_at->format('M d, Y') : '-' }}
                            </span>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Email Verified:</span>
                            <span>
                                {{ $admin->email_verified_at ? $admin->email_verified_at->format('M d, Y') : 'Not Verified' }}
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- Profile Form --}}
        <div class="col-xl-8 col-lg-7">
            <form id="profileForm" enctype="multipart/form-data">
                @csrf

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-user-edit mr-2"></i> Personal Information
                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="row">

                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold" for="name">
                                    Full Name <span class="text-danger">*</span>
                                </label>

                                <input id="name"
                                       name="name"
                                       type="text"
                                       class="form-control"
                                       value="{{ old('name', $admin->name) }}"
                                       placeholder="Enter full name">
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold" for="email">
                                    Email Address <span class="text-danger">*</span>
                                </label>

                                <input id="email"
                                       name="email"
                                       type="email"
                                       class="form-control"
                                       value="{{ old('email', $admin->email) }}"
                                       placeholder="Enter email address">
                            </div>

                            <div class="col-md-12 form-group mb-0">
                                <label class="font-weight-bold" for="photo">
                                    Profile Photo
                                </label>

                                <div class="custom-file">
                                    <input id="photo"
                                           name="photo"
                                           type="file"
                                           class="custom-file-input"
                                           accept="image/*">

                                    <label class="custom-file-label" for="photo">
                                        Choose file...
                                    </label>
                                </div>

                                <small class="text-muted">
                                    Allowed: jpg, jpeg, png, webp. Max size: 2MB.
                                </small>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer bg-light d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted mb-2 mb-md-0">
                            <i class="fas fa-info-circle mr-1 text-primary"></i>
                            Keep your profile information updated.
                        </small>

                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="profileSubmitBtn">
                            <i class="fas fa-save mr-1"></i> Save Changes
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

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .validation-error {
            font-size: 13px;
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

            @if(session('success'))
                showToast('success', "{{ session('success') }}");
            @endif

            @if(session('error'))
                showToast('error', "{{ session('error') }}");
            @endif

            $('#photo').on('change', function (e) {
                const file = e.target.files[0];

                if (file) {
                    $('.custom-file-label').text(file.name);

                    const reader = new FileReader();

                    reader.onload = function (event) {
                        $('#profilePreview').attr('src', event.target.result);
                    };

                    reader.readAsDataURL(file);
                }
            });

            $('#profileForm').on('submit', function (e) {
                e.preventDefault();

                let form = $(this);
                let formData = new FormData(this);
                let btn = $('#profileSubmitBtn');

                form.find('.validation-error').remove();
                form.find('.is-invalid').removeClass('is-invalid');

                $.ajax({
                    url: "{{ route('admin.profile.update') }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,

                    beforeSend: function () {
                        btn.prop('disabled', true)
                            .html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
                    },

                    complete: function () {
                        btn.prop('disabled', false)
                            .html('<i class="fas fa-save mr-1"></i> Save Changes');
                    },

                    success: function (response) {
                        if (response.status) {
                            showToast('success', response.message || 'Profile updated successfully.');

                            if (response.image_url) {
                                $('#profilePreview').attr('src', response.image_url);
                            }

                            if (response.name) {
                                $('h4.font-weight-bold').text(response.name);
                            }

                            if (response.email) {
                                $('p.text-muted.small').text(response.email);
                            }

                            $('.custom-file-label').text('Choose file...');
                            $('#photo').val('');
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

                                if (input.closest('.custom-file').length) {
                                    input.closest('.custom-file').after(
                                        '<small class="text-danger validation-error d-block mt-1">' + messages[0] + '</small>'
                                    );
                                } else {
                                    input.after(
                                        '<small class="text-danger validation-error d-block mt-1">' + messages[0] + '</small>'
                                    );
                                }
                            });

                            showToast('error', 'Please fix the validation errors.');
                        } else {
                            showToast('error', 'Something went wrong.');
                        }
                    }
                });
            });

        });
    </script>
@endsection