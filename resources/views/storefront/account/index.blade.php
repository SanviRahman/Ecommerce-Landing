@extends('storefront.layouts.catalog')
@section('title','My Account')
@section('content')
<div class="p07-breadcrumb"><a href="{{ route('storefront.home') }}">Home</a><span>/</span><span>My Account</span></div>
<div class="p19-account-layout">
    <aside class="p19-account-sidebar"><div class="p19-account-avatar">{{ mb_strtoupper(mb_substr($customer->name,0,1)) }}</div>
        <strong>{{ $customer->name }}</strong><small>{{ $customer->email }}</small>
        <a href="#p19-account-overview">▦ Dashboard</a><a href="#p19-account-orders">▤ My Orders</a>
        <a href="#p19-account-profile">♙ Profile Settings</a>
        <a href="{{ route('storefront.wishlist') }}">♡ My Wishlist</a><a href="{{ route('order.track') }}">⌕ Track Order</a>
        <a href="{{ route('storefront.shop') }}">🛒 Continue Shopping</a>
        <form method="POST" action="{{ route('storefront.account.logout') }}">@csrf<button type="submit">Logout</button></form>
    </aside>
    <div class="p19-account-main"><section id="p19-account-overview"><p class="p19-caption">MY ACCOUNT</p>
        <h1>Dashboard</h1><p>Welcome back, {{ $customer->name }}.</p>
        <div class="p19-account-stat-grid"><div><span>Total Orders</span><strong>{{ $orderStats['total'] }}</strong></div>
            <div><span>Active Orders</span><strong>{{ $orderStats['active'] }}</strong></div>
            <div><span>Completed</span><strong>{{ $orderStats['completed'] }}</strong></div></div>
    </section>
    <section id="p19-account-orders" class="p19-account-panel"><h2>Recent Orders</h2>
        @forelse($orders as $order)
            <div class="p19-order-row"><div><strong>{{ $order->invoice_id }}</strong><small>{{ $order->created_at?->format('d M Y') }} · {{ $order->order_status }}</small></div>
                <span>৳{{ number_format((float)$order->total_amount,2) }}</span></div>
        @empty <p>No orders linked to this account yet. Guest/campaign orders are not exposed without verified ownership.</p> @endforelse
    </section>
    <section id="p19-account-profile" class="p19-account-panel"><h2>Profile Settings</h2>
        @if(session('status'))<p class="p08-feedback">{{ session('status') }}</p>@endif
        @if($errors->any())<div class="p08-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('storefront.account.profile.update') }}" class="p19-profile-form">@csrf @method('PATCH')
            <label for="p19-profile-name">Name</label><input id="p19-profile-name" name="name" required maxlength="160" value="{{ old('name',$customer->name) }}">
            <label for="p19-profile-phone">Phone (optional)</label><input id="p19-profile-phone" name="phone" inputmode="tel" maxlength="11" pattern="01[0-9]{9}" value="{{ old('phone',$customer->phone) }}">
            <label for="p19-profile-address">Delivery Address</label><textarea id="p19-profile-address" name="address" rows="3" maxlength="2000">{{ old('address',$customer->address) }}</textarea>
            <button type="submit" class="p08-primary">Save Profile</button>
        </form>
    </section>
    </div>
</div>
@endsection
