@extends('storefront.layouts.catalog')
@section('title', 'Checkout')
@section('content')
    <div class="p08-heading"><h1>Cash on Delivery Checkout</h1><p>Your price and stock are verified again when you submit.</p></div>
    @if($errors->any())<div class="p08-error" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <div class="p08-checkout-grid">
        <form id="p08-checkout-form" method="POST" action="{{ route('storefront.checkout.place') }}" class="p08-checkout-form">
            @csrf
            <input type="hidden" name="checkout_key" value="{{ old('checkout_key', $checkoutKey) }}">
            <input type="hidden" name="cart_version" value="{{ $cart['version'] }}">
            <label for="p08-customer-name">Full name</label>
            <input id="p08-customer-name" name="customer_name" maxlength="255" value="{{ old('customer_name') }}" autocomplete="name" required>
            <label for="p08-phone">Phone (11 digits)</label>
            <input id="p08-phone" name="phone" inputmode="tel" pattern="01[0-9]{9}" maxlength="11" value="{{ old('phone') }}" autocomplete="tel" required>
            <label for="p08-address">Complete delivery address</label>
            <textarea id="p08-address" name="address" rows="3" maxlength="2000" required>{{ old('address') }}</textarea>
            <label for="p08-area">Delivery area</label>
            <select id="p08-area" name="shipping_area_id" required>
                <option value="">Choose a delivery area</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}" @selected((string) old('shipping_area_id') === (string) $area->id)>{{ $area->area_name }} — ৳{{ number_format($area->delivery_charge) }}</option>
                @endforeach
            </select>
            <label for="p08-note">Order note (optional)</label>
            <textarea id="p08-note" name="customer_note" rows="2" maxlength="1000">{{ old('customer_note') }}</textarea>
            <p class="p08-help">Only cash on delivery is available. A changed product price or unavailable shipping area stops checkout.</p>
            <button class="p08-primary p08-submit" type="submit" @disabled($areas->isEmpty())>Place COD Order</button>
            @if($areas->isEmpty())<p class="p08-error">Delivery areas are not configured; checkout is unavailable.</p>@endif
        </form>
        <aside class="p08-summary p08-checkout-summary" aria-label="Order summary">
            <h2>Order Summary</h2>
            @foreach($cart['lines'] as $line)
                <p>{{ $line['name'] }} ({{ $line['quantity'] }}) <strong>৳{{ $line['line_total'] }}</strong></p>
            @endforeach
            <hr>
            <p>Subtotal <strong>৳{{ $cart['subtotal'] }}</strong></p>
            <p>Delivery <strong>Calculated on submit</strong></p>
            <a href="{{ route('storefront.cart') }}">Review cart</a>
        </aside>
    </div>
@endsection
