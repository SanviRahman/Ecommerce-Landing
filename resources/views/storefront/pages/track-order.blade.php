@extends('storefront.layouts.catalog')
@section('title', 'Track Your Order')
@section('content')
    <section class="p33-track-page" aria-labelledby="p33-track-page-title">
        <div class="p07-breadcrumb"><a href="{{ route('home') }}">Home</a> / Track Order</div>
        @if(\Illuminate\Support\Facades\Auth::guard('storefront')->user()?->is_active)
            <div class="p47-dashboard-return"><a href="{{ route('storefront.account.index', ['tab' => 'dashboard']) }}#p19-account-overview">← Back to Dashboard</a></div>
        @endif
        <span class="p33-overline">DELIVERY UPDATES</span>
        <h1 id="p33-track-page-title">Track Your Order</h1>
        <p>Check the latest status using the 11-digit phone number on your order.</p>
        <form method="POST" action="{{ route('order.track.search') }}" data-storefront-track>
            @csrf
            <label for="p33-track-phone">Order phone number</label>
            <div class="p33-track-controls"><input id="p33-track-phone" type="tel" name="tracking_phone" pattern="01[0-9]{9}" maxlength="11" inputmode="numeric" autocomplete="tel" value="{{ old('tracking_phone', $searchedPhone ?? '') }}" required placeholder="01XXXXXXXXX"><button type="submit">Track Order →</button></div>
        </form>
        @error('tracking_phone')<p class="p33-error" role="alert">{{ $message }}</p>@enderror
        <div class="p33-track-results" data-track-results role="status" aria-live="polite">
            @if($orders !== null)
                @include('frontend.partials.order-tracking-results', ['trackingOrders' => $orders, 'trackingSearchedPhone' => $searchedPhone])
            @endif
        </div>
    </section>
@endsection
