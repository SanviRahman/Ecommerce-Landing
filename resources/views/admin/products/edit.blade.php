@extends('adminlte::page')

@section('title', $title ?? 'Edit Product')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <h1 class="h3 mb-1">{{ $title ?? 'Edit Product' }}</h1>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.products.index') }}">Back to Products</a>
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
<script src="{{ asset('frontend/js/product-page-standalone.js') }}" defer></script>
@stop
