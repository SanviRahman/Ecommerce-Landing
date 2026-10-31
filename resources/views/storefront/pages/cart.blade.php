@extends('storefront.layouts.catalog')
@section('title', 'Your Cart')
@section('content')
    <div class="p08-heading"><h1>Your Cart</h1><p>Your selections are saved privately in this browser for up to 30 days.</p></div>
    @if(session('status'))<p class="p08-feedback" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="p08-error" role="alert"><strong>Unable to update your cart.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    @forelse($cart['lines'] as $line)
        <section class="p08-cart-line" aria-label="{{ $line['name'] }}">
            <div>
                <h2>{{ $line['name'] }}</h2>
                @if($line['option_label'])<p>{{ $line['option_label'] }}</p>@endif
                <p>SKU: {{ $line['sku'] ?? 'Unavailable' }}</p>
                <p>Quoted: ৳{{ $line['unit_price'] }} each · Line total: ৳{{ $line['line_total'] }}</p>
                @unless($line['available'])<p class="p08-error">This product is unavailable. Remove it to continue.</p>@endunless
                @if($line['price_changed'])<p class="p08-warning">Price changed to ৳{{ $line['current_price'] }}. Update to accept the latest price.</p>@endif
                @if(!$line['sufficient'] && $line['available'])<p class="p08-warning">Available stock: {{ $line['stock'] }}. Reduce quantity to continue.</p>@endif
            </div>
            <form action="{{ route('storefront.cart.change', $line['id']) }}" method="POST" class="p08-line-form">
                @csrf @method('PATCH')
                <input type="hidden" name="cart_version" value="{{ $cart['version'] }}">
                <label for="quantity-{{ $line['id'] }}">Quantity</label>
                <input id="quantity-{{ $line['id'] }}" type="number" name="quantity" min="0" max="1000" value="{{ $line['quantity'] }}" required>
                <div class="p08-inline-actions"><button type="submit">Update / Accept price</button><button type="submit" name="quantity" value="0" formnovalidate>Remove</button></div>
            </form>
        </section>
    @empty
        <p class="p08-empty">Your cart is empty. <a href="{{ route('storefront.shop') }}">Browse products</a>.</p>
    @endforelse
    @if(count($cart['lines']))
        <div class="p08-summary">
            <p>Merchandise subtotal <strong>৳{{ $cart['subtotal'] }}</strong></p>
            <small>Shipping is calculated from the current delivery area at checkout.</small>
            @if($cart['can_checkout'] && config('storefront.p08_checkout_test_enabled', false) && config('storefront.p03_core_test_enabled', false))
                <a class="p08-primary" href="{{ route('storefront.checkout') }}">Continue to Checkout</a>
            @else
                <p class="p08-warning">Review your items. Secure checkout requires reconciled product inventory, a published homepage and an active global delivery area.</p>
            @endif
        </div>
    @endif
@endsection
