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

<form id="productForm" action="{{ $action }}" method="POST" enctype="multipart/form-data">
    @csrf

    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Product Name <span class="text-danger">*</span></label>

                <input type="text"
                       name="name"
                       value="{{ old('name', $product->name ?? '') }}"
                       class="form-control @error('name') is-invalid @enderror"
                       placeholder="Product name"
                       required>

                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        @if($isEdit)
            <div class="col-md-6">
                <div class="form-group">
                    <label>Slug</label>

                    <input type="text"
                           name="slug"
                           value="{{ old('slug', $product->slug ?? '') }}"
                           class="form-control @error('slug') is-invalid @enderror"
                           placeholder="product-slug">

                    @error('slug')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        @endif

        <div class="col-md-6">
            <div class="form-group">
                <label>Category <span class="text-danger">*</span></label>

                <select name="category_id"
                        class="form-control @error('category_id') is-invalid @enderror"
                        required>
                    <option value="">Select Category</option>

                    @foreach($categories as $category)
                        <option value="{{ $category->id }}"
                            @selected(old('category_id', $product->category_id ?? '') == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                @error('category_id')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Brand</label>

                <select name="brand_id"
                        class="form-control @error('brand_id') is-invalid @enderror">
                    <option value="">Select Brand</option>

                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}"
                            @selected(old('brand_id', $product->brand_id ?? '') == $brand->id)>
                            {{ $brand->name }}
                        </option>
                    @endforeach
                </select>

                @error('brand_id')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Product Code</label>

                <input type="text"
                       name="product_code"
                       value="{{ old('product_code', $product->product_code ?? '') }}"
                       class="form-control @error('product_code') is-invalid @enderror"
                       placeholder="Example: PRD-001">

                @error('product_code')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Purchase Price</label>

                <input type="number"
                       name="purchase_price"
                       value="{{ old('purchase_price', $product->purchase_price ?? '') }}"
                       class="form-control"
                       min="0"
                       placeholder="0">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Old Price</label>

                <input type="number"
                       name="old_price"
                       value="{{ old('old_price', $product->old_price ?? '') }}"
                       class="form-control"
                       min="0"
                       placeholder="0">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>New Price <span class="text-danger">*</span></label>

                <input type="number"
                       name="new_price"
                       value="{{ old('new_price', $product->new_price ?? '') }}"
                       class="form-control @error('new_price') is-invalid @enderror"
                       min="0"
                       required
                       placeholder="0">

                @error('new_price')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Stock</label>

                <input type="number"
                       name="stock"
                       value="{{ old('stock', $product->stock ?? 0) }}"
                       class="form-control"
                       min="0"
                       placeholder="0">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Sold Quantity</label>

                <input type="number"
                       name="sold_quantity"
                       value="{{ old('sold_quantity', $product->sold_quantity ?? 0) }}"
                       class="form-control"
                       min="0"
                       placeholder="0">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Weight / Size</label>

                <input type="text"
                       name="weight_size"
                       value="{{ old('weight_size', $product->weight_size ?? '') }}"
                       class="form-control"
                       placeholder="Example: 500gm">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Product Thumbnail</label>

                <input type="file"
                       name="product_thumbnail"
                       class="form-control @error('product_thumbnail') is-invalid @enderror"
                       accept="image/*">

                @error('product_thumbnail')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror

                @if($isEdit && !empty($product?->thumbnail))
                    <div class="mt-2">
                        <img src="{{ $product->thumbnail }}"
                             alt="{{ $product->name }}"
                             width="120"
                             class="img-thumbnail">
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-8">
            <div class="form-group">
                <label>Product Gallery</label>

                <input type="file"
                       name="product_gallery[]"
                       class="form-control"
                       accept="image/*"
                       multiple>

                @if($isEdit && $product && method_exists($product, 'getMedia'))
                    <div class="row mt-2">
                        @foreach($product->getMedia('product_gallery') as $media)
                            <div class="col-md-3 mb-2" id="product-media-box-{{ $media->id }}">
                                <div class="border rounded p-1">
                                    <img src="{{ $media->getUrl() }}"
                                         class="img-fluid rounded"
                                         style="height:90px;width:100%;object-fit:cover;">

                                    <button type="button"
                                            class="btn btn-xs btn-danger btn-block mt-1 btnDeleteProductMedia"
                                            data-id="{{ $media->id }}"
                                            data-url="{{ route('admin.products.delete_media', $media->id) }}">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-12">
            <hr>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        <input type="checkbox"
                               name="is_top_sale"
                               value="1"
                               class="custom-control-input"
                               id="is_top_sale"
                               @checked(old('is_top_sale', $product->is_top_sale ?? false))>

                        <label class="custom-control-label" for="is_top_sale">
                            Top Sale
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        <input type="checkbox"
                               name="is_feature"
                               value="1"
                               class="custom-control-input"
                               id="is_feature"
                               @checked(old('is_feature', $product->is_feature ?? false))>

                        <label class="custom-control-label" for="is_feature">
                            Featured
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        <input type="checkbox"
                               name="is_flash_sale"
                               value="1"
                               class="custom-control-input"
                               id="is_flash_sale"
                               @checked(old('is_flash_sale', $product->is_flash_sale ?? false))>

                        <label class="custom-control-label" for="is_flash_sale">
                            Flash Sale
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        <input type="checkbox"
                               name="is_free_delivery"
                               value="1"
                               class="custom-control-input"
                               id="is_free_delivery"
                               @checked(old('is_free_delivery', $product->is_free_delivery ?? false))>

                        <label class="custom-control-label" for="is_free_delivery">
                            Free Delivery
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        <input type="checkbox"
                               name="status"
                               value="1"
                               class="custom-control-input"
                               id="status"
                               @checked(old('status', $product->status ?? true))>

                        <label class="custom-control-label" for="status">
                            Active
                        </label>
                    </div>
                </div>
            </div>

            <hr>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label>Short Description</label>

                <textarea name="short_description"
                          class="form-control"
                          rows="4"
                          placeholder="Short description">{{ old('short_description', $product->short_description ?? '') }}</textarea>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label>Full Description</label>

                <textarea name="full_description"
                          class="form-control"
                          rows="7"
                          placeholder="Full description">{{ old('full_description', $product->full_description ?? '') }}</textarea>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Meta Title</label>

                <input type="text"
                       name="meta_title"
                       value="{{ old('meta_title', $product->meta_title ?? '') }}"
                       class="form-control">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Meta Description</label>

                <textarea name="meta_description"
                          class="form-control"
                          rows="3">{{ old('meta_description', $product->meta_description ?? '') }}</textarea>
            </div>
        </div>
    </div>

    <div class="border-top pt-3">
        <button type="submit" class="btn btn-success px-4">
            <i class="fas fa-save mr-1"></i>
            {{ $isEdit ? 'Update Product' : 'Create Product' }}
        </button>

        <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>
            Cancel
        </button>
    </div>
</form>