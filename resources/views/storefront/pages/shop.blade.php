@extends('storefront.layouts.catalog')
@section('title', $category?->name ?? 'Shop Products')
@section('content')
    <div class="p07-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}">Home</a><span aria-hidden="true">/</span>
        @if($category && $category->parent_id)
            <a href="{{ route('storefront.category', $category->parent->slug) }}">{{ $category->parent->name }}</a>
            <span aria-hidden="true">/</span>
        @endif
        <span>{{ $category?->name ?? 'Shop' }}</span>
    </div>
    <section class="p07-page-heading">
        <span class="p07-eyebrow">Curated catalog</span>
        <h1>{{ $category?->name ?? 'Explore our products' }}</h1>
        <p>Browse available products and their genuine options. Checkout is not enabled in this preview.</p>
    </section>

    <section id="categories" class="p07-browse-categories" aria-label="Product categories">
        <h2>Shop by Category</h2>
        <div class="p07-category-list">
            <a href="{{ route('storefront.shop') }}" class="p07-category-chip">All products</a>
            @foreach($navigation as $parent)
                <a href="{{ route('storefront.category', $parent->slug) }}" class="p07-category-chip">{{ $parent->name }}</a>
                @foreach($parent->children as $child)
                    <a href="{{ route('storefront.category', $child->slug) }}" class="p07-category-chip p07-category-child">{{ $child->name }}</a>
                @endforeach
            @endforeach
        </div>
    </section>

    <section aria-label="Catalog product listing">
        <div class="p07-list-toolbar">
            <p><strong>{{ $products->total() }}</strong> products</p>
            <form method="GET" action="{{ $category ? route('storefront.category', $category->slug) : route('storefront.shop') }}">
                @if($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
                <label for="p07-sort">Sort</label>
                <select name="sort" id="p07-sort" data-auto-submit>
                    <option value="newest" @selected($sort==='newest')>Newest</option>
                    <option value="price_asc" @selected($sort==='price_asc')>Price: low to high</option>
                    <option value="price_desc" @selected($sort==='price_desc')>Price: high to low</option>
                    <option value="name" @selected($sort==='name')>Name</option>
                </select>
                <button class="p07-visually-hidden" type="submit">Apply sort</button>
            </form>
        </div>
        @if($products->isEmpty())
            <div class="p07-blank">No matching public products. <a href="{{ route('storefront.shop') }}">View all products</a></div>
        @else
            <div class="p07-card-grid">
                @foreach($products as $product)
                    @include('storefront.partials.product-card', ['product' => $product])
                @endforeach
            </div>
            @if($products->hasPages())
                <nav class="p07-pagination" aria-label="Catalog pages">
                    @if($products->onFirstPage())
                        <span class="p07-page-disabled">Previous</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}" rel="prev">Previous</a>
                    @endif
                    <span>Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
                    @if($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" rel="next">Next</a>
                    @else
                        <span class="p07-page-disabled">Next</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
