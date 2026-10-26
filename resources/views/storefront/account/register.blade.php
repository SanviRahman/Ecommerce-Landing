@extends('storefront.layouts.catalog')
@section('title','Create Customer Account')
@section('content')
<div class="p19-auth-layout">
    <section class="p19-auth-card" aria-labelledby="register-title"><p class="p19-caption">JOIN OUR STORE</p>
        <h1 id="register-title">Create Your Account</h1><p>Register without affecting your admin or employee login.</p>
        @if($errors->any())<div class="p08-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('storefront.account.register.submit') }}">@csrf
            <label for="p19-signup-name">Full Name</label><input id="p19-signup-name" type="text" name="name" maxlength="160" value="{{ old('name') }}" autocomplete="name" required>
            <label for="p19-signup-email">Email</label><input id="p19-signup-email" type="email" name="email" maxlength="190" value="{{ old('email') }}" autocomplete="email" required>
            <label for="p19-signup-password">Password (12+ characters including letters and numbers)</label>
            <input id="p19-signup-password" type="password" name="password" minlength="12" autocomplete="new-password" required>
            <label for="p19-signup-confirm">Confirm Password</label>
            <input id="p19-signup-confirm" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
            <button class="p08-primary" type="submit">Create Account →</button>
        </form>
        <p>Already have an account? <a href="{{ route('storefront.account.login') }}">Login</a></p>
    </section>
    <aside class="p19-auth-aside"><div class="p19-auth-icon">✓</div><h2>Your next order,<br>made easier.</h2><p>Your password is hashed before storage.</p><p>Order ownership is scoped to your customer account.</p></aside>
</div>
@endsection
