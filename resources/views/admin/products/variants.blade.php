@extends('adminlte::page')
@section('title', 'Option-wise Product Pricing')
@section('content_header')
<h1><i class="fas fa-tags text-success mr-2" aria-hidden="true"></i> Option-wise Product Pricing</h1>
<p class="text-muted">{{ $product->name }} — Color, Size and Weight are optional. Select Piece Count and set its price to sell this product.</p>
@stop
@section('content')
<a class="btn btn-outline-secondary mb-3" href="{{ route('admin.products.index') }}">Back to Products</a>
<a class="btn btn-outline-success mb-3" href="{{ route('admin.stock.index') }}">Stock Management</a>
<a class="btn btn-outline-primary mb-3" href="{{ route('admin.products.options.index') }}">Manage Option Values</a>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card shadow-sm border-top border-success" id="option-wise-pricing">
 <div class="card-header"><h2 class="h5 mb-0">Select options and set prices</h2></div>
 <div class="card-body">
  <p class="text-muted">Leave Color, Size or Weight unselected if this product does not use them. Selected choices can be combined freely; only Piece Count changes the price. Stock belongs to the product, not each combination.</p>
  @if((int)$product->inventory_version === 0 && ! $product->has_variants)
    <div class="alert alert-info" role="note">
      <strong>Adding options to an existing Simple Product:</strong>
      Select a Piece Count and set its price below. Saving will prepare one shared product inventory balance using the existing {{ number_format((int)$product->stock) }} units, without increasing or resetting stock. Existing product details and past orders are retained. If there are unfinished legacy orders, complete or reconcile them before enabling Option-wise Pricing.
    </div>
  @endif
  @if(! in_array((int)$product->inventory_version, [0, 1], true) || $product->has_variants)
    <div class="alert alert-warning">This product uses an unsupported legacy variation setup. Reconcile its inventory before assigning Option-wise prices.</div>
  @else
  <form method="POST" action="{{ route('admin.products.variants.option_prices', $product) }}">
   @csrf
   @if($prefilledOnePiecePrice)
    <p class="alert alert-light border small" role="note">
     <i class="fas fa-info-circle text-success mr-1" aria-hidden="true"></i>
     1 Piece is preselected at the Product Selling Price (৳{{ number_format((int) $product->new_price) }}).
     You can change the price before saving.
    </p>
   @endif
   <div class="row">
    @foreach($options as $option)
    <fieldset class="col-lg-6 mb-4">
      <legend class="h6 font-weight-bold">{{ $option->name }} <span class="small text-muted">({{ $option->code === 'piece_count' ? 'required' : 'optional' }})</span></legend>
      @php
        $saved = $configuredSelections[$option->code] ?? [];
        $selected = array_map('intval', (array)old('options.'.$option->code, $saved));
      @endphp
      @foreach($option->values as $value)
       <div class="custom-control custom-checkbox mb-2">
        <input class="custom-control-input" type="checkbox" id="p-option-{{ $value->id }}"
         name="options[{{ $option->code }}][]" value="{{ $value->id }}" @checked(in_array((int)$value->id, $selected, true))>
        <label class="custom-control-label" for="p-option-{{ $value->id }}">{{ $value->label }}</label>
       </div>
       @if($option->code === 'piece_count')
        <label for="p-price-{{ $value->id }}">{{ $value->label }} — BDT price</label>
        <input id="p-price-{{ $value->id }}" class="form-control form-control-sm mb-3" type="number" min="1" max="4294967295"
         name="prices[{{ $value->id }}]" value="{{ old('prices.'.$value->id, $configuredPrices[$value->id] ?? '') }}" placeholder="e.g. 390">
       @endif
      @endforeach
      @if($option->values->isEmpty())<p class="text-muted">No active values yet. <a href="{{ route('admin.products.options.index') }}">Add values</a>.</p>@endif
    </fieldset>
    @endforeach
   </div>
   @if(! $product->status)
    <div class="custom-control custom-checkbox mb-3">
     <input type="hidden" name="activate_after_save" value="0">
     <input class="custom-control-input" type="checkbox" name="activate_after_save" value="1"
      id="activate-option-wise-product" @checked(old('activate_after_save', true))>
     <label class="custom-control-label font-weight-bold" for="activate-option-wise-product">
      Activate this product after saving Option-wise Prices
     </label>
     <small class="form-text text-muted">Checked by default. The product is activated only if prices save successfully. With zero available stock, customers still cannot order.</small>
    </div>
   @endif
   <button type="submit" class="btn btn-success btn-lg">Save Option-wise Prices</button>
   <a href="#saved-optionwise-prices" class="btn btn-outline-secondary ml-2">View Saved Prices</a>
  </form>
  @endif
 </div>
</div>
<div class="card"><div class="card-body"><strong>Physical stock:</strong> {{ number_format((int)$product->stock) }} units. <span class="text-muted">Manage purchases and adjustments from Stock Management. Confirmation deducts stock once.</span></div></div>

{{-- Saved choices and Piece Count prices for the current product. Purely read-only. --}}
<div class="card shadow-sm mt-3" id="saved-optionwise-prices">
 <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
  <h2 class="h5 mb-0"><i class="fas fa-list-alt text-success mr-2" aria-hidden="true"></i> Saved Option-wise Variations</h2>
  <span class="badge badge-light border mt-2 mt-sm-0">{{ $savedPiecePrices->count() }} price option(s)</span>
 </div>
 <div class="card-body">
  <p class="text-muted small">Saved choices for <strong>{{ $product->name }}</strong>. Color, Size and Weight are independent; only Piece Count changes the price. All choices use the same product stock.</p>
  <div class="row mb-3">
   @foreach(['color' => 'Color', 'size' => 'Size', 'weight' => 'Weight'] as $code => $name)
   <div class="col-md-4 mb-2">
    <div class="border rounded h-100 p-3">
     <div class="font-weight-bold mb-2">{{ $name }}</div>
     @forelse($configuredOptionLabels[$code] ?? [] as $choice)
      <span class="badge {{ $choice['active'] ? 'badge-light border' : 'badge-warning' }} mr-1 mb-1" @if(! $choice['active']) title="Inactive choice" @endif>{{ $choice['label'] }}</span>
     @empty
      <span class="text-muted small">Not configured</span>
     @endforelse
    </div>
   </div>
   @endforeach
  </div>
  <h3 class="h6 font-weight-bold mb-2">Piece Count price index</h3>
  <div class="table-responsive">
   <table class="table table-bordered table-striped table-hover mb-0">
    <caption class="sr-only">Saved Piece Count prices for {{ $product->name }}</caption>
    <thead class="thead-light">
     <tr><th scope="col" style="width: 70px">#</th><th scope="col">Piece Count</th><th scope="col" class="text-right">Price (BDT)</th><th scope="col" class="text-right">Old Price (BDT)</th><th scope="col">Status</th></tr>
    </thead>
    <tbody>
     @forelse($savedPiecePrices as $piece)
     <tr>
      <td>{{ $loop->iteration }}</td>
      <td class="font-weight-bold">{{ $piece->label }}</td>
      <td class="text-right font-weight-bold">৳{{ number_format((int) $piece->price) }}</td>
      <td class="text-right">{{ $piece->old_price === null ? '—' : '৳'.number_format((int) $piece->old_price) }}</td>
      <td><span class="badge {{ $piece->value_active && $piece->group_active ? 'badge-success' : 'badge-secondary' }}">{{ $piece->value_active && $piece->group_active ? 'Active' : 'Inactive' }}</span></td>
     </tr>
     @empty
     <tr><td colspan="5" class="text-center text-muted py-3">No Option-wise prices saved yet. Choose a Piece Count and save the prices above.</td></tr>
     @endforelse
    </tbody>
   </table>
  </div>
 </div>
</div>

{{-- All configured Option-wise products, with links back to this same editor. --}}
<div class="card shadow-sm mt-3" id="all-optionwise-products">
 <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
  <h2 class="h5 mb-0"><i class="fas fa-boxes text-success mr-2" aria-hidden="true"></i> All Option-wise Products</h2>
  <span class="badge badge-light border mt-2 mt-sm-0">{{ number_format($optionWiseProducts->total()) }} product(s)</span>
 </div>
 <div class="card-body p-0">
  <div class="table-responsive">
   <table class="table table-hover table-bordered mb-0">
    <caption class="sr-only">All products configured for Option-wise Pricing</caption>
    <thead class="thead-light"><tr>
     <th scope="col">Product</th><th scope="col">Code</th><th scope="col">Piece Prices</th>
     <th scope="col">Price Range (BDT)</th><th scope="col">Shared Stock</th>
     <th scope="col">Status</th><th scope="col">Action</th>
    </tr></thead>
    <tbody>
    @forelse($optionWiseProducts as $configuredProduct)
     <tr class="{{ (int)$configuredProduct->id === (int)$product->id ? 'table-success' : '' }}">
      <td class="font-weight-bold">{{ $configuredProduct->name }} @if((int)$configuredProduct->id === (int)$product->id)<span class="badge badge-success">Current</span>@endif</td>
      <td>{{ $configuredProduct->product_code ?: '—' }}</td>
      <td>{{ number_format((int)$configuredProduct->option_count) }}</td>
      <td>৳{{ number_format((int)$configuredProduct->min_price) }}@if((int)$configuredProduct->min_price !== (int)$configuredProduct->max_price) – ৳{{ number_format((int)$configuredProduct->max_price) }}@endif</td>
      <td>{{ number_format((int)$configuredProduct->stock) }}</td>
      <td><span class="badge {{ $configuredProduct->status ? 'badge-success' : 'badge-secondary' }}">{{ $configuredProduct->status ? 'Active' : 'Inactive' }}</span></td>
      <td><a class="btn btn-outline-success btn-sm" href="{{ route('admin.products.variants.index', $configuredProduct->id) }}#saved-optionwise-prices" aria-label="View Option-wise prices for {{ $configuredProduct->name }}">View prices</a></td>
     </tr>
    @empty
     <tr><td colspan="7" class="text-center text-muted py-3">No Option-wise products configured yet.</td></tr>
    @endforelse
    </tbody>
   </table>
  </div>
 </div>
 @if($optionWiseProducts->hasPages())
 <div class="card-footer d-flex justify-content-center pt-3 pb-1">
  {{ $optionWiseProducts->fragment('all-optionwise-products')->onEachSide(1)->links('pagination::bootstrap-4') }}
 </div>
 @endif
</div>
@stop
