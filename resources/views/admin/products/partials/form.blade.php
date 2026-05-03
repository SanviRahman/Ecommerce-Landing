<form action="{{ $action }}" method="POST" id="productForm" enctype="multipart/form-data">
    @csrf

    @if($isEdit)
        @method('PUT')
    @endif

    <div class="card card-primary card-outline card-outline-tabs shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="product-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active font-weight-bold p-2"
                       id="general-tab"
                       data-toggle="pill"
                       href="#tab-general"
                       role="tab">
                        <i class="fas fa-box mr-2 text-primary"></i> General Info
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link font-weight-bold p-2"
                       id="price-tab"
                       data-toggle="pill"
                       href="#tab-price"
                       role="tab">
                        <i class="fas fa-tags mr-2 text-warning"></i> Price & Stock
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link font-weight-bold p-2"
                       id="media-tab"
                       data-toggle="pill"
                       href="#tab-media"
                       role="tab">
                        <i class="fas fa-images mr-2 text-info"></i> Media
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link font-weight-bold p-2"
                       id="seo-tab"
                       data-toggle="pill"
                       href="#tab-seo"
                       role="tab">
                        <i class="fas fa-search-plus mr-2 text-success"></i> SEO
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content">

                {{-- General Info --}}
                <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Product Name <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   name="name"
                                   id="product_name"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('name', $product->name ?? '') }}"
                                   placeholder="Enter product name"
                                   required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                URL Slug
                            </label>

                            <input type="text"
                                   name="slug"
                                   id="product_slug"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('slug', $product->slug ?? '') }}"
                                   placeholder="auto-generated">
                        </div>

                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Product Code <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   name="product_code"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('product_code', $product->product_code ?? '') }}"
                                   placeholder="e.g. PRD-1001"
                                   required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Category <span class="text-danger">*</span>
                            </label>

                            <select name="category_id"
                                    class="form-control border-light bg-light shadow-none"
                                    required>
                                <option value="">Select Category</option>

                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}"
                                        {{ old('category_id', $product->category_id ?? '') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Brand
                            </label>

                            <select name="brand_id"
                                    class="form-control border-light bg-light shadow-none">
                                <option value="">No Brand</option>

                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}"
                                        {{ old('brand_id', $product->brand_id ?? '') == $brand->id ? 'selected' : '' }}>
                                        {{ $brand->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Weight / Size
                            </label>

                            <input type="text"
                                   name="weight_size"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('weight_size', $product->weight_size ?? '') }}"
                                   placeholder="e.g. 500g, 1kg, XL">
                        </div>

                        <div class="col-md-12 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Short Description
                            </label>

                            <textarea name="short_description"
                                      class="form-control border-light bg-light shadow-none"
                                      rows="3"
                                      placeholder="Short product description">{{ old('short_description', $product->short_description ?? '') }}</textarea>
                        </div>

                        <div class="col-md-12 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Full Description
                            </label>

                            <textarea name="full_description"
                                      class="form-control border-light bg-light shadow-none"
                                      rows="5"
                                      placeholder="Full product description">{{ old('full_description', $product->full_description ?? '') }}</textarea>
                        </div>

                        <div class="col-md-3 form-group">
                            <div class="custom-control custom-switch" style="padding-left: 3.5rem;">
                                <input type="checkbox"
                                       name="status"
                                       class="custom-control-input"
                                       id="statusSwitch"
                                       value="1"
                                       {{ (!isset($product) || $product?->status) ? 'checked' : '' }}>

                                <label class="custom-control-label font-weight-bold" for="statusSwitch">
                                    Active
                                </label>
                            </div>
                        </div>

                        <div class="col-md-3 form-group">
                            <div class="custom-control custom-switch" style="padding-left: 3.5rem;">
                                <input type="checkbox"
                                       name="is_top_sale"
                                       class="custom-control-input"
                                       id="topSaleSwitch"
                                       value="1"
                                       {{ isset($product) && $product?->is_top_sale ? 'checked' : '' }}>

                                <label class="custom-control-label font-weight-bold" for="topSaleSwitch">
                                    Top Sale
                                </label>
                            </div>
                        </div>

                        <div class="col-md-3 form-group">
                            <div class="custom-control custom-switch" style="padding-left: 3.5rem;">
                                <input type="checkbox"
                                       name="is_feature"
                                       class="custom-control-input"
                                       id="featureSwitch"
                                       value="1"
                                       {{ isset($product) && $product?->is_feature ? 'checked' : '' }}>

                                <label class="custom-control-label font-weight-bold" for="featureSwitch">
                                    Featured
                                </label>
                            </div>
                        </div>

                        <div class="col-md-3 form-group">
                            <div class="custom-control custom-switch" style="padding-left: 3.5rem;">
                                <input type="checkbox"
                                       name="is_flash_sale"
                                       class="custom-control-input"
                                       id="flashSaleSwitch"
                                       value="1"
                                       {{ isset($product) && $product?->is_flash_sale ? 'checked' : '' }}>

                                <label class="custom-control-label font-weight-bold" for="flashSaleSwitch">
                                    Flash Sale
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Price & Stock --}}
                <div class="tab-pane fade" id="tab-price" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Purchase Price <span class="text-danger">*</span>
                            </label>

                            <input type="number"
                                   name="purchase_price"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('purchase_price', $product->purchase_price ?? 0) }}"
                                   min="0"
                                   required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Old Price
                            </label>

                            <input type="number"
                                   name="old_price"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('old_price', $product->old_price ?? '') }}"
                                   min="0">
                        </div>

                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                New Price <span class="text-danger">*</span>
                            </label>

                            <input type="number"
                                   name="new_price"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('new_price', $product->new_price ?? 0) }}"
                                   min="0"
                                   required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Stock <span class="text-danger">*</span>
                            </label>

                            <input type="number"
                                   name="stock"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('stock', $product->stock ?? 0) }}"
                                   min="0"
                                   required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Sold Quantity
                            </label>

                            <input type="number"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ $product->sold_quantity ?? 0 }}"
                                   disabled>
                        </div>
                    </div>
                </div>

                {{-- Media --}}
                <div class="tab-pane fade" id="tab-media" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="font-weight-bold small text-uppercase text-muted d-block">
                                Product Thumbnail
                            </label>

                            <div class="image-upload-wrapper mb-3 shadow-sm border rounded-lg"
                                 style="width: 220px; height: 220px; background: #fff; overflow: hidden; cursor: pointer;"
                                 onclick="$('#thumbnail_input').click()">

                                <img id="thumbnail_preview"
                                     src="{{ isset($product) && $product ? $product->thumbnail : asset('vendor/adminlte/dist/img/no-image.png') }}"
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            </div>

                            <input type="file"
                                   name="thumbnail"
                                   id="thumbnail_input"
                                   class="d-none"
                                   accept="image/*">

                            <button type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    onclick="$('#thumbnail_input').click()">
                                <i class="fas fa-camera mr-1"></i> Change Thumbnail
                            </button>

                            <p class="small text-muted mt-2 mb-0">
                                Recommended: JPG, PNG, WEBP. Max 2MB.
                            </p>
                        </div>

                        <div class="col-md-8">
                            <label class="font-weight-bold small text-uppercase text-muted d-block">
                                Product Gallery
                            </label>

                            <input type="file"
                                   name="gallery[]"
                                   id="gallery_input"
                                   class="form-control-file mb-3"
                                   multiple
                                   accept="image/*">

                            @if(isset($product) && $product)
                                <div class="row">
                                    @forelse($product->getMedia('product_gallery') as $media)
                                        <div class="col-md-3 mb-3" id="media-box-{{ $media->id }}">
                                            <div class="border rounded p-1 text-center">
                                                <img src="{{ $media->getUrl() }}"
                                                     style="width: 100%; height: 90px; object-fit: cover;"
                                                     class="rounded mb-1">

                                                <button type="button"
                                                        class="btn btn-danger btn-sm btnDeleteMedia"
                                                        data-id="{{ $media->id }}"
                                                        data-url="{{ route('admin.products.delete_media', $media->id) }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <p class="text-muted small">No gallery images uploaded.</p>
                                        </div>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- SEO --}}
                <div class="tab-pane fade" id="tab-seo" role="tabpanel">
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Meta Title
                            </label>

                            <input type="text"
                                   name="meta_title"
                                   class="form-control border-light bg-light shadow-none"
                                   value="{{ old('meta_title', $product->meta_title ?? '') }}">
                        </div>

                        <div class="col-md-12 form-group">
                            <label class="font-weight-bold small text-uppercase text-muted">
                                Meta Description
                            </label>

                            <textarea name="meta_description"
                                      class="form-control border-light bg-light shadow-none"
                                      rows="4">{{ old('meta_description', $product->meta_description ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="card-footer bg-white border-top p-4 text-right">
            <button type="button"
                    class="btn btn-link text-muted font-weight-bold text-decoration-none mr-3"
                    data-dismiss="modal">
                Cancel
            </button>

            <button type="submit"
                    class="btn btn-primary px-5 font-weight-bold shadow-sm"
                    style="border-radius: 10px;">
                <i class="fas fa-save mr-1"></i>
                {{ $isEdit ? 'Update Product' : 'Create Product' }}
            </button>
        </div>
    </div>
</form>

<script>
    $(document).ready(function () {
        $('#thumbnail_input').on('change', function () {
            const file = this.files[0];

            if (file) {
                let reader = new FileReader();

                reader.onload = (e) => $('#thumbnail_preview').attr('src', e.target.result);

                reader.readAsDataURL(file);
            }
        });

        @if(! $isEdit)
            $('#product_name').on('keyup', function () {
                let slug = $(this).val().toLowerCase().trim()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/[\s_-]+/g, '-')
                    .replace(/^-+|-+$/g, '');

                $('#product_slug').val(slug);
            });
        @endif

        $('.btnDeleteMedia').on('click', function () {
            let url = $(this).data('url');
            let id = $(this).data('id');

            Swal.fire({
                title: 'Delete this image?',
                text: 'This image will be removed from product gallery.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (res) {
                            if (res.status) {
                                $('#media-box-' + id).remove();

                                Swal.fire({
                                    icon: 'success',
                                    title: res.message,
                                    timer: 1200,
                                    showConfirmButton: false
                                });
                            }
                        },
                        error: function () {
                            Swal.fire('Error', 'Image delete failed.', 'error');
                        }
                    });
                }
            });
        });
    });
</script>