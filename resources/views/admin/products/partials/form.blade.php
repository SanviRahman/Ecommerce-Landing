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

    @php
        $isManagedInventory = $isEdit && (int) ($product->inventory_version ?? 0) === 1;
        $managedSkuChoices = $isManagedInventory ? ($managedVariants ?? collect()) : collect();
        $requestedStockSku = old('stock_variant_id', $managedSkuChoices->first()?->id);
        $selectedStockSku = $managedSkuChoices->firstWhere('id', (int) $requestedStockSku)
            ?? $managedSkuChoices->first();
        $editingVariation = false; // The former per-SKU combination editor is retired from Product Edit.
        $selectedVariantOptions = $editingVariation
            ? $selectedStockSku->optionValues->keyBy(fn ($value) => $value->option?->code)
            : collect();
    @endphp

    <div class="alert alert-light border mb-3" role="note">
        <strong>Product option setup:</strong> Select an existing parent/sub-category below.
        <a href="{{ route('admin.categories.index') }}" target="_blank" rel="noopener">Manage sub-categories</a> ·
        <a href="{{ route('admin.products.options.index') }}" target="_blank" rel="noopener">Color / Size / Weight / Piece Count values</a>.
        @if($isEdit)
            <span class="small d-block mt-1">Set Color, Size, Weight and Piece Count from Option-wise Pricing. Stock is shared for this product, not divided by choice.</span>
        @else
            <span class="small d-block mt-1">For an Option-wise product, check “Set up Option-wise Pricing” below. You can enter opening Stock now or add it later from Stock Management. Color, Size and Weight are optional on the next screen; Piece Count and its price are required. Other products remain simple.</span>
        @endif
    </div>
    @if(!$isEdit && ($initialVariationEnabled ?? false))
        <div class="card border-success mb-3">
            <div class="card-body">
                <label class="d-flex align-items-center font-weight-bold" for="create-for-options" style="gap:.6rem">
                    <input type="checkbox" name="create_for_options" value="1" id="create-for-options" @checked(old('create_for_options', false))>
                    Set up Option-wise Pricing for this product
                </label>
                <p class="small text-muted mb-0">Enter opening Stock below (0 is also allowed), or add more later in Stock Management. After saving, choose any optional Color, Size and Weight values and set a required price for each offered Piece Count. Opening stock is audited. After valid Piece Count prices are saved, the product can automatically become Active (this is checked by default on the pricing screen).</p>
            </div>
        </div>
    @endif
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Product Name <span class="text-danger">*</span></label>

                <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}"
                    class="form-control @error('name') is-invalid @enderror" placeholder="Product name" required>

                @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        @if($isEdit)
        <div class="col-md-6">
            <div class="form-group">
                <label>Slug</label>

                <input type="text" name="slug" value="{{ old('slug', $product->slug ?? '') }}"
                    class="form-control @error('slug') is-invalid @enderror" placeholder="product-slug">

                @error('slug')
                <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>
        @endif

        <div class="col-md-6">
            <div class="form-group">
                <label>Category <span class="text-danger">*</span></label>

                <select name="category_id" class="form-control @error('category_id') is-invalid @enderror" required>
                    <option value="">Select Category</option>

                    @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') ==
                        $category->id)>
                        {{ $category->parent_id ? '↳ ' : '' }}{{ $category->name }}
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

                <select name="brand_id" class="form-control @error('brand_id') is-invalid @enderror">
                    <option value="">Select Brand</option>

                    @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id ?? '') == $brand->id)>
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

                <input type="text" name="product_code" value="{{ old('product_code', $product->product_code ?? '') }}"
                    class="form-control @error('product_code') is-invalid @enderror" placeholder="Example: PRD-001">

                @error('product_code')
                <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Purchase Price</label>

                <input type="number" name="purchase_price"
                    value="{{ old('purchase_price', $product->purchase_price ?? '') }}" class="form-control" min="0"
                    placeholder="0">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Old Price</label>

                <input type="number" name="old_price" value="{{ old('old_price', $product->old_price ?? '') }}"
                    class="form-control" min="0" placeholder="0">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label for="base-product-new-price">New Price <span class="text-danger">*</span></label>

                <input type="number" name="new_price" id="base-product-new-price" value="{{ old('new_price', $product->new_price ?? '') }}"
                    class="form-control @error('new_price') is-invalid @enderror" min="0" max="4294967295" required placeholder="Selling price">

                @error('new_price')
                <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                @if($isManagedInventory && $selectedStockSku)
                    <label for="managed-stock-sku" class="sr-only">Internal product inventory reference</label>
                    <select name="stock_variant_id" id="managed-stock-sku" class="form-control mb-2" hidden>
                        @foreach($managedSkuChoices as $stockSku)
                            @php
                                $stockSkuLabel = $stockSku->optionValues->map(fn ($value) => ($value->option?->name ?? 'Option').': '.$value->label)->implode(' / ');
                            @endphp
                            <option value="{{ $stockSku->id }}" data-current-stock="{{ $stockSku->stock }}"
                                data-variation-signature="{{ $stockSku->option_signature }}"
                                @foreach(['color', 'size', 'weight', 'piece_count'] as $optionCode)
                                    data-option-{{ $optionCode }}="{{ $stockSku->optionValues->first(fn ($value) => $value->option?->code === $optionCode)?->id ?? '' }}"
                                @endforeach
                                @selected((int) $selectedStockSku->id === (int) $stockSku->id)>
                                Shared product stock
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="expected_variant_stock" id="expected-variant-stock"
                        value="{{ old('expected_variant_stock', $selectedStockSku->stock) }}">
                @endif
                <label for="base-product-stock">Stock {{ $isManagedInventory ? '(product total)' : (! $isEdit ? '(opening quantity)' : '') }}</label>
                <input type="number" name="stock" id="base-product-stock"
                    value="{{ old('stock', $isManagedInventory ? ($selectedStockSku?->stock ?? $product->stock) : ($product->stock ?? 0)) }}"
                    class="form-control @error('stock') is-invalid @enderror"
                    min="0" max="1000000" step="1" placeholder="0"
                    @if($isManagedInventory && ! $selectedStockSku) disabled @endif>
                @error('stock')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
                @if($isManagedInventory)
                    <small class="text-muted d-block">Total product stock: {{ $product->stock }}. Adjustments are recorded in the existing inventory ledger.</small>
                @else
                    <small class="text-muted d-block">Enter the exact opening quantity (0 or more). Option-wise products record this stock in the inventory ledger; you can add or adjust it later in Stock Management.</small>
                @endif
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Sold Quantity</label>

                <input type="number" name="sold_quantity"
                    value="{{ old('sold_quantity', $product->sold_quantity ?? 0) }}" class="form-control" min="0"
                    placeholder="0">
            </div>
        </div>

        {{-- Legacy simple products need their historical free-text Weight / Size.
             Do not show it next to the managed Weight and Size dropdowns. --}}
        @if(($isEdit && ! ($product->has_variants ?? false)) || (! $isEdit && ! ($initialVariationEnabled ?? false)))
        <div class="col-md-4">
            <div class="form-group">
                <label for="legacy-weight-size">Weight / Size (legacy)</label>
                <input type="text" id="legacy-weight-size" name="weight_size"
                    value="{{ old('weight_size', $product->weight_size ?? '') }}"
                    class="form-control" maxlength="255" placeholder="Example: 500gm">
            </div>
        </div>
        @endif

        <div class="col-md-4">
            <div class="form-group">
                <label>Product Thumbnail</label>

                <input type="file" name="product_thumbnail"
                    class="form-control @error('product_thumbnail') is-invalid @enderror" accept="image/*">

                @error('product_thumbnail')
                <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror

                @if($isEdit && !empty($product?->thumbnail))
                <div class="mt-2">
                    <img src="{{ $product->thumbnail }}" alt="{{ $product->name }}" width="120" class="img-thumbnail">
                </div>
                @endif
            </div>
        </div>

        <div class="col-md-8">
            <div class="form-group">
                <label>Product Gallery</label>

                <input type="file" name="product_gallery[]" class="form-control" accept="image/*" multiple>

                @error('product_gallery')
                <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror

                @error('product_gallery.*')
                <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror

                @if($isEdit && $product && method_exists($product, 'getMedia'))
                <div class="row mt-2" id="productGalleryPreviewWrapper">
                    @foreach($product->getMedia('product_gallery') as $media)
                    <div class="col-md-3 mb-2" id="product-media-box-{{ $media->id }}">
                        <div class="border rounded p-1 bg-white">
                            <img src="{{ $media->getUrl() }}" class="img-fluid rounded" alt="Gallery Image"
                                style="height:90px;width:100%;object-fit:cover;">

                            <button type="button" class="btn btn-xs btn-danger btn-block mt-1 btnDeleteProductMedia"
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
                        <input type="checkbox" name="is_top_sale" value="1" class="custom-control-input"
                            id="is_top_sale" @checked(old('is_top_sale', $product->is_top_sale ?? false))>

                        <label class="custom-control-label" for="is_top_sale">
                            Top Sale
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_feature" value="1" class="custom-control-input" id="is_feature"
                            @checked(old('is_feature', $product->is_feature ?? false))>

                        <label class="custom-control-label" for="is_feature">
                            Featured
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_flash_sale" value="1" class="custom-control-input"
                            id="is_flash_sale" @checked(old('is_flash_sale', $product->is_flash_sale ?? false))>

                        <label class="custom-control-label" for="is_flash_sale">
                            Flash Sale
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_free_delivery" value="1" class="custom-control-input"
                            id="is_free_delivery" @checked(old('is_free_delivery', $product->is_free_delivery ??
                        false))>

                        <label class="custom-control-label" for="is_free_delivery">
                            Free Delivery
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="custom-control custom-switch">
                        {{-- Explicit OFF value: unchecked switches must not default to Active. --}}
                        <input type="hidden" name="status" value="0">
                        <input type="checkbox" name="status" value="1" class="custom-control-input" id="status"
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

                <textarea name="short_description" class="form-control" rows="4"
                    placeholder="Short description">{{ old('short_description', $product->short_description ?? '') }}</textarea>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label>Full Description</label>

                <textarea name="full_description" class="form-control" rows="7"
                    placeholder="Full description">{{ old('full_description', $product->full_description ?? '') }}</textarea>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Meta Title</label>

                <input type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title ?? '') }}"
                    class="form-control">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Meta Description</label>

                <textarea name="meta_description" class="form-control"
                    rows="3">{{ old('meta_description', $product->meta_description ?? '') }}</textarea>
            </div>
        </div>
    </div>

    @if($isEdit && ($initialVariationEnabled ?? false) && ! ($product->has_variants ?? false))
        <p class="alert alert-light border" role="note">
            @if($isManagedInventory)
                To change Piece Count prices, open
                <a href="{{ route('admin.products.variants.index', $product->id) }}#option-wise-pricing">Option-wise Pricing</a>.
                Shared physical stock can be updated here or in Stock Management.
            @else
                Want to offer Color, Size, Weight or differently priced Piece Counts later?
                <a href="{{ route('admin.products.variants.index', $product->id) }}#option-wise-pricing">Add Option-wise Pricing</a>
                after saving this edit. The current stock will be kept; there is no need to create another product.
            @endif
        </p>
    @endif

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