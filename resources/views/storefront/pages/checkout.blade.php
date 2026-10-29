@extends('storefront.layouts.catalog')
@section('title', 'Secure Checkout')
@section('content')
    @php $shopper = \Illuminate\Support\Facades\Auth::guard('storefront')->user(); @endphp
    <div hidden data-p53-checkout-tracking="{{ json_encode([
        'currency' => 'BDT',
        'value' => (float) $cart['subtotal'],
        'items' => array_map(static fn ($line) => [
            'item_id' => (string) $line['product_id'],
            'item_name' => (string) $line['name'],
            'price' => (float) $line['unit_price'],
            'quantity' => (int) $line['quantity'],
        ], $cart['lines']),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}"></div>
    <div class="p50-checkout-intro">
    <div class="p19-checkout-heading" data-reveal><p class="p19-caption">SECURE CASH ON DELIVERY CHECKOUT</p>
        <h1>Complete Your <span>Order</span></h1><p>Enter shipping details, confirm payment method, review your order and place it securely.</p>
    </div>
    <ol class="p19-checkout-steps" aria-label="Checkout steps" data-step-indicator>
        @foreach(['Shipping','Payment','Review','Order Placed'] as $name)
            <li class="{{ $loop->first ? 'is-current' : '' }}" data-step-indicator-item="{{ $loop->iteration }}"
                @if($loop->first) aria-current="step" @endif><span class="p50-step-number">{{ $loop->iteration }}</span><span class="p50-step-label">{{ $name }}</span></li>
        @endforeach
    </ol>
    </div>
    @if($errors->any())<div class="p08-error" role="alert"><strong>Please check these details:</strong>
        <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <div class="p08-checkout-grid p19-checkout-grid">
        <form id="p08-checkout-form" method="POST" action="{{ route('storefront.checkout.place') }}"
            class="p08-checkout-form p19-checkout-form" data-checkout-stepper>
            @csrf
            <input type="hidden" name="checkout_key" value="{{ old('checkout_key', $checkoutKey) }}">
            <input type="hidden" name="cart_version" value="{{ $cart['version'] }}">
            <fieldset class="p19-step-panel" data-checkout-step="1"><legend><span class="p50-step-legend-inner"><span class="p50-step-legend-icon">@include('storefront.partials.icon', ['name'=>'truck'])</span>Shipping Information</span></legend>
                <p class="p19-step-subtitle">Where should we deliver your order?</p>
                <div class="p19-form-row">
                    <div><label for="p08-customer-name">Full Name <span>*</span></label>
                        <input id="p08-customer-name" name="customer_name" maxlength="255"
                            value="{{ old('customer_name', $shopper?->name) }}" autocomplete="name" required></div>
                    <div><label for="p08-phone">Phone Number <span>*</span></label>
                        <input id="p08-phone" name="phone" inputmode="tel" pattern="01[0-9]{9}" maxlength="11"
                            value="{{ old('phone', $shopper?->phone) }}" placeholder="01XXXXXXXXX" autocomplete="tel" required></div>
                </div>
                <label for="p08-address">Complete Delivery Address <span>*</span></label>
                <textarea id="p08-address" name="address" rows="3" minlength="8" maxlength="2000"
                    autocomplete="street-address" required>{{ old('address', $shopper?->address) }}</textarea>
                <label for="p08-area">Delivery Area <span>*</span></label>
                <select id="p08-area" name="shipping_area_id" data-shipping-area required>
                    <option value="">Choose a delivery area</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" data-shipping-cost="{{ $area->delivery_charge }}"
                            @selected((string)old('shipping_area_id')===(string)$area->id)>
                            {{ $area->area_name }} — ৳{{ number_format($area->delivery_charge) }}
                        </option>
                    @endforeach
                </select>
                <label for="p08-note">Order note (optional)</label>
                <textarea id="p08-note" name="customer_note" rows="2" maxlength="1000">{{ old('customer_note') }}</textarea>
                <p class="p08-help">The final price, stock and shipping charge are recalculated by the server before placing your order.</p>
                <button type="button" class="p08-primary p19-next-btn" data-step-next="2" @disabled($areas->isEmpty())>Continue to Payment →</button>
                @if($areas->isEmpty())<p class="p08-error">Delivery areas are not configured; checkout is unavailable.</p>@endif
            </fieldset>
            <fieldset class="p19-step-panel" data-checkout-step="2"><legend><span class="p50-step-legend-inner"><span class="p50-step-legend-icon">@include('storefront.partials.icon', ['name'=>'shield'])</span>Payment Method</span></legend>
                <p class="p19-step-subtitle">Choose a payment method available at this store.</p>
                <label class="p19-payment-choice"><input type="radio" name="payment_display" checked disabled>
                    <span><strong>Cash on Delivery</strong><small>Pay when your order is delivered</small></span></label>
                <p class="p19-note">Online cards, bKash and Nagad are not enabled for this protected checkout.</p>
                <div class="p19-form-actions"><button type="button" data-step-back="1">← Shipping</button>
                    <button type="button" class="p08-primary" data-step-next="3">Review Order →</button></div>
            </fieldset>
            <fieldset class="p19-step-panel" data-checkout-step="3"><legend><span class="p50-step-legend-inner"><span class="p50-step-legend-icon">@include('storefront.partials.icon', ['name'=>'check'])</span>Review Your Order</span></legend>
                <p class="p19-step-subtitle">Verify your shipping details and items before submitting.</p>
                <div class="p19-review-shipping" data-review-shipping></div>
                <div class="p19-review-lines">
                    @foreach($cart['lines'] as $line)
                        <p>{{ $line['name'] }} × {{ $line['quantity'] }} <strong>৳{{ $line['line_total'] }}</strong></p>
                    @endforeach
                </div>
                <div class="p19-review-totals"><p>Subtotal <strong>৳{{ $cart['subtotal'] }}</strong></p>
                    <p>Delivery <strong data-shipping-preview>Choose delivery area</strong></p>
                    <p class="p54-review-total">Estimated Total <strong data-checkout-total-preview>Choose delivery area</strong></p></div>
                <p class="p08-help">By placing your order, you confirm that the information is correct. Inventory and price are checked again.</p>
                <div class="p19-form-actions"><button type="button" data-step-back="2">← Payment</button>
                    <button class="p08-primary p08-submit" type="submit" @disabled($areas->isEmpty())>Place COD Order</button>
                </div>
            </fieldset>
            <noscript><p class="p19-noscript">JavaScript is disabled. The secure server form is fully usable; click Place COD Order below.</p>
                <button class="p08-primary p08-submit" type="submit" @disabled($areas->isEmpty())>Place COD Order</button></noscript>
        </form>
        <aside class="p08-summary p08-checkout-summary p19-order-summary" aria-label="Order summary" data-reveal>
            <h2>Order Summary <small>{{ count($cart['lines']) }} items</small></h2>
            @foreach($cart['lines'] as $line)
                <div class="p19-summary-product"><span>{{ $line['name'] }}<small>Qty: {{ $line['quantity'] }}</small></span><strong>৳{{ $line['line_total'] }}</strong></div>
            @endforeach
            <hr><p>Subtotal <strong>৳{{ $cart['subtotal'] }}</strong></p>
            <p>Shipping <strong data-shipping-preview>Choose delivery area</strong></p>
            <p class="p19-summary-total">Estimated Total <strong data-checkout-total-preview data-subtotal-bdt="{{ $cart['subtotal'] }}" aria-live="polite">Choose delivery area</strong></p>
            <p class="p54-total-note">Final total, shipping and stock are confirmed by the server when you place the order.</p>
            <a href="{{ route('storefront.cart') }}">← Edit cart</a>
        </aside>
    </div>
@endsection
