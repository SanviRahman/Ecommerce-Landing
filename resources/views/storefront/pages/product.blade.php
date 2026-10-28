@extends('storefront.layouts.catalog')
@section('title', $product->name)
@section('content')
    @php
        // The web middleware normally supplies $errors. Read the flashed bag as
        // a fallback so a redirected storefront form never loses its feedback.
        $flashedProductErrors = request()->hasSession() ? request()->session()->get('errors') : null;
        $hasFlashedProductErrors = $flashedProductErrors instanceof \Illuminate\Support\ViewErrorBag
            && $flashedProductErrors->any();
        // A marker from this exact product form is flashed ONLY on validation
        // failure. Do not show a warning from unrelated cart/checkout forms.
        $rejectedProductForm = old('storefront_product_origin') === $product->slug;
    @endphp
    @if($errors->any() || $hasFlashedProductErrors || $rejectedProductForm)
        <div class="p08-error p10-product-error" role="alert" aria-live="assertive">
            <strong>We could not update your cart.</strong>
            @if($errors->any())
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @elseif($hasFlashedProductErrors)
                <ul>@foreach($flashedProductErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @else
                <p>We could not add this item. Review the selected variation, quantity and current cart, then try again.</p>
            @endif
        </div>
    @endif
    <div class="p07-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span>
        <a href="{{ route('storefront.shop') }}">Shop</a><span aria-hidden="true">/</span>
        <a href="{{ route('storefront.category', $category->slug) }}">{{ $category->name }}</a>
        <span aria-hidden="true">/</span><span>{{ $product->name }}</span>
    </div>
    <div class="p07-detail">
        <div class="p07-product-gallery">
            <img class="p07-main-photo" src="{{ $catalog->productImage($product) }}" alt="{{ $product->name }}" fetchpriority="high">
            @if($product->getMedia('product_gallery')->isNotEmpty())
                <div class="p07-thumbnail-row" aria-label="Product gallery">
                    @foreach($product->getMedia('product_gallery')->take(6) as $galleryImage)
                        <button type="button" data-gallery-src="{{ $galleryImage->getUrl() }}" data-gallery-alt="{{ $product->name }} image {{ $loop->iteration }}" aria-label="Show image {{ $loop->iteration }}">
                            <img src="{{ $galleryImage->getUrl() }}" alt="" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        <section class="p07-detail-summary" aria-labelledby="p07-product-name">
            <span class="p07-eyebrow">{{ $category->name }}</span>
            <h1 id="p07-product-name">{{ $product->name }}</h1>
            <div class="p07-detail-price" id="p07-selected-price">{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($catalog->displayPrice($product)) }}</div>


            @if($product->has_variants)
                <div id="p07-variant-picker" data-variant-matrix="{{ json_encode($selection, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}" data-p35-managed-product="{{ (int) $product->inventory_version === 1 ? '1' : '0' }}">
                    @foreach($selection['options'] as $option)
                        <div class="p07-choice-group">
                            <label for="p07-option-{{ $option['id'] }}">{{ $option['name'] }}</label>
                            <select id="p07-option-{{ $option['id'] }}" data-option-id="{{ $option['id'] }}">
                                <option value="">Choose {{ $option['name'] }}</option>
                                @foreach($option['values'] as $value)
                                    <option value="{{ $value['id'] }}">{{ $value['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <p class="p07-variant-status" id="p07-variant-status" role="status" aria-live="polite">Select all options to view exact price and stock.</p>
                    <p class="p07-sku" id="p07-variant-sku"></p>
                </div>
            @else
                <p class="p07-variant-status">{{ $catalog->displayStock($product) > 0 ? 'Available: '.$catalog->displayStock($product) : 'Currently out of stock' }}</p>
                @if($product->weight_size)<p class="p07-sku">{{ $product->weight_size }}</p>@endif
            @endif
            @if($cartVersion !== null)
                @php
                    // Cart is variant-owned. Legacy displayed stock is NOT sufficient for a safe order.
                    $simpleDefault = !$product->has_variants && (int) $product->inventory_version === 1
                        ? $product->variants()->where('is_default', true)->where('is_active', true)
                            ->where('option_signature', 'default')->where('stock', '>', 0)->first()
                        : null;
                    $canSelectSku = (int) $product->inventory_version === 1 && ($product->has_variants
                        ? $catalog->eligibleVariants($product)->contains(static fn ($v) => $v->stock > 0)
                        : $simpleDefault !== null);
                    $canCheckoutNow = config('storefront.p08_checkout_test_enabled', false) === true
                        && config('storefront.p03_core_test_enabled', false) === true;
                @endphp
                <form id="p08-product-order-form" class="p08-product-order" method="POST" action="{{ route('storefront.cart.add') }}">
                    @csrf
                    <input type="hidden" name="storefront_product_origin" value="{{ $product->slug }}">
                    <input type="hidden" name="cart_version" value="{{ $cartVersion }}">
                    @if($product->has_variants)
                        <label for="p08-variant-id">Selected variation</label>
                        <select name="product_variant_id" id="p08-variant-id" aria-describedby="p07-variant-status" required>
                            <option value="">Choose the exact SKU</option>
                            @foreach($catalog->eligibleVariants($product) as $variant)
                                <option value="{{ $variant->id }}" @disabled($variant->stock < 1) @selected((string) old('product_variant_id') === (string) $variant->id)>
                                    {{ $variant->optionValues->map(fn($v) => $v->label)->implode(' / ') }} ({{ $variant->sku }}) — {{ $variant->stock }} available
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="product_variant_id" value="{{ $simpleDefault?->id }}">
                    @endif
                    <div class="p35-quantity-field">
                        <label for="p08-product-quantity">Quantity</label>
                        <div class="p35-quantity-stepper" data-p35-quantity-stepper>
                            <button type="button" data-p35-qty-step="-1" aria-label="Decrease quantity" aria-controls="p08-product-quantity">−</button>
                            <input type="number" name="quantity" id="p08-product-quantity" min="1"
                                max="{{ $simpleDefault ? min(1000, (int) $simpleDefault->stock) : 1000 }}"
                                value="{{ old('quantity', 1) }}" inputmode="numeric" required>
                            <button type="button" data-p35-qty-step="1" aria-label="Increase quantity" aria-controls="p08-product-quantity">+</button>
                        </div>
                    </div>
                    <div class="p08-product-actions p35-product-actions" id="p19-primary-buy-actions">
                        <button type="submit" name="intent" value="cart" class="p08-action-cart"
                            @if($product->has_variants) data-p35-variant-required @endif
                            @disabled(!$canSelectSku)>
                            @include('storefront.partials.icon', ['name'=>'cart-plus']) <span>Add to Cart</span>
                        </button>
                        @if($canCheckoutNow)
                            <button type="submit" name="intent" value="checkout" class="p08-action-buy"
                                @if($product->has_variants) data-p35-variant-required @endif
                                @disabled(!$canSelectSku)>Buy Now @include('storefront.partials.icon', ['name'=>'arrow-right'])</button>
                        @else
                            <button type="button" class="p08-action-buy" disabled aria-describedby="p35-checkout-availability"
                                title="Checkout is not yet available">Buy Now @include('storefront.partials.icon', ['name'=>'arrow-right'])</button>
                        @endif
                    </div>
                    <p class="p08-help">Cash on delivery. Prices and availability are verified again before confirming an order.</p>
                    @unless($canSelectSku)
                        <p class="p35-purchase-note" role="status">This product is not yet available to add to the online cart. Please contact us for ordering assistance.</p>
                    @endunless
                </form>
                @unless($canCheckoutNow)
                    <p class="p08-help p35-checkout-availability" id="p35-checkout-availability" role="status">
                        Secure checkout is temporarily unavailable. You can still review your <a href="{{ route('storefront.cart') }}">shopping cart</a>.
                    </p>
                @endunless
                {{-- Real checkout submit is only available after the existing stock/shipping readiness gates.
                     The bar also shows checkout's disabled state clearly on small screens. --}}
                <div class="p10-mobile-buy p35-sticky-buy" data-sticky-order hidden aria-label="Quick purchase action">
                    <div class="p19-sticky-product">
                        <img src="{{ $catalog->productImage($product) }}" alt="" loading="lazy" width="46" height="46">
                        <span><strong>{{ \Illuminate\Support\Str::limit($product->name, 28) }}</strong>
                            <small data-p35-sticky-price>{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($catalog->displayPrice($product)) }}</small></span>
                    </div>
                    @if($canCheckoutNow)
                        <button form="p08-product-order-form" type="submit" name="intent" value="checkout"
                            @if($product->has_variants) data-p35-variant-required @endif
                            @disabled(!$canSelectSku)>Buy Now @include('storefront.partials.icon', ['name'=>'arrow-right'])</button>
                    @else
                        <button type="button" disabled title="Checkout is temporarily unavailable">Buy Now</button>
                    @endif
                </div>
            @else
                <div class="p07-checkout-notice" role="note">
                    <strong>Product information</strong>
                    <span>Online ordering is unavailable for this server. Please contact us for assistance.</span>
                </div>
            @endif
            @php
                $wishlistState = app(\App\Support\StorefrontWishlistState::class);
                $wishSignedIn = $wishlistState->authenticated();
                $isSaved = $wishSignedIn && $wishlistState->contains((int) $product->id);
            @endphp
            <div class="p32-wishlist-form">
                @if($wishSignedIn)
                    <form method="POST" action="{{ route('storefront.wishlist.toggle') }}">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <button type="submit" class="p32-wishlist-btn" aria-label="{{ $isSaved ? 'Remove from' : 'Add to' }} wishlist">
                            @include('storefront.partials.icon', ['name'=>$isSaved ? 'heart-fill' : 'heart'])
                            {{ $isSaved ? 'Saved to Wishlist' : 'Save to Wishlist' }}
                        </button>
                    </form>
                    <a href="{{ route('storefront.wishlist') }}" class="p32-wishlist-view">View Wishlist</a>
                @else
                    <a href="{{ route('storefront.account.login') }}" class="p32-wishlist-btn p39-login-wishlist"
                       aria-label="Log in or register to save this product">
                        @include('storefront.partials.icon', ['name'=>'heart']) Sign in to Save
                    </a>
                    <a href="{{ route('storefront.account.register') }}" class="p32-wishlist-view">Create account</a>
                @endif
            </div>
            <a class="p07-back-to-shop" href="{{ route('storefront.shop') }}">Explore more products</a>
        </section>
    </div>

    @if($product->short_description)
        <section class="p19-product-benefits" aria-label="Product highlights"><h2>Product highlights</h2><p>{{ $product->short_description }}</p></section>
    @endif
    @if($product->full_description)
        <section class="p07-description" aria-labelledby="p07-description-heading">
            <h2 id="p07-description-heading">Product description</h2>
            <div class="p07-description-body">{{ $product->full_description }}</div>
        </section>
    @endif
    @if(($relatedProducts ?? collect())->isNotEmpty())
        <section class="p35-related-products" aria-labelledby="p35-related-heading" data-reveal>
            <div class="p35-related-heading">
                <div><span class="p07-eyebrow">DISCOVER MORE</span><h2 id="p35-related-heading">Related products</h2>
                    <p>More items from our active collections.</p></div>
                <a href="{{ route('storefront.shop') }}">Explore all products →</a>
            </div>
            <div class="p07-card-grid p35-related-grid" data-reveal-stagger>
                @foreach($relatedProducts as $relatedProduct)
                    @include('storefront.partials.product-card', ['product' => $relatedProduct])
                @endforeach
            </div>
        </section>
    @endif
@endsection
