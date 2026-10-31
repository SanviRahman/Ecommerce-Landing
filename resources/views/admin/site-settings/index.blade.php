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
<style>
.sf-social-setting-panel{padding:22px 20px 8px;margin:10px 0 20px;background:linear-gradient(135deg,#f5fbf7,#fff);border:1px solid #d0eadd;border-radius:16px;box-shadow:0 8px 24px rgba(19,90,58,.05)}
.sf-social-setting-panel .form-control{border-radius:9px;min-height:42px}
.sf-social-setting-panel .form-control:focus{border-color:#218f61;box-shadow:0 0 0 3px rgba(33,143,97,.12)}
</style>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>Please fix the following errors:</strong>

        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
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
                    Campaign & Storefront Settings
                </h5>

                <small class="text-muted">
                    Contact details are shared; campaign and storefront branding images are independent. Existing campaign pages and footer controls remain unchanged.
                </small>
            </div>

            <div class="card-body">
                <form action="{{ $action }}" method="POST" enctype="multipart/form-data" id="siteSettingForm">
                    @csrf

                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <div class="form-group mb-4">
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

                    <div class="card border-success mb-4">
                        <div class="card-header bg-light"><h6 class="mb-0 font-weight-bold"><i class="fas fa-address-card text-success mr-2"></i>Storefront Footer & Contact Us</h6></div>
                        <div class="card-body">
                            <p class="text-muted small">These existing Site Settings fields feed the public footer and Contact Us page. Leave a field blank to hide it.</p>
                            <div class="row">
                                <div class="form-group col-md-6"><label for="sfContactPhone">Phone</label><input id="sfContactPhone" type="tel" name="phone" maxlength="25" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $siteSetting->phone ?? '') }}" placeholder="01XXXXXXXXX">@error('phone')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
                                <div class="form-group col-md-6"><label for="sfContactHotline">Hotline (optional)</label><input id="sfContactHotline" type="tel" name="hotline" maxlength="25" class="form-control @error('hotline') is-invalid @enderror" value="{{ old('hotline', $siteSetting->hotline ?? '') }}">@error('hotline')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
                                <div class="form-group col-md-6"><label for="sfContactEmail">Email</label><input id="sfContactEmail" type="email" name="email" maxlength="190" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $siteSetting->email ?? '') }}" placeholder="support@example.com">@error('email')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
                                <div class="form-group col-md-6"><label for="sfWorkingHours">Working Hours</label><input id="sfWorkingHours" type="text" name="working_hours" maxlength="120" class="form-control @error('working_hours') is-invalid @enderror" value="{{ old('working_hours', $siteSetting->working_hours ?? '') }}" placeholder="Sat - Thu · 9 AM - 7 PM">@error('working_hours')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
                                <div class="form-group col-12"><label for="sfAddress">Business Address (also used for Google Maps)</label><textarea id="sfAddress" name="address" rows="3" maxlength="1500" class="form-control @error('address') is-invalid @enderror" placeholder="Your actual store location">{{ old('address', $siteSetting->address ?? '') }}</textarea>@error('address')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
                                <div class="form-group col-12"><label for="sfAbout">Footer Description</label><textarea id="sfAbout" name="business_short_description" rows="2" maxlength="500" class="form-control @error('business_short_description') is-invalid @enderror">{{ old('business_short_description', $siteSetting->business_short_description ?? '') }}</textarea>@error('business_short_description')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
                                <div class="form-group col-md-6"><label for="sfWhatsApp">WhatsApp Number</label><input id="sfWhatsApp" type="tel" maxlength="25" name="whatsapp_number" class="form-control @error('whatsapp_number') is-invalid @enderror" value="{{ old('whatsapp_number', $siteSetting->whatsapp_number ?? '') }}">@error('whatsapp_number')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
                                <div class="form-group col-md-6"><label for="sfMessenger">Messenger HTTPS Link</label><input id="sfMessenger" type="url" maxlength="255" name="messenger_link" class="form-control @error('messenger_link') is-invalid @enderror" value="{{ old('messenger_link', $siteSetting->messenger_link ?? '') }}">@error('messenger_link')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
                                <div class="col-12">
                                    <section class="sf-social-setting-panel" aria-labelledby="sf-footer-links-title">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
                                            <div><h6 id="sf-footer-links-title" class="font-weight-bold text-success mb-1"><i class="fas fa-share-alt mr-1"></i> Footer Social Links</h6>
                                                <p class="small text-muted mb-0">Manage the footer icons here. Blank links will be hidden. WhatsApp and Call use the phone fields above.</p>
                                            </div>
                                            <span class="badge badge-success px-3 py-2">Site Settings managed</span>
                                        </div>
                                        <div class="row">
                                            @foreach(['youtube'=>'YouTube', 'facebook'=>'Facebook', 'instagram'=>'Instagram', 'linkedin'=>'LinkedIn'] as $key=>$label)
                                                @php $field = 'footer_'.$key.'_url'; @endphp
                                                <div class="form-group col-md-6">
                                                    <label for="sf-{{ $key }}">{{ $label }} HTTPS URL</label>
                                                    <input id="sf-{{ $key }}" type="url" name="{{ $field }}" maxlength="255"
                                                        class="form-control @error($field) is-invalid @enderror"
                                                        value="{{ old($field, ($siteSetting && $siteSetting->footer_social_managed) ? ($siteSetting->$field ?? '') : ($siteSetting?->$field ?: ($legacySocial[$key] ?? ''))) }}"
                                                        placeholder="https://{{ $key }}.com/your-profile">
                                                    @error($field)<span class="invalid-feedback">{{ $message }}</span>@enderror
                                                </div>
                                            @endforeach
                                        </div>
                                    </section>
                                </div>
                                <div class="col-12"><input type="hidden" name="status" value="0"><label><input type="checkbox" name="status" value="1" @checked((bool) old('status', $siteSetting->status ?? true))> Enable active public Site Settings</label></div>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-light border-0 mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 font-weight-bold">
                                <i class="fas fa-images mr-1"></i> Campaign Branding — Existing Logo, White Logo &amp; Favicon
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
                                Campaign logos and favicon (existing images). Storefront has separate upload fields below.
                            </small>
                        </div>
                    </div>

                    <div class="card border-success mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-1 font-weight-bold"><i class="fas fa-store text-success mr-2"></i>Storefront Branding — Separate Logo &amp; Favicon</h6>
                            <small class="text-muted">Uploads here change only the public Storefront. Campaign logos/favicon above remain unchanged. PNG, JPG or WebP only.</small>
                        </div>
                        <div class="card-body"><div class="row">
                            @foreach([
                                'storefront_logo' => 'Storefront Logo',
                                'storefront_white_logo' => 'Storefront White Logo',
                                'storefront_favicon' => 'Storefront Favicon',
                            ] as $collection => $label)
                            <div class="col-md-4"><div class="form-group">
                                <label for="{{ $collection }}">{{ $label }}</label>
                                <input type="file" id="{{ $collection }}" name="{{ $collection }}" accept="image/jpeg,image/png,image/webp"
                                    class="form-control @error($collection) is-invalid @enderror">
                                @error($collection)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @if($isEdit && ($currentMedia = $siteSetting?->getFirstMedia($collection)))
                                <div class="media-preview-box mt-3 {{ $collection === 'storefront_white_logo' ? 'dark-preview' : '' }}">
                                    <img class="img-fluid img-thumbnail" src="{{ $siteSetting->getFirstMediaUrl($collection) }}" alt="{{ $label }}">
                                    <button type="button" class="btn btn-sm btn-danger btnDeleteMedia mt-2"
                                        data-url="{{ route('admin.site-settings.delete_media', $currentMedia->id) }}">
                                        <i class="fas fa-trash mr-1"></i>Remove
                                    </button>
                                </div>
                                @endif
                            </div></div>
                            @endforeach
                        </div><p class="small text-muted mb-0">No new database columns are required: existing Spatie media table keeps each collection separate. Only upload the images you want to change.</p></div>
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
                    Campaign Branding Preview
                </h5>
            </div>

            <div class="card-body">
                @if ($siteSetting)
                    <div class="preview-brand-box text-center mb-3">
                        @if ($siteSetting->getFirstMedia('site_logo'))
                            <img src="{{ $siteSetting->logo }}"
                                 class="img-fluid preview-logo"
                                 alt="{{ $siteSetting->website_name }}">
                        @else
                            <div class="empty-logo">
                                <i class="fas fa-image"></i>
                            </div>
                        @endif

                        <h4 class="font-weight-bold mt-3 mb-0">
                            {{ $siteSetting->website_name }}
                        </h4>
                    </div>

                    <div class="branding-preview-grid">
                        <div class="branding-preview-item">
                            <strong>Site Logo</strong>
                            @if ($siteSetting->getFirstMedia('site_logo'))
                                <img src="{{ $siteSetting->logo }}" alt="Site Logo">
                            @else
                                <span class="text-muted">Not uploaded</span>
                            @endif
                        </div>

                        <div class="branding-preview-item dark-preview">
                            <strong class="text-white">White Logo</strong>
                            @if ($siteSetting->getFirstMedia('site_white_logo'))
                                <img src="{{ $siteSetting->white_logo }}" alt="White Logo">
                            @else
                                <span class="text-light">Not uploaded</span>
                            @endif
                        </div>

                        <div class="branding-preview-item">
                            <strong>Favicon</strong>
                            @if ($siteSetting->getFirstMedia('site_favicon'))
                                <img src="{{ $siteSetting->favicon }}" class="favicon-preview" alt="Favicon">
                            @else
                                <span class="text-muted">Not uploaded</span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-cogs fa-3x mb-3"></i>
                        <h5>No settings found</h5>
                        <p class="mb-0">Website name and branding images save করুন।</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm setting-card">
            <div class="card-header bg-white"><h5 class="mb-0 font-weight-bold"><i class="fas fa-store text-success mr-1"></i> Storefront Branding Preview</h5></div>
            <div class="card-body"><div class="branding-preview-grid">
                @foreach([
                    'storefront_logo' => 'Storefront Logo',
                    'storefront_white_logo' => 'Storefront White Logo',
                    'storefront_favicon' => 'Storefront Favicon',
                ] as $collection => $label)
                <div class="branding-preview-item {{ $collection === 'storefront_white_logo' ? 'dark-preview' : '' }}">
                    <strong class="{{ $collection === 'storefront_white_logo' ? 'text-white' : '' }}">{{ $label }}</strong>
                    @if($siteSetting?->getFirstMedia($collection))
                        <img src="{{ $siteSetting->getFirstMediaUrl($collection) }}" alt="{{ $label }}">
                    @else
                        <span class="{{ $collection === 'storefront_white_logo' ? 'text-light' : 'text-muted' }}">Not uploaded</span>
                    @endif
                </div>
                @endforeach
            </div></div>
        </div>

        <div class="card border-0 shadow-sm setting-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-info-circle text-info mr-1"></i>
                    Usage Note
                </h5>
            </div>

            <div class="card-body">
                <p class="text-muted mb-0">
                    Global storefront contact details above are saved in Site Settings. Campaign pages retain their campaign-specific footer controls.
                </p>
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
    background: #111827 !important;
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

.branding-preview-grid {
    display: grid;
    gap: 10px;
}

.branding-preview-item {
    min-height: 96px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f9fafb;
    text-align: center;
}

.branding-preview-item img {
    max-width: 100%;
    max-height: 58px;
    object-fit: contain;
}

.swal2-container {
    z-index: 999999 !important;
}
</style>
@endsection
