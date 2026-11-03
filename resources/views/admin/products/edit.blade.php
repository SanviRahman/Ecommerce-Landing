@extends('adminlte::page')

@section('title', $title ?? 'Edit Product')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <h1 class="h3 mb-1">{{ $title ?? 'Edit Product' }}</h1>
    <div class="d-flex align-items-center flex-wrap" style="gap:.5rem">
        @if((int) ($product->inventory_version ?? 0) === 1 && auth()->user()?->isAdmin())
            @if(($initialVariationEnabled ?? false) && $product->has_variants)
            <a class="btn btn-success btn-sm" href="{{ route('admin.products.variants.index', $product->id) }}#add-variation"><i class="fas fa-plus-circle mr-1" aria-hidden="true"></i>Add Variation</a>
            <a class="btn btn-outline-success btn-sm" href="{{ route('admin.products.variants.index', $product->id) }}#bulk-variations"><i class="fas fa-layer-group mr-1" aria-hidden="true"></i>Build Multiple Variations</a>
            @endif
            <a class="btn btn-outline-success btn-sm" href="{{ route('admin.products.variants.index', $product->id) }}">Manage All Variants &amp; Prices</a>
        @endif
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.products.index') }}">Back to Products</a>
    </div>
</div>
@endsection

@section('content')
<div class="card card-outline card-primary js-product-standalone"
    data-return-url="{{ route('admin.products.index') }}"
    data-csrf-token="{{ csrf_token() }}">
    <div class="card-body">
        @include('admin.products.partials.form')
    </div>
</div>
@endsection

@section('js')
<script src="{{ asset('frontend/js/product-variation-edit.js') }}" defer></script>
<script src="{{ asset('frontend/js/product-page-standalone.js') }}" defer></script>
@stop
