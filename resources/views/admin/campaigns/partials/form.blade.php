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

    <div class="form-group">
        <label>Banner Image</label>
        <input type="file"
               name="banner_image"
               class="form-control @error('banner_image') is-invalid @enderror"
               accept="image/*">

        @error('banner_image')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror

        @if ($isEdit && !empty($campaign?->banner_image_url))
            <div class="mt-2">
                <img src="{{ $campaign->banner_image_url }}" width="140" class="img-thumbnail">
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
        <div class="col-md-4">
            <div class="form-group">
                <label>Image One</label>
                <input type="file"
                       name="image_one"
                       class="form-control"
                       accept="image/*">

                @if ($isEdit && !empty($campaign?->image_one_url))
                    <div class="mt-2">
                        <img src="{{ $campaign->image_one_url }}" width="120" class="img-thumbnail">
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Image Two</label>
                <input type="file"
                       name="image_two"
                       class="form-control"
                       accept="image/*">

                @if ($isEdit && !empty($campaign?->image_two_url))
                    <div class="mt-2">
                        <img src="{{ $campaign->image_two_url }}" width="120" class="img-thumbnail">
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Image Three</label>
                <input type="file"
                       name="image_three"
                       class="form-control"
                       accept="image/*">

                @if ($isEdit && !empty($campaign?->image_three_url))
                    <div class="mt-2">
                        <img src="{{ $campaign->image_three_url }}" width="120" class="img-thumbnail">
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Review Image</label>
        <input type="file"
               name="review_image"
               class="form-control"
               accept="image/*">

        @if ($isEdit && !empty($campaign?->review_image_url))
            <div class="mt-2">
                <img src="{{ $campaign->review_image_url }}" width="120" class="img-thumbnail">
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