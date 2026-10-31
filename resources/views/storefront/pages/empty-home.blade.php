@extends('storefront.layouts.catalog')
@section('title', 'Homepage')
@section('content')
    <section class="p07-not-published" aria-labelledby="p07-not-published-title">
        <span class="p07-eyebrow">Our storefront</span>
        <h1 id="p07-not-published-title">Our homepage is being prepared.</h1>
        <p>Browse available products while we get the homepage ready.</p>
        <a class="p07-back-to-shop" href="{{ route('storefront.shop') }}">Browse public products</a>
    </section>
@endsection
