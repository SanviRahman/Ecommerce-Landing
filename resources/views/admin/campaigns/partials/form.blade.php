@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $bannerImageMedia = ($isEdit && isset($campaign) && $campaign) ? $campaign->getFirstMedia('banner_image') : null;
    $campaignVideoMedia = ($isEdit && isset($campaign) && $campaign) ? $campaign->getFirstMedia('campaign_video') : null;
    $imageOneMedia = ($isEdit && isset($campaign) && $campaign) ? $campaign->getFirstMedia('image_one') : null;
    $imageTwoMedia = ($isEdit && isset($campaign) && $campaign) ? $campaign->getFirstMedia('image_two') : null;
    $imageThreeMedia = ($isEdit && isset($campaign) && $campaign) ? $campaign->getFirstMedia('image_three') : null;
    $reviewImageMedia = ($isEdit && isset($campaign) && $campaign) ? $campaign->getFirstMedia('review_image') : null;
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data">
    @csrf

    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="form-group">
        <label>Landing Page Title <span class="text-danger">*</span></label>
        <input type="text"
               name="title"
               value="{{ old('title', $campaign->title ?? '') }}"
               class="form-control @error('title') is-invalid @enderror"
               placeholder="Example: shantogiftshopbd"
               required>

        @error('title')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    @if ($isEdit)
        <div class="form-group">
            <label>Slug</label>
            <input type="text"
                   name="slug"
                   value="{{ old('slug', $campaign->slug ?? '') }}"
                   class="form-control @error('slug') is-invalid @enderror"
                   placeholder="example: shantogiftshopbd">

            @error('slug')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    @endif

    {{-- Banner Image --}}
    <div class="form-group">
        <label>Banner Image</label>
        <input type="file"
               name="banner_image"
               id="banner_image"
               class="form-control campaign-media-input @error('banner_image') is-invalid @enderror"
               accept="image/*"
               data-preview-target="#banner_image_new_preview"
               data-preview-type="image">

        @error('banner_image')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror

        <div id="banner_image_new_preview" class="mt-2 d-none"></div>

        @if ($isEdit && $bannerImageMedia)
            <div class="mt-2 existing-campaign-media" id="existing_banner_image">
                <div class="d-inline-block position-relative">
                    <img src="{{ $bannerImageMedia->getUrl() }}" width="140" class="img-thumbnail">

                    <button type="button"
                            class="btn btn-sm btn-danger campaign-media-delete-btn"
                            data-url="{{ route('admin.campaigns.delete_media', ['id' => $bannerImageMedia->id]) }}"
                            data-target="#existing_banner_image"
                            style="position:absolute;top:4px;right:4px;"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        @elseif ($isEdit && !empty($campaign?->banner_image_url))
            <div class="mt-2 existing-campaign-media" id="existing_banner_image">
                <div class="d-inline-block position-relative">
                    <img src="{{ $campaign->banner_image_url }}" width="140" class="img-thumbnail">
                </div>
            </div>
        @endif
    </div>

    {{-- Hero Video --}}
    <div class="form-group">
        <label>Hero Video</label>

        <input type="file"
               name="campaign_video"
               id="campaign_video"
               class="form-control campaign-media-input @error('campaign_video') is-invalid @enderror"
               accept="video/mp4,video/webm,video/ogg"
               data-preview-target="#campaign_video_new_preview"
               data-preview-type="video">

        <small class="form-text text-muted">
            Supported video: mp4, webm, ogg. Maximum size: 50MB.
        </small>

        @error('campaign_video')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror

        <div id="campaign_video_new_preview" class="mt-3 d-none"></div>

        @if ($isEdit && $campaignVideoMedia)
            <div class="mt-3 existing-campaign-media" id="existing_campaign_video">
                <div class="d-inline-block position-relative">
                    <video width="280" controls style="border-radius: 8px; background: #111;">
                        <source src="{{ $campaignVideoMedia->getUrl() }}" type="{{ $campaignVideoMedia->mime_type }}">
                        Your browser does not support the video tag.
                    </video>

                    <button type="button"
                            class="btn btn-sm btn-danger campaign-media-delete-btn"
                            data-url="{{ route('admin.campaigns.delete_media', ['id' => $campaignVideoMedia->id]) }}"
                            data-target="#existing_campaign_video"
                            style="position:absolute;top:4px;right:4px;"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                <div class="mt-2">
                    <a href="{{ $campaignVideoMedia->getUrl() }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-play mr-1"></i>
                        View Current Video
                    </a>
                </div>
            </div>
        @elseif ($isEdit && !empty($campaign?->campaign_video_url))
            <div class="mt-3 existing-campaign-media" id="existing_campaign_video">
                <div class="d-inline-block position-relative">
                    <video width="280" controls style="border-radius: 8px; background: #111;">
                        <source src="{{ $campaign->campaign_video_url }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>

                <div class="mt-2">
                    <a href="{{ $campaign->campaign_video_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-play mr-1"></i>
                        View Current Video
                    </a>
                </div>
            </div>
        @endif
    </div>

    <div class="form-group">
        <label>Banner Title / Offer Text</label>
        <input type="text"
               name="offer_text"
               value="{{ old('offer_text', $campaign->offer_text ?? '') }}"
               class="form-control"
               placeholder="Example: হোম ডেলিভারি ফ্রি ৩ দিনের জন্য প্রযোজ্য">
    </div>

    <div class="form-group">
        <label>Products <span class="text-danger">*</span></label>

        <select name="products[]"
                id="products"
                class="form-control select2-products @error('products') is-invalid @enderror"
                multiple="multiple"
                required
                style="width: 100%;">

            @foreach ($products as $product)
                <option value="{{ $product->id }}"
                    @selected(in_array($product->id, old('products', $selectedProducts ?? [])))>
                    {{ $product->name }} — ৳{{ number_format($product->new_price) }}
                </option>
            @endforeach

        </select>

        <small class="form-text text-muted">
            Click this field to show all active products. You can select multiple products.
        </small>

        @error('products')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label>Old Price</label>
                <input type="number"
                       name="old_price"
                       value="{{ old('old_price', $campaign->old_price ?? '') }}"
                       class="form-control"
                       min="0"
                       placeholder="Example: 1500">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>New Price</label>
                <input type="number"
                       name="new_price"
                       value="{{ old('new_price', $campaign->new_price ?? '') }}"
                       class="form-control"
                       min="0"
                       placeholder="Example: 1300">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Button Text</label>
                <input type="text"
                       name="button_text"
                       value="{{ old('button_text', $campaign->button_text ?? 'অর্ডার করুন') }}"
                       class="form-control"
                       placeholder="অর্ডার করুন">
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Image One --}}
        <div class="col-md-4">
            <div class="form-group">
                <label>Image One</label>
                <input type="file"
                       name="image_one"
                       id="image_one"
                       class="form-control campaign-media-input"
                       accept="image/*"
                       data-preview-target="#image_one_new_preview"
                       data-preview-type="image">

                <div id="image_one_new_preview" class="mt-2 d-none"></div>

                @if ($isEdit && $imageOneMedia)
                    <div class="mt-2 existing-campaign-media" id="existing_image_one">
                        <div class="d-inline-block position-relative">
                            <img src="{{ $imageOneMedia->getUrl() }}" width="120" class="img-thumbnail">

                            <button type="button"
                                    class="btn btn-sm btn-danger campaign-media-delete-btn"
                                    data-url="{{ route('admin.campaigns.delete_media', ['id' => $imageOneMedia->id]) }}"
                                    data-target="#existing_image_one"
                                    style="position:absolute;top:4px;right:4px;"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                @elseif ($isEdit && !empty($campaign?->image_one_url))
                    <div class="mt-2 existing-campaign-media" id="existing_image_one">
                        <div class="d-inline-block position-relative">
                            <img src="{{ $campaign->image_one_url }}" width="120" class="img-thumbnail">
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Image Two --}}
        <div class="col-md-4">
            <div class="form-group">
                <label>Image Two</label>
                <input type="file"
                       name="image_two"
                       id="image_two"
                       class="form-control campaign-media-input"
                       accept="image/*"
                       data-preview-target="#image_two_new_preview"
                       data-preview-type="image">

                <div id="image_two_new_preview" class="mt-2 d-none"></div>

                @if ($isEdit && $imageTwoMedia)
                    <div class="mt-2 existing-campaign-media" id="existing_image_two">
                        <div class="d-inline-block position-relative">
                            <img src="{{ $imageTwoMedia->getUrl() }}" width="120" class="img-thumbnail">

                            <button type="button"
                                    class="btn btn-sm btn-danger campaign-media-delete-btn"
                                    data-url="{{ route('admin.campaigns.delete_media', ['id' => $imageTwoMedia->id]) }}"
                                    data-target="#existing_image_two"
                                    style="position:absolute;top:4px;right:4px;"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                @elseif ($isEdit && !empty($campaign?->image_two_url))
                    <div class="mt-2 existing-campaign-media" id="existing_image_two">
                        <div class="d-inline-block position-relative">
                            <img src="{{ $campaign->image_two_url }}" width="120" class="img-thumbnail">
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Image Three --}}
        <div class="col-md-4">
            <div class="form-group">
                <label>Image Three</label>
                <input type="file"
                       name="image_three"
                       id="image_three"
                       class="form-control campaign-media-input"
                       accept="image/*"
                       data-preview-target="#image_three_new_preview"
                       data-preview-type="image">

                <div id="image_three_new_preview" class="mt-2 d-none"></div>

                @if ($isEdit && $imageThreeMedia)
                    <div class="mt-2 existing-campaign-media" id="existing_image_three">
                        <div class="d-inline-block position-relative">
                            <img src="{{ $imageThreeMedia->getUrl() }}" width="120" class="img-thumbnail">

                            <button type="button"
                                    class="btn btn-sm btn-danger campaign-media-delete-btn"
                                    data-url="{{ route('admin.campaigns.delete_media', ['id' => $imageThreeMedia->id]) }}"
                                    data-target="#existing_image_three"
                                    style="position:absolute;top:4px;right:4px;"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                @elseif ($isEdit && !empty($campaign?->image_three_url))
                    <div class="mt-2 existing-campaign-media" id="existing_image_three">
                        <div class="d-inline-block position-relative">
                            <img src="{{ $campaign->image_three_url }}" width="120" class="img-thumbnail">
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Review Image --}}
    <div class="form-group">
        <label>Review Image</label>
        <input type="file"
               name="review_image"
               id="review_image"
               class="form-control campaign-media-input"
               accept="image/*"
               data-preview-target="#review_image_new_preview"
               data-preview-type="image">

        <div id="review_image_new_preview" class="mt-2 d-none"></div>

        @if ($isEdit && $reviewImageMedia)
            <div class="mt-2 existing-campaign-media" id="existing_review_image">
                <div class="d-inline-block position-relative">
                    <img src="{{ $reviewImageMedia->getUrl() }}" width="120" class="img-thumbnail">

                    <button type="button"
                            class="btn btn-sm btn-danger campaign-media-delete-btn"
                            data-url="{{ route('admin.campaigns.delete_media', ['id' => $reviewImageMedia->id]) }}"
                            data-target="#existing_review_image"
                            style="position:absolute;top:4px;right:4px;"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        @elseif ($isEdit && !empty($campaign?->review_image_url))
            <div class="mt-2 existing-campaign-media" id="existing_review_image">
                <div class="d-inline-block position-relative">
                    <img src="{{ $campaign->review_image_url }}" width="120" class="img-thumbnail">
                </div>
            </div>
        @endif
    </div>

    <div class="form-group">
        <label>Short Description</label>
        <textarea name="short_description"
                  class="form-control"
                  rows="4"
                  placeholder="Short description">{{ old('short_description', $campaign->short_description ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="full_description"
                  class="form-control"
                  rows="6"
                  placeholder="Full description">{{ old('full_description', $campaign->full_description ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>Order Form Title</label>
        <input type="text"
               name="order_form_title"
               value="{{ old('order_form_title', $campaign->order_form_title ?? '') }}"
               class="form-control"
               placeholder="অফারটি সীমিত সময়ের জন্য, তাই অফার শেষ হওয়ার আগেই অর্ডার করুন">
    </div>

    <div class="form-group">
        <label>Order Form Subtitle</label>
        <input type="text"
               name="order_form_subtitle"
               value="{{ old('order_form_subtitle', $campaign->order_form_subtitle ?? '') }}"
               class="form-control"
               placeholder="দুই সেট অর্ডার করলে সারাদেশে হোম ডেলিভারি ফ্রি">
    </div>

    <div class="form-group">
        <label>Meta Title</label>
        <input type="text"
               name="meta_title"
               value="{{ old('meta_title', $campaign->meta_title ?? '') }}"
               class="form-control">
    </div>

    <div class="form-group">
        <label>Meta Description</label>
        <textarea name="meta_description"
                  class="form-control"
                  rows="3">{{ old('meta_description', $campaign->meta_description ?? '') }}</textarea>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="custom-control custom-switch">
                <input type="checkbox"
                       name="status"
                       value="1"
                       class="custom-control-input"
                       id="status"
                       @checked(old('status', $campaign->status ?? true))>
                <label class="custom-control-label" for="status">Active</label>
            </div>
        </div>

        <div class="col-md-4">
            <div class="custom-control custom-switch">
                <input type="checkbox"
                       name="enable_bulk_order"
                       value="1"
                       class="custom-control-input"
                       id="enable_bulk_order"
                       @checked(old('enable_bulk_order', $campaign->enable_bulk_order ?? false))>
                <label class="custom-control-label" for="enable_bulk_order">Enable Bulk Order</label>
            </div>
        </div>
    </div>

    <div class="border-top pt-3">
        <button type="submit" class="btn btn-success px-4">
            <i class="fas fa-save mr-1"></i>
            {{ $isEdit ? 'Update Campaign' : 'Create Campaign' }}
        </button>

        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-secondary px-4">
            <i class="fas fa-times mr-1"></i> Cancel
        </a>
    </div>
</form>

@push('js')
<script>
$(document).ready(function () {
    /*
    |--------------------------------------------------------------------------
    | New Selected Image/Video Preview
    |--------------------------------------------------------------------------
    */
    $(document).on('change', '.campaign-media-input', function () {
        const input = this;
        const file = input.files && input.files[0] ? input.files[0] : null;
        const target = $($(input).data('preview-target'));
        const type = $(input).data('preview-type');

        target.html('').addClass('d-none');

        if (!file) {
            return;
        }

        const objectUrl = URL.createObjectURL(file);

        let previewHtml = '';

        if (type === 'video') {
            previewHtml = `
                <div class="d-inline-block position-relative">
                    <video width="280" controls style="border-radius:8px;background:#111;">
                        <source src="${objectUrl}" type="${file.type}">
                        Your browser does not support the video tag.
                    </video>

                    <button type="button"
                            class="btn btn-sm btn-danger campaign-new-media-remove"
                            data-input="#${input.id}"
                            data-preview="${$(input).data('preview-target')}"
                            style="position:absolute;top:4px;right:4px;"
                            title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div>
                    <small class="text-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        New video selected: ${file.name}
                    </small>
                </div>
            `;
        } else {
            previewHtml = `
                <div class="d-inline-block position-relative">
                    <img src="${objectUrl}"
                         width="140"
                         class="img-thumbnail"
                         style="max-height:120px;object-fit:cover;">

                    <button type="button"
                            class="btn btn-sm btn-danger campaign-new-media-remove"
                            data-input="#${input.id}"
                            data-preview="${$(input).data('preview-target')}"
                            style="position:absolute;top:4px;right:4px;"
                            title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div>
                    <small class="text-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        New image selected: ${file.name}
                    </small>
                </div>
            `;
        }

        target.html(previewHtml).removeClass('d-none');
    });

    /*
    |--------------------------------------------------------------------------
    | Remove New Selected Media Before Submit
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.campaign-new-media-remove', function () {
        const inputSelector = $(this).data('input');
        const previewSelector = $(this).data('preview');

        $(inputSelector).val('');
        $(previewSelector).html('').addClass('d-none');
    });

    /*
    |--------------------------------------------------------------------------
    | Delete Existing Uploaded Media
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.campaign-media-delete-btn', function () {
        const button = $(this);
        const url = button.data('url');
        const target = button.data('target');

        if (!url || url.includes('/null') || url.includes('/undefined')) {
            alert('Media delete URL not found. Please refresh and try again.');
            return;
        }

        if (!confirm('Delete this media?')) {
            return;
        }

        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: url,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function (res) {
                $(target).remove();

                if (typeof Swal !== 'undefined') {
                    Swal.fire('Deleted', res.message || 'Media deleted successfully.', 'success');
                } else {
                    alert(res.message || 'Media deleted successfully.');
                }
            },
            error: function (xhr) {
                button.prop('disabled', false).html('<i class="fas fa-trash"></i>');

                let message = 'Media delete failed.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', message, 'error');
                } else {
                    alert(message);
                }
            }
        });
    });
});
</script>
@endpush