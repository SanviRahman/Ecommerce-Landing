@extends('storefront.layouts.catalog')
@section('title', 'My Wishlist')
@section('content')
    <div class="p07-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><span>Wishlist</span></div>
    <section class="p32-wishlist-heading">
        <p class="p19-caption">YOUR FAVOURITES</p>
        <h1>My Wishlist <span aria-hidden="true">@include('storefront.partials.icon', ['name'=>'heart'])</span></h1>
        <p>Your private wishlist belongs to your signed-in customer session. Only active products are displayed.</p>
        @if(session('status')) <p class="p08-feedback" role="status">{{ session('status') }}</p> @endif
        @if($errors->any()) <p class="p08-error" role="alert">{{ $errors->first() }}</p> @endif
    </section>
    @if($products->isEmpty())
        <div class="p07-blank">No products saved yet. <a href="{{ route('storefront.shop') }}">Explore products</a> and tap the heart icon to save your favourites.</div>
    @else
        <div class="p07-card-grid p32-wishlist-grid">
            @foreach($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    @endif
@endsection
