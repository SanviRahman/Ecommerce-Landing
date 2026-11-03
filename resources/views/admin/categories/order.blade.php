@extends('adminlte::page')

@section('title', $title)

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1>{{ $title }}</h1>
        <p class="text-muted mb-0">One ordering screen for the storefront category menu, category pages and homepage category-wise products.</p>
    </div>
    <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary mt-2 mt-md-0">Manage Categories</a>
</div>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert"><strong>Order was not saved.</strong>
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<style>
    .catalog-order-item{display:flex;align-items:center;gap:12px;padding:12px 14px;background:#fff;border:1px solid #dae4df;border-radius:9px;margin-bottom:8px}
    .catalog-order-position{display:inline-grid;place-items:center;background:#e6f5ed;border-radius:7px;color:#126641;font-weight:700;min-width:32px;height:32px}
    .catalog-order-name{min-width:0;flex:1;overflow-wrap:anywhere}
    .catalog-order-actions{display:flex;gap:5px;flex-shrink:0}
    .catalog-order-list{list-style:none;padding:0;margin:0 0 14px}
    .catalog-order-group{margin-bottom:21px;padding:12px;background:#f7f9fb;border-radius:11px}
    .catalog-order-group h3{font-size:14px;font-weight:700;margin:0 0 12px}
    .catalog-order-note{font-size:13px;color:#586c60}
    .catalog-order-item:focus-within{outline:2px solid #16734b;outline-offset:2px}
</style>
<div class="row">
    <div class="col-lg-5">
        <section class="card shadow-sm" aria-labelledby="order-categories-title">
            <div class="card-header bg-white"><h2 id="order-categories-title" class="h5 mb-1">1. Category order</h2>
                <p class="catalog-order-note mb-0">Move categories up or down. Subcategories stay within their own parent.</p>
            </div>
            <div class="card-body">
                @if($categories->isEmpty())
                    <p class="text-muted">No categories yet. Create a category first.</p>
                @else
                    <form method="POST" action="{{ route('admin.catalog-order.categories') }}" data-order-form>
                        @csrf @method('PATCH')
                        @if($selectedCategory)<input type="hidden" name="category_id" value="{{ $selectedCategory->id }}">@endif
                        <div class="catalog-order-group">
                            <h3>Top-level categories</h3>
                            <ol class="catalog-order-list" data-order-list>
                                @foreach($parents as $parent)
                                    <li class="catalog-order-item">
                                        <input type="hidden" name="category_ids[]" value="{{ $parent->id }}">
                                        <span class="catalog-order-position" data-order-number>{{ $loop->iteration }}</span>
                                        <div class="catalog-order-name"><strong>{{ $parent->name }}</strong>
                                            <small class="d-block text-muted">{{ $parent->products_count }} direct products · {{ $parent->status ? 'Active' : 'Inactive' }}</small>
                                        </div>
                                        <div class="catalog-order-actions">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-move="up" aria-label="Move {{ $parent->name }} up" title="Move up"><i class="fas fa-arrow-up"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-move="down" aria-label="Move {{ $parent->name }} down" title="Move down"><i class="fas fa-arrow-down"></i></button>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                        @foreach($parents as $parent)
                            @php $children = $categories->where('parent_id', $parent->id)->values(); @endphp
                            @if($children->isNotEmpty())
                                <div class="catalog-order-group"><h3>Subcategories of {{ $parent->name }}</h3>
                                    <ol class="catalog-order-list" data-order-list>
                                        @foreach($children as $child)
                                            <li class="catalog-order-item">
                                                <input type="hidden" name="category_ids[]" value="{{ $child->id }}">
                                                <span class="catalog-order-position" data-order-number>{{ $loop->iteration }}</span>
                                                <div class="catalog-order-name"><strong>{{ $child->name }}</strong>
                                                    <small class="d-block text-muted">{{ $child->products_count }} products · {{ $child->status ? 'Active' : 'Inactive' }}</small>
                                                </div>
                                                <div class="catalog-order-actions">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move="up" aria-label="Move {{ $child->name }} up" title="Move up"><i class="fas fa-arrow-up"></i></button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move="down" aria-label="Move {{ $child->name }} down" title="Move down"><i class="fas fa-arrow-down"></i></button>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            @endif
                        @endforeach
                        @if($orphans->isNotEmpty())
                            <div class="catalog-order-group"><h3>Categories whose parent is currently unavailable</h3>
                                <p class="catalog-order-note">These categories are hidden publicly until their parent is restored or reassigned.</p>
                                <ol class="catalog-order-list" data-order-list>
                                    @foreach($orphans as $orphan)
                                        <li class="catalog-order-item">
                                            <input type="hidden" name="category_ids[]" value="{{ $orphan->id }}">
                                            <span class="catalog-order-position" data-order-number>{{ $loop->iteration }}</span>
                                            <div class="catalog-order-name"><strong>{{ $orphan->name }}</strong><small class="d-block text-muted">Parent #{{ $orphan->parent_id }} unavailable</small></div>
                                            <div class="catalog-order-actions">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-move="up" aria-label="Move {{ $orphan->name }} up"><i class="fas fa-arrow-up"></i></button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-move="down" aria-label="Move {{ $orphan->name }} down"><i class="fas fa-arrow-down"></i></button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-success btn-block">Save Category Order</button>
                    </form>
                @endif
            </div>
        </section>
    </div>
    <div class="col-lg-7">
        <section class="card shadow-sm" aria-labelledby="order-products-title">
            <div class="card-header bg-white"><h2 id="order-products-title" class="h5 mb-1">2. Products within a category</h2>
                <p class="catalog-order-note mb-0">Save here once. Category listing and homepage category shelves use the same product order automatically.</p>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.catalog-order.index') }}" id="catalog-order-category-form" class="form-group">
                    <label for="catalog-order-category">Choose a category</label>
                    <div>
                        <select name="category_id" id="catalog-order-category" class="form-control" aria-describedby="catalog-order-category-help">
                            @foreach($parents as $parent)
                                <option value="{{ $parent->id }}" @selected($selectedCategory?->id === $parent->id)>{{ $parent->name }} (main category)</option>
                                @foreach($categories->where('parent_id', $parent->id) as $child)
                                    <option value="{{ $child->id }}" @selected($selectedCategory?->id === $child->id)>&nbsp;&nbsp;— {{ $child->name }}</option>
                                @endforeach
                            @endforeach
                            @foreach($orphans as $orphan)
                                <option value="{{ $orphan->id }}" @selected($selectedCategory?->id === $orphan->id)>{{ $orphan->name }} (parent unavailable)</option>
                            @endforeach
                        </select>
                    </div>
                    <small class="form-text text-muted" id="catalog-order-category-help">Products appear automatically when you select a category.</small>
                    <noscript><button class="btn btn-outline-primary btn-sm mt-2" type="submit">Show Products</button></noscript>
                </form>
                @if($selectedCategory && $products->isNotEmpty())
                    <form method="POST" action="{{ route('admin.catalog-order.products') }}" data-order-form>
                        @csrf @method('PATCH')
                        <input type="hidden" name="category_id" value="{{ $selectedCategory->id }}">
                        <p class="catalog-order-note">{{ $products->count() }} products in <strong>{{ $selectedCategory->name }}</strong>. Inactive products are included for future activation.</p>
                        <ol class="catalog-order-list" data-order-list>
                            @foreach($products as $product)
                                <li class="catalog-order-item">
                                    <input type="hidden" name="product_ids[]" value="{{ $product->id }}">
                                    <span class="catalog-order-position" data-order-number>{{ $loop->iteration }}</span>
                                    <div class="catalog-order-name"><strong>{{ $product->name }}</strong>
                                        <small class="d-block text-muted">#{{ $product->id }} · {{ $product->status ? 'Active' : 'Inactive' }}</small>
                                    </div>
                                    <div class="catalog-order-actions">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-move="up" aria-label="Move {{ $product->name }} up" title="Move up"><i class="fas fa-arrow-up"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-move="down" aria-label="Move {{ $product->name }} down" title="Move down"><i class="fas fa-arrow-down"></i></button>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <button type="submit" class="btn btn-success btn-block">Save Product Order</button>
                    </form>
                @else
                    <div class="alert alert-light border">{{ $selectedCategory ? 'This category has no products yet.' : 'Create a category to arrange products.' }}</div>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection

@section('js')
<script>
// Category selection is read-only GET navigation; saving any reordering still requires PATCH.
var hasUnsavedOrder = false;
var categoryForm = document.getElementById('catalog-order-category-form');
var categorySelect = document.getElementById('catalog-order-category');
if (categoryForm && categorySelect) {
    var initiallySelectedCategoryId = categorySelect.value;
    categorySelect.addEventListener('change', function () {
        if (hasUnsavedOrder && !window.confirm('You have unsaved order changes. Switch category without saving?')) {
            categorySelect.value = initiallySelectedCategoryId;
            return;
        }
        categoryForm.submit();
    });
}
// Move controls work on desktop, keyboard and mobile touch without extra packages.
document.querySelectorAll('[data-order-list]').forEach(function (list) {
    function refresh() {
        Array.from(list.children).forEach(function (item, index) {
            var number = item.querySelector('[data-order-number]');
            if (number) number.textContent = String(index + 1);
            var up = item.querySelector('[data-move="up"]');
            var down = item.querySelector('[data-move="down"]');
            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === list.children.length - 1;
        });
    }
    list.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-move]');
        if (!button || !list.contains(button)) return;
        var item = button.closest('li');
        if (!item || item.parentElement !== list) return;
        var moved = false;
        if (button.dataset.move === 'up' && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
            moved = true;
        } else if (button.dataset.move === 'down' && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
            moved = true;
        }
        if (moved) hasUnsavedOrder = true;
        refresh();
        button.focus();
    });
    refresh();
});
</script>
@endsection
