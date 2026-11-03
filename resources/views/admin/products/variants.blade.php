@extends('adminlte::page')

@section('title', 'Product Variants - ' . $product->name)

@section('content_header')
<div class="d-flex align-items-center justify-content-between flex-wrap">
    <div>
        <h1 class="mb-1">Variants: {{ $product->name }}</h1>
        <p class="text-muted mb-0">SKU, option combinations, exact prices and audited inventory.</p>
    </div>
    <div class="btn-group mt-2 mt-md-0">
        <a class="btn btn-outline-primary" href="{{ route('admin.products.edit', $product->id) }}">Product Edit</a>
        <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">All Products</a>
        <a class="btn btn-outline-success" href="{{ route('admin.products.options.index') }}">Option Values</a>
    </div>
</div>
@endsection

@section('content')
<div class="alert alert-info" role="note">
    <strong>Managed variants:</strong> Admin-only on the verified test/local database or approved P58 production database. On a live inventory database, only a NEW zero-stock product without campaign/order history can be prepared. Legacy campaign inventory remains separate.
    Conversion temporarily deactivates the product. After checking a complete, stocked SKU combination and checkout readiness, activate it explicitly. No historical orders or inventory are rewritten.
    Newly added variants start at zero stock, then use the exact Stock field to enter their current sellable pack quantity. Never assume a 2-piece pack is the same as cart quantity 2.
</div>
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Please review:</strong>
        <ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif

<div class="row">
    <div class="col-lg-4 mb-3">
        <section class="card shadow-sm h-100">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Catalog State</h2></div>
            <div class="card-body">
                <dl class="row mb-1">
                    <dt class="col-6">Type</dt><dd class="col-6">{{ $product->has_variants ? 'Variable' : 'Simple' }}</dd>
                    <dt class="col-6">Inventory</dt><dd class="col-6">{{ (int) $product->inventory_version === 1 ? 'Managed' : 'Legacy' }}</dd>
                    <dt class="col-6">Total stock</dt><dd class="col-6">{{ number_format($product->stock) }}</dd>
                    <dt class="col-6">Base BDT</dt><dd class="col-6">{{ number_format($product->new_price) }}</dd>
                </dl>
                @if($product->has_variants && (int) $product->inventory_version === 1)
                    <div class="border-top mt-3 pt-3">
                        <h3 class="h6 font-weight-bold">Storefront availability</h3>
                        <p class="small mb-2">Product: <span class="badge {{ $product->status ? 'badge-success' : 'badge-warning' }}">{{ $product->status ? 'Active' : 'Inactive' }}</span></p>
                        @if($product->status)
                            <form method="POST" action="{{ route('admin.products.variants.pause', $product->id) }}">
                                @csrf
                                <button class="btn btn-outline-warning btn-block" type="submit">Pause Variable Product</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.products.variants.activate', $product->id) }}">
                                @csrf
                                <button class="btn btn-success btn-block" type="submit">Validate &amp; Activate for Storefront</button>
                            </form>
                        @endif
                        <p class="small text-muted mt-2 mb-0">Requires a valid category, complete active options, positive audited SKU stock and checkout readiness on the approved database. Production sales still require their independent P58 release gate.</p>
                    </div>
                @endif
                @if((int) $product->inventory_version === 0)
                    <form method="POST" action="{{ route('admin.products.variants.prepare', $product->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block">Prepare Default Inventory</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Creates one legacy SKU and an opening ledger; historic orders unchanged.</p>
                @elseif(! $product->has_variants)
                    <form method="POST" action="{{ route('admin.products.variants.convert', $product->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-block">Convert to Variable</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Requires zero stock and no managed order reference to the default SKU.</p>
                @else
                    <p class="small text-success mb-0">Ready to configure exact option combinations.</p>
                @endif
            </div>
        </section>
    </div>
    <div class="col-lg-8 mb-3">
        <section class="card card-outline card-success shadow-sm h-100" id="add-variation" aria-labelledby="add-variation-heading">
            <div class="card-header bg-white">
                <h2 class="h5 font-weight-bold text-success mb-1" id="add-variation-heading">
                    <i class="fas fa-plus-circle mr-1" aria-hidden="true"></i> Add New Variation
                </h2>
                <p class="small text-muted mb-0">Same product, new SKU. Copy existing choices, change Piece Count (or another option), then set this SKU's own price and stock.</p>
            </div>
            <div class="card-body">
            @if($product->has_variants && (int) $product->inventory_version === 1 && $options->isNotEmpty())
                @php
                    $copyableVariants = $variants->filter(fn ($variant) => $variant->option_signature !== 'default' && $variant->optionValues->isNotEmpty())->values();
                    $preferredCopy = $copyableVariants->first(fn ($variant) => $variant->is_active
                        && $variant->optionValues->every(fn ($value) => $value->is_active && $value->option?->is_active))
                        ?? $copyableVariants->first();
                    $existingVariantChoices = $copyableVariants->map(function ($variant) {
                        $selectedValues = [];
                        foreach ($variant->optionValues as $value) {
                            if ($value->option && isset(\App\Services\Catalog\VariantCatalogManager::OPTIONS[$value->option->code])) {
                                $selectedValues[$value->option->code] = (string) $value->id;
                            }
                        }
                        return ['id' => $variant->id, 'sku' => $variant->sku, 'values' => $selectedValues];
                    })->all();
                @endphp
                <form method="POST" id="variant-quick-add-form"
                      action="{{ route('admin.products.variants.store', $product->id) }}"
                      data-existing-variants="{{ json_encode($existingVariantChoices, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                      data-old-input="{{ old('quick_add') ? '1' : '0' }}">
                    @csrf
                    <input type="hidden" name="quick_add" value="1">

                    <div class="form-group">
                        <label for="variant-copy-from" class="font-weight-bold">1. Copy option choices from an existing SKU</label>
                        <select id="variant-copy-from" class="form-control">
                            <option value="">Select options manually</option>
                            @foreach($copyableVariants as $copyVariant)
                                <option value="{{ $copyVariant->id }}" @selected($preferredCopy?->id === $copyVariant->id)>
                                    {{ $copyVariant->sku }} — {{ $copyVariant->optionValues->map(fn ($value) => $value->label)->implode(' / ') }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Only Color / Size / Weight / Piece Count are copied. Price, stock and SKU code are never copied.</small>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <p class="font-weight-bold mb-2">2. Choose the new combination</p>
                        <div class="form-row">
                            @foreach($options as $option)
                                @php
                                    $copyValueId = $preferredCopy?->optionValues->first(fn ($value) => $value->option?->code === $option->code)?->id;
                                    $chosenId = old('options.' . $option->code, $copyValueId ?? '');
                                @endphp
                                <div class="form-group col-md-6 col-xl-3">
                                    <label for="quick-option-{{ $option->code }}">{{ $option->name }}@if($product->status) <span class="text-danger">*</span>@endif</label>
                                    <select id="quick-option-{{ $option->code }}" name="options[{{ $option->code }}]"
                                        data-variant-option="{{ $option->code }}"
                                        class="form-control @error('options.'.$option->code) is-invalid @enderror"
                                        @if($product->status) required @endif>
                                        <option value="">Not selected</option>
                                        @foreach($option->values as $value)
                                            <option value="{{ $value->id }}" @selected((string) $chosenId === (string) $value->id)>{{ $value->label }}</option>
                                        @endforeach
                                    </select>
                                    @error('options.'.$option->code)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                        <p class="small mb-3 text-muted" role="status" aria-live="polite" data-variant-selection-status>
                            Change at least one option, such as 1 Piece to 2 Pieces, to make a new SKU.
                        </p>
                        @error('options')<p class="text-danger small">{{ $message }}</p>@enderror
                        <a class="small" href="{{ route('admin.products.options.index') }}" target="_blank" rel="noopener">Missing a Color, Size, Weight or Piece Count value? Manage Option Values</a>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <p class="font-weight-bold mb-2">3. Set price and stock for this new SKU</p>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="variant-price">Selling Price (BDT) <span class="text-danger">*</span></label>
                                <input id="variant-price" type="number" min="1" max="4294967295" step="1"
                                    name="price_override" value="{{ old('price_override') }}" required
                                    class="form-control @error('price_override') is-invalid @enderror" placeholder="e.g. 850">
                                @error('price_override')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label for="variant-old-price">Old Price (optional)</label>
                                <input id="variant-old-price" type="number" min="0" max="4294967295" step="1"
                                    name="old_price_override" value="{{ old('old_price_override') }}"
                                    class="form-control @error('old_price_override') is-invalid @enderror" placeholder="Leave blank">
                                @error('old_price_override')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label for="new-variant-stock">Stock (exact packs) <span class="text-danger">*</span></label>
                                <input id="new-variant-stock" type="number" min="0" max="1000000" step="1"
                                    name="stock" value="{{ old('stock', 0) }}" required
                                    class="form-control @error('stock') is-invalid @enderror" aria-describedby="new-variant-stock-help">
                                <small id="new-variant-stock-help" class="text-muted">2-piece stock counts packs, not single pieces.</small>
                                @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="variant-sku">SKU Code (optional)</label>
                            <input id="variant-sku" type="text" name="sku" maxlength="100" autocomplete="off"
                                pattern="[A-Za-z0-9][A-Za-z0-9._-]*" value="{{ old('sku') }}"
                                class="form-control @error('sku') is-invalid @enderror" placeholder="Leave blank for an automatically generated unique SKU">
                            <small class="form-text text-muted">One unique SKU per combination. Existing codes cannot be reused.</small>
                            @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" class="custom-control-input" name="is_active" value="1" id="variant-active" @checked((bool) old('is_active', true))>
                            <label for="variant-active" class="custom-control-label">Active (available when in stock and the product is active)</label>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg" data-save-variant>
                            <i class="fas fa-plus-circle mr-1" aria-hidden="true"></i> Save New Variation
                        </button>
                        <p class="small text-muted mt-2 mb-0">Creating this SKU never changes the previous SKU's price, historical orders or stock. An exact duplicate is rejected even if it was deactivated.</p>
                    </div>
                </form>
            @elseif(! $product->has_variants || (int) $product->inventory_version !== 1)
                <p class="text-muted mb-0">Prepare and convert the product to managed Variable inventory before creating option combinations. Existing stock and order history must be reconciled safely.</p>
            @else
                <p class="text-warning mb-0">No active option values are available. Use <a href="{{ route('admin.products.options.index') }}">Variant Options</a> to configure values before adding SKUs.</p>
            @endif
            </div>
        </section>
    </div>
</div>

{{-- Bulk SKU builder: no inferred prices, no guessed inventory, no schema changes. --}}
<section class="card card-outline card-primary shadow-sm" id="bulk-variations" aria-labelledby="bulk-variations-heading">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2 class="h5 font-weight-bold text-primary mb-1" id="bulk-variations-heading">
                <i class="fas fa-th mr-1" aria-hidden="true"></i> Build Multiple Variations
            </h2>
            <p class="text-muted small mb-0">Choose the Color, Size, Weight and Piece Count values your customers should be able to combine. Review every new SKU's exact price and stock before saving.</p>
        </div>
        <span class="badge badge-light">{{ $remainingVariantSlots }} of 200 SKU slots remaining</span>
    </div>
    <div class="card-body">
        @if($product->has_variants && (int) $product->inventory_version === 1 && $matrixOptions->isNotEmpty() && $remainingVariantSlots > 0)
            @php
                $usedMatrixValues = collect($existingMatrix)->flatMap(static fn (array $row) => array_values($row['values']))
                    ->map(static fn ($id) => (int) $id)->unique()->all();
            @endphp
            <form id="bulk-variant-form" method="POST"
                action="{{ route('admin.products.variants.bulk', $product->id) }}"
                data-existing-matrix="{{ json_encode($existingMatrix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}"
                data-old-rows="{{ json_encode(old('variants', []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}"
                data-remaining-slots="{{ $remainingVariantSlots }}" data-batch-limit="60">
                @csrf
                <h3 class="h6 font-weight-bold">Step 1 — Choose values to offer</h3>
                <p class="small text-muted">For example, select Red + Black, L + XL, 10g and 1 Piece + 2 Pieces. Existing combinations are automatically skipped; only missing SKUs will be offered for creation.</p>
                <div class="row">
                    @foreach($matrixOptions as $option)
                        <fieldset class="col-md-6 col-xl-3 mb-3" data-bulk-option-group="{{ $option->code }}">
                            <legend class="h6 font-weight-bold mb-2">{{ $option->name }}</legend>
                            @foreach($option->values as $value)
                                <label class="d-flex align-items-center mb-2" style="gap:.5rem;cursor:pointer">
                                    <input type="checkbox" class="m-0" data-bulk-option="{{ $option->code }}"
                                        value="{{ $value->id }}" data-bulk-label="{{ $value->label }}"
                                        @checked(in_array((int) $value->id, $usedMatrixValues, true) || ($usedMatrixValues === [] && $loop->first))>
                                    <span>{{ $value->label }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline-primary" id="bulk-matrix-preview">
                    <i class="fas fa-table mr-1" aria-hidden="true"></i> Preview Missing Combinations
                </button>
                <div class="alert alert-light border mt-3" id="bulk-matrix-status" role="status" aria-live="polite">
                    Select the options above, then preview the missing combinations.
                </div>
                <div id="bulk-matrix-review" hidden>
                    <div class="border-top pt-3 mt-3">
                        <h3 class="h6 font-weight-bold">Step 2 — Approve each SKU's price and exact stock</h3>
                        <p class="small text-muted">Prices must be entered for every new variation. Stock is counted in sellable packs, not individual pieces. Nothing is copied from existing SKU stock.</p>
                        <div class="form-row align-items-end mb-3 p-2 bg-light rounded">
                            <div class="form-group col-md-3 mb-2">
                                <label for="bulk-price-all">Price for every pending SKU (optional)</label>
                                <input id="bulk-price-all" type="number" min="1" max="4294967295" step="1" class="form-control form-control-sm" placeholder="e.g. 850">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label for="bulk-stock-all">Exact stock for each (optional)</label>
                                <input id="bulk-stock-all" type="number" min="0" max="1000000" step="1" class="form-control form-control-sm" placeholder="e.g. 10">
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <button class="btn btn-outline-secondary btn-sm" id="bulk-apply-values" type="button">Apply entered values to all rows</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="thead-light"><tr>
                                    <th scope="col">New combination</th>
                                    <th scope="col" style="min-width:130px">Price (BDT) *</th>
                                    <th scope="col" style="min-width:125px">Old price</th>
                                    <th scope="col" style="min-width:125px">Stock (packs) *</th>
                                </tr></thead>
                                <tbody id="bulk-matrix-rows"></tbody>
                            </table>
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <label class="d-flex align-items-center mb-3" style="gap:.5rem;cursor:pointer">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Create as Active (new SKUs are still unavailable for checkout until their stock is positive and the product is active)</span>
                        </label>
                        <button type="submit" id="bulk-matrix-save" class="btn btn-primary btn-lg">Create Reviewed Variations</button>
                        <p class="small text-muted mt-2 mb-0">All rows save together or none do. Duplicate/archived SKUs are not overwritten. Existing stock, orders, campaigns and checkout are unchanged.</p>
                    </div>
                </div>
            </form>
        @elseif($remainingVariantSlots === 0)
            <div class="alert alert-warning mb-0">The SKU limit of 200 per product has been reached (including archived SKUs).</div>
        @else
            <p class="text-muted mb-0">This builder is available after the product is prepared as a managed Variable product with active option values.</p>
        @endif
    </div>
</section>

{{-- Read-only catalog snapshot. Existing SKU edits belong to Product Create/Edit. --}}
<section class="card shadow-sm" aria-labelledby="current-skus-heading">
    <div class="card-header bg-white">
        <h2 class="h5 mb-1" id="current-skus-heading">Current SKUs ({{ $variants->count() }})</h2>
        <p class="small text-muted mb-0" id="current-skus-description">
            View only. Manage existing SKU prices and stock from Product Create/Edit; add new combinations using the forms above.
        </p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0" aria-describedby="current-skus-description">
                <thead class="thead-light">
                    <tr>
                        <th scope="col">SKU</th>
                        <th scope="col">Color</th>
                        <th scope="col">Size</th>
                        <th scope="col">Weight</th>
                        <th scope="col">Piece Count</th>
                        <th scope="col">Price (BDT)</th>
                        <th scope="col">Old Price (BDT)</th>
                        <th scope="col">Stock</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($variants as $variant)
                        @php
                            $effectivePrice = $variant->price_override ?? $product->new_price;
                            $effectiveOldPrice = $variant->old_price_override ?? $product->old_price;
                        @endphp
                        <tr>
                            <th scope="row" class="font-weight-normal">
                                <span class="font-weight-bold">{{ $variant->sku }}</span>
                                @if($variant->is_default)
                                    <span class="badge badge-light border ml-1">Default SKU</span>
                                @endif
                            </th>
                            <td>{{ $variant->optionValues->first(fn ($value) => $value->option?->code === 'color')?->label ?? '—' }}</td>
                            <td>{{ $variant->optionValues->first(fn ($value) => $value->option?->code === 'size')?->label ?? '—' }}</td>
                            <td>{{ $variant->optionValues->first(fn ($value) => $value->option?->code === 'weight')?->label ?? '—' }}</td>
                            <td>{{ $variant->optionValues->first(fn ($value) => $value->option?->code === 'piece_count')?->label ?? '—' }}</td>
                            <td>
                                {{ number_format((float) $effectivePrice) }}
                                @if($variant->price_override === null)
                                    <small class="text-muted d-block">Base price</small>
                                @endif
                            </td>
                            <td>{{ $effectiveOldPrice !== null && (float) $effectiveOldPrice > 0 ? number_format((float) $effectiveOldPrice) : '—' }}</td>
                            <td><span class="badge badge-light border">{{ number_format((int) $variant->stock) }}</span></td>
                            <td>
                                <span class="badge {{ $variant->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $variant->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-muted text-center py-4">No variants found. Prepare default inventory to begin.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection


@section('js')
<script src="{{ asset('frontend/js/admin-variant-quick-add.js') }}" defer></script>
<script src="{{ asset('frontend/js/admin-variant-matrix.js') }}?v=20261103-combo" defer></script>
@stop
