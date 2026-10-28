@extends('storefront.layouts.catalog')
@section('title','Customer Login')
@section('content')
<div class="p19-auth-layout">
    <section class="p19-auth-card" aria-labelledby="login-title"><p class="p19-caption">CUSTOMER ACCOUNT</p>
        <h1 id="login-title">Welcome Back</h1><p>Login to see your own orders and saved profile.</p>
        @if(session('status'))<p class="p39-wishlist-signin-note" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<div class="p08-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('storefront.account.login.submit') }}">@csrf
            <label for="p19-login-email">Email Address</label>
            <input type="email" id="p19-login-email" name="email" value="{{ old('email') }}" autocomplete="username" required maxlength="190">
            <label for="p19-login-password">Password</label>
            <input type="password" id="p19-login-password" name="password" autocomplete="current-password" required>
            <button class="p08-primary" type="submit">Login to Account →</button>
        </form>
        <p>New customer? <a href="{{ route('storefront.account.register') }}">Create an account</a></p>
    </section>
    <aside class="p19-auth-aside"><div class="p19-auth-icon">▣</div>
        <h2>Shop Smarter.<br>Live Better.</h2>
        <p>✓ Track orders safely</p><p>✓ Store your delivery address</p><p>✓ Faster repeat checkout</p>
        <small>Guest checkout is always an option where checkout is enabled.</small>
    </aside>
</div>
@endsection
