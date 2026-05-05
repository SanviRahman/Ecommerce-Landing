@extends('adminlte::page')

@section('title', $title ?? 'Site Settings')

@section('plugins.Sweetalert2', true)

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Site Settings' }}</h1>

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

    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left mr-1"></i> Dashboard
        </a>
    </div>
</div>
@endsection

@section('content')

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>Please fix the following errors:</strong>

        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{!! $error !!}</li>
            @endforeach
        </ul>

        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm setting-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-cogs text-primary mr-1"></i>
                    Website Information
                </h5>

                <small class="text-muted">
                    Manage website name, contact information, headline and footer text.
                </small>
            </div>

            <div class="card-body">
                <form action="{{ $action }}" method="POST" enctype="multipart/form-data" id="siteSettingForm">
                    @csrf

                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>
                                    Website Name <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       name="website_name"
                                       value="{{ old('website_name', $siteSetting->website_name ?? '') }}"
                                       class="form-control @error('website_name') is-invalid @enderror"
                                       placeholder="Example: Shanto Gift Shop"
                                       required>

                                @error('website_name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>

                                <input type="email"
                                       name="email"
                                       value="{{ old('email', $siteSetting->email ?? '') }}"
                                       class="form-control @error('email') is-invalid @enderror"
                                       placeholder="example@gmail.com">

                                @error('email')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Phone</label>

                                <input type="text"
                                       name="phone"
                                       value="{{ old('phone', $siteSetting->phone ?? '') }}"
                                       class="form-control @error('phone') is-invalid @enderror"
                                       placeholder="017XXXXXXXX">

                                @error('phone')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Hotline</label>

                                <input type="text"
                                       name="hotline"
                                       value="{{ old('hotline', $siteSetting->hotline ?? '') }}"
                                       class="form-control @error('hotline') is-invalid @enderror"
                                       placeholder="096XXXXXXXX">

                                @error('hotline')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>WhatsApp Number</label>

                                <input type="text"
                                       name="whatsapp_number"
                                       value="{{ old('whatsapp_number', $siteSetting->whatsapp_number ?? '') }}"
                                       class="form-control @error('whatsapp_number') is-invalid @enderror"
                                       placeholder="88017XXXXXXXX">

                                @error('whatsapp_number')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Messenger Link</label>

                        <input type="url"
                               name="messenger_link"
                               value="{{ old('messenger_link', $siteSetting->messenger_link ?? '') }}"
                               class="form-control @error('messenger_link') is-invalid @enderror"
                               placeholder="https://m.me/your-page-name">

                        @error('messenger_link')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Address</label>

                        <textarea name="address"
                                  class="form-control @error('address') is-invalid @enderror"
                                  rows="3"
                                  placeholder="Business address">{{ old('address', $siteSetting->address ?? '') }}</textarea>

                        @error('address')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Top Headline</label>

                        <textarea name="top_headline"
                                  class="form-control @error('top_headline') is-invalid @enderror"
                                  rows="2"
                                  placeholder="Example: আজকের স্পেশাল অফার">{{ old('top_headline', $siteSetting->top_headline ?? '') }}</textarea>

                        @error('top_headline')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Business Short Description</label>

                        <textarea name="business_short_description"
                                  class="form-control @error('business_short_description') is-invalid @enderror"
                                  rows="4"
                                  placeholder="Short business description">{{ old('business_short_description', $siteSetting->business_short_description ?? '') }}</textarea>

                        @error('business_short_description')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Footer Text</label>

                        <textarea name="footer_text"
                                  class="form-control @error('footer_text') is-invalid @enderror"
                                  rows="3"
                                  placeholder="Footer copyright or business text">{{ old('footer_text', $siteSetting->footer_text ?? '') }}</textarea>

                        @error('footer_text')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Working Hours</label>

                        <input type="text"
                               name="working_hours"
                               value="{{ old('working_hours', $siteSetting->working_hours ?? '') }}"
                               class="form-control @error('working_hours') is-invalid @enderror"
                               placeholder="Example: Sat - Thu, 10:00 AM - 8:00 PM">

                        @error('working_hours')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="custom-control custom-switch mb-4">
                        <input type="checkbox"
                               name="status"
                               value="1"
                               class="custom-control-input"
                               id="status"
                               @checked(old('status', $siteSetting->status ?? true))>

                        <label class="custom-control-label font-weight-bold" for="status">
                            Active
                        </label>
                    </div>

                    <div class="card bg-light border-0 mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 font-weight-bold">
                                <i class="fas fa-images mr-1"></i> Branding Images
                            </h6>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                {{-- Site Logo --}}
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Site Logo</label>

                                        <input type="file"
                                               name="site_logo"
                                               class="form-control @error('site_logo') is-invalid @enderror"
                                               accept="image/*">

                                        @error('site_logo')
                                            <span class="invalid-feedback d-block">{{ $message }}</span>
                                        @enderror

                                        @if ($isEdit && $siteSetting?->getFirstMedia('site_logo'))
                                            @php
                                                $siteLogoMedia = $siteSetting->getFirstMedia('site_logo');
                                            @endphp

                                            <div class="media-preview-box mt-3">
                                                <img src="{{ $siteSetting->logo }}"
                                                     class="img-fluid img-thumbnail"
                                                     alt="Site Logo">

                                                <button type="button"
                                                        class="btn btn-sm btn-danger btnDeleteMedia mt-2"
                                                        data-url="{{ route('admin.site-settings.delete_media', $siteLogoMedia->id) }}">
                                                    <i class="fas fa-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- White Logo --}}
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>White Logo</label>

                                        <input type="file"
                                               name="site_white_logo"
                                               class="form-control @error('site_white_logo') is-invalid @enderror"
                                               accept="image/*">

                                        @error('site_white_logo')
                                            <span class="invalid-feedback d-block">{{ $message }}</span>
                                        @enderror

                                        @if ($isEdit && $siteSetting?->getFirstMedia('site_white_logo'))
                                            @php
                                                $whiteLogoMedia = $siteSetting->getFirstMedia('site_white_logo');
                                            @endphp

                                            <div class="media-preview-box dark-preview mt-3">
                                                <img src="{{ $siteSetting->white_logo }}"
                                                     class="img-fluid img-thumbnail"
                                                     alt="White Logo">

                                                <button type="button"
                                                        class="btn btn-sm btn-danger btnDeleteMedia mt-2"
                                                        data-url="{{ route('admin.site-settings.delete_media', $whiteLogoMedia->id) }}">
                                                    <i class="fas fa-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Favicon --}}
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Favicon</label>

                                        <input type="file"
                                               name="site_favicon"
                                               class="form-control @error('site_favicon') is-invalid @enderror"
                                               accept="image/*,.ico">

                                        @error('site_favicon')
                                            <span class="invalid-feedback d-block">{{ $message }}</span>
                                        @enderror

                                        @if ($isEdit && $siteSetting?->getFirstMedia('site_favicon'))
                                            @php
                                                $faviconMedia = $siteSetting->getFirstMedia('site_favicon');
                                            @endphp

                                            <div class="media-preview-box mt-3">
                                                <img src="{{ $siteSetting->favicon }}"
                                                     class="img-fluid img-thumbnail favicon-preview"
                                                     alt="Favicon">

                                                <button type="button"
                                                        class="btn btn-sm btn-danger btnDeleteMedia mt-2"
                                                        data-url="{{ route('admin.site-settings.delete_media', $faviconMedia->id) }}">
                                                    <i class="fas fa-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <small class="text-muted">
                                Recommended: Logo PNG/WebP, White Logo transparent PNG/WebP, Favicon 64x64 or 128x128.
                            </small>
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-save mr-1"></i>
                            {{ $isEdit ? 'Update Settings' : 'Save Settings' }}
                        </button>

                        <button type="reset" class="btn btn-secondary px-4">
                            <i class="fas fa-redo mr-1"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Right Side Preview --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm setting-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-eye text-primary mr-1"></i>
                    Current Preview
                </h5>
            </div>

            <div class="card-body">
                @if ($siteSetting)
                    <div class="preview-brand-box text-center mb-4">
                        @if ($siteSetting->getFirstMedia('site_logo'))
                            <img src="{{ $siteSetting->logo }}"
                                 class="img-fluid preview-logo"
                                 alt="{{ $siteSetting->website_name }}">
                        @else
                            <div class="empty-logo">
                                <i class="fas fa-image"></i>
                            </div>
                        @endif

                        <h4 class="font-weight-bold mt-3 mb-1">
                            {{ $siteSetting->website_name }}
                        </h4>

                        @if ($siteSetting->status)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </div>

                    <ul class="list-group list-group-flush preview-list">
                        <li class="list-group-item px-0">
                            <strong><i class="fas fa-phone text-primary mr-1"></i> Phone:</strong><br>
                            <span>{{ $siteSetting->phone ?? 'N/A' }}</span>
                        </li>

                        <li class="list-group-item px-0">
                            <strong><i class="fas fa-headset text-primary mr-1"></i> Hotline:</strong><br>
                            <span>{{ $siteSetting->hotline ?? 'N/A' }}</span>
                        </li>

                        <li class="list-group-item px-0">
                            <strong><i class="fab fa-whatsapp text-success mr-1"></i> WhatsApp:</strong><br>
                            <span>{{ $siteSetting->whatsapp_number ?? 'N/A' }}</span>
                        </li>

                        <li class="list-group-item px-0">
                            <strong><i class="fas fa-envelope text-primary mr-1"></i> Email:</strong><br>
                            <span>{{ $siteSetting->email ?? 'N/A' }}</span>
                        </li>

                        <li class="list-group-item px-0">
                            <strong><i class="fas fa-clock text-primary mr-1"></i> Working Hours:</strong><br>
                            <span>{{ $siteSetting->working_hours ?? 'N/A' }}</span>
                        </li>

                        <li class="list-group-item px-0">
                            <strong><i class="fas fa-map-marker-alt text-danger mr-1"></i> Address:</strong><br>
                            <span>{{ $siteSetting->address ?? 'N/A' }}</span>
                        </li>
                    </ul>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-cogs fa-3x mb-3"></i>
                        <h5>No settings found</h5>
                        <p class="mb-0">Fill the form and save your site settings.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm setting-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-info-circle text-info mr-1"></i>
                    Usage Note
                </h5>
            </div>

            <div class="card-body">
                <p class="text-muted mb-2">
                    Frontend header/footer এ এই settings use করতে পারবে।
                </p>

                <code>
                    \App\Models\SiteSetting::where('status', true)->latest()->first()
                </code>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
$(document).ready(function () {
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

    @if (session('success'))
        showToast('success', @json(session('success')));
    @endif

    @if (session('error'))
        showToast('error', @json(session('error')));
    @endif

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    });

    $(document).on('click', '.btnDeleteMedia', function () {
        let url = $(this).data('url');

        Swal.fire({
            title: 'Remove media?',
            text: 'This image will be removed from site settings.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed || result.value) {
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Media removed successfully.');

                            setTimeout(function () {
                                window.location.reload();
                            }, 800);
                        } else {
                            showToast('error', res.message || 'Media remove failed.');
                        }
                    },
                    error: function (xhr) {
                        let message = 'Media remove failed.';

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        Swal.fire('Error', message, 'error');
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
.setting-card {
    border-radius: 12px;
    overflow: hidden;
}

.breadcrumb-item+.breadcrumb-item::before {
    content: ">";
}

.form-control {
    border-radius: 7px;
    min-height: 42px;
}

.form-group label {
    font-weight: 600;
    color: #374151;
}

.media-preview-box {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px;
    background: #f9fafb;
    text-align: center;
}

.media-preview-box img {
    max-height: 90px;
    object-fit: contain;
}

.dark-preview {
    background: #111827;
}

.favicon-preview {
    max-width: 64px;
    max-height: 64px;
}

.preview-brand-box {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    background: #f9fafb;
}

.preview-logo {
    max-height: 90px;
    object-fit: contain;
}

.empty-logo {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    margin: 0 auto;
}

.preview-list strong {
    color: #111827;
}

.preview-list span {
    color: #6b7280;
}

.swal2-container {
    z-index: 999999 !important;
}
</style>
@endsection