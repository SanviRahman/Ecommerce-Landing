@extends('adminlte::page')

@section('title', 'Product Variants - ' . $product->name)

@section('content_header')
<div class="d-flex align-items-center justify-content-between flex-wrap">
    <div>
        <h1 class="mb-1">Variants: {{ $product->name }}</h1>
        <p class="text-muted mb-0">SKU, option combinations, exact prices and audited inventory.</p>
    </div>
    <div class="btn-group mt-2 mt-md-0">
        <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">All Products</a>
        <a class="btn btn-outline-success" href="{{ route('admin.products.options.index') }}">Option Values</a>
    </div>
</div>
@endsection

@section('content')
<div class="alert alert-info" role="note">
    <strong>P04 isolated test-only administration.</strong> Variable products are not yet purchasable through the legacy campaign checkout.
    A conversion automatically deactivates the product until variant-aware checkout is released.
    Every variant starts at zero stock. Never assume a 2-piece pack is the same as cart quantity 2.
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
        <section class="card shadow-sm h-100">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Add Variant</h2></div>
            <div class="card-body">
            @if($product->has_variants && (int) $product->inventory_version === 1)
                <form method="POST" action="{{ route('admin.products.variants.store', $product->id) }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="variant-sku">SKU</label>
                            <input id="variant-sku" type="text" name="sku" required maxlength="100" value="{{ old('sku') }}" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" class="form-control" placeholder="SKU-1KG-BLK">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="variant-price">Price BDT (optional)</label>
                            <input id="variant-price" type="number" min="0" name="price_override" value="{{ old('price_override') }}" class="form-control" placeholder="Inherit base price">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="variant-old-price">Old BDT (optional)</label>
                            <input id="variant-old-price" type="number" min="0" name="old_price_override" value="{{ old('old_price_override') }}" class="form-control">
                        </div>
                        @foreach($options as $option)
                        <div class="form-group col-md-3 col-sm-6">
                            <label for="option-{{ $option->id }}">{{ $option->name }}</label>
                            <select id="option-{{ $option->id }}" name="options[{{ $option->code }}]" class="form-control">
                                <option value="">Not selected</option>
                                @foreach($option->values as $value)
                                <option value="{{ $value->id }}" @selected(old('options.' . $option->code) == $value->id)>{{ $value->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endforeach
                    </div>
                    <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" class="custom-control-input" name="is_active" value="1" id="variant-active" @checked(old('is_active', true))>
                        <label for="variant-active" class="custom-control-label">Variant active</label>
                    </div>
                    <button type="submit" class="btn btn-success">Create Zero-Stock Variant</button>
                    <p class="small text-muted mt-2 mb-0">Choose at least one dimension. Missing prices inherit the product base price; zero is an explicit zero.</p>
                </form>
            @else
                <p class="text-muted mb-0">Prepare and convert the product before adding size/color/weight/pack combinations.</p>
            @endif
            </div>
        </section>
    </div>
</div>

<section class="card shadow-sm">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Current SKUs ({{ $variants->count() }})</h2></div>
    <div class="card-body">
        @forelse($variants as $variant)
        <article class="border rounded p-3 mb-3" aria-labelledby="variant-name-{{ $variant->id }}">
            <div class="d-flex justify-content-between flex-wrap align-items-start mb-2">
                <div>
                    <h3 id="variant-name-{{ $variant->id }}" class="h6 font-weight-bold mb-1">{{ $variant->sku }}</h3>
                    <p class="small text-muted mb-0">
                        @forelse($variant->optionValues as $value)
                            {{ $value->option?->name ?? 'Option' }}: {{ $value->label }}{{ !$loop->last ? ' · ' : '' }}
                        @empty
                            {{ $variant->is_default ? 'Simple default SKU' : 'No options selected' }}
                        @endforelse
                    </p>
                </div>
                <div class="text-right">
                    <span class="badge {{ $variant->is_active ? 'badge-success' : 'badge-secondary' }}">{{ $variant->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="badge badge-light">Stock: {{ $variant->stock }}</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-7 mb-2">
                    @if(!$variant->is_default && $variant->option_signature !== 'default' && $product->has_variants)
                    <form method="POST" action="{{ route('admin.products.variants.update', [$product->id, $variant->id]) }}">
                        @csrf @method('PATCH')
                        <div class="form-row align-items-end">
                            <div class="form-group col-sm-4">
                                <label for="sku-{{ $variant->id }}">SKU</label>
                                <input id="sku-{{ $variant->id }}" type="text" name="sku" value="{{ $variant->sku }}" required maxlength="100" class="form-control form-control-sm">
                            </div>
                            <div class="form-group col-sm-4">
                                <label for="price-{{ $variant->id }}">BDT price</label>
                                <input id="price-{{ $variant->id }}" type="number" min="0" name="price_override" value="{{ $variant->price_override }}" class="form-control form-control-sm" placeholder="Inherit">
                            </div>
                            <div class="form-group col-sm-4">
                                <label for="old-{{ $variant->id }}">Old BDT</label>
                                <input id="old-{{ $variant->id }}" type="number" min="0" name="old_price_override" value="{{ $variant->old_price_override }}" class="form-control form-control-sm" placeholder="Inherit">
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="active-{{ $variant->id }}" @checked($variant->is_active)>
                                <label for="active-{{ $variant->id }}" class="custom-control-label">Active</label>
                            </div>
                            <button type="submit" class="btn btn-outline-success btn-sm">Save Variant</button>
                        </div>
                    </form>
                    @else
                    <p class="small text-muted mb-0">Default SKU; pricing follows Product Edit and cannot be removed here.</p>
                    @endif
                </div>
                <div class="col-lg-5 border-left mb-2">
                    <form method="POST" action="{{ route('admin.products.variants.stock', [$product->id, $variant->id]) }}">
                        @csrf
                        <input type="hidden" name="expected_stock" value="{{ $variant->stock }}">
                        <div class="form-row">
                            <div class="form-group col-sm-4">
                                <label for="delta-{{ $variant->id }}">Stock + / -</label>
                                <input id="delta-{{ $variant->id }}" type="number" name="delta" min="-1000000" max="1000000" required class="form-control form-control-sm" placeholder="+10 / -2">
                            </div>
                            <div class="form-group col-sm-8">
                                <label for="reason-{{ $variant->id }}">Reason (min 8 chars)</label>
                                <input id="reason-{{ $variant->id }}" type="text" name="reason" minlength="8" maxlength="255" required class="form-control form-control-sm" placeholder="Warehouse count correction">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm">Save Audited Stock Delta</button>
                    </form>
                </div>
            </div>
        </article>
        @empty
        <p class="text-muted mb-0">No variants found. Prepare default inventory to begin.</p>
        @endforelse
    </div>
</section>
@endsection
