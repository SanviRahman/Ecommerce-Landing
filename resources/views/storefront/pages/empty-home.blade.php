@extends('storefront.layouts.catalog')
@section('title', 'Homepage')
@section('content')
    <section class="p07-not-published" aria-labelledby="p07-not-published-title">
        <span class="p07-eyebrow">Storefront preview</span>
        <h1 id="p07-not-published-title">The shop homepage is not published yet.</h1>
        <p>Published homepage content will appear here after an authorized admin publishes a valid revision. Draft sections remain private.</p>
        <a class="p07-back-to-shop" href="{{ route('storefront.shop') }}">Browse public products</a>
    </section>
@endsection
