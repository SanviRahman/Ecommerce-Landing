@extends('storefront.layouts.catalog')
@section('title','My Account')
@section('content')
@php $profilePhotoUrl = $customer->getFirstMediaUrl('profile_photo'); @endphp
<div class="p07-breadcrumb"><a href="{{ route('storefront.home') }}">Home</a><span>/</span><span>My Account</span></div>
<div class="p19-account-layout">
    <aside class="p19-account-sidebar" aria-label="Customer account navigation">
        <div class="p19-account-avatar p52-account-avatar">
            @if($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="{{ $customer->name }} profile photo" loading="lazy">
            @else
                {{ mb_strtoupper(mb_substr($customer->name,0,1)) }}
            @endif
        </div>
        <strong>{{ $customer->name }}</strong><small>{{ $customer->email }}</small>
        <nav class="p47-account-menu" aria-label="Account sections">
            <a href="{{ route('storefront.account.index', ['tab' => 'dashboard']) }}#p19-account-overview" @if($activeTab === 'dashboard') aria-current="page" @endif>▦ Dashboard</a>
            <a href="{{ route('storefront.account.index', ['tab' => 'orders']) }}#p19-account-overview" @if($activeTab === 'orders') aria-current="page" @endif>▤ My Orders</a>
            <a href="{{ route('storefront.account.index', ['tab' => 'profile']) }}#p19-account-profile" @if($activeTab === 'profile') aria-current="page" @endif>♙ Profile Settings</a>
        </nav>
        <a href="{{ route('storefront.wishlist') }}">♡ My Wishlist</a>
        <a href="{{ route('order.track') }}">⌕ Track Order</a>
        <a href="{{ route('storefront.shop') }}">🛒 Continue Shopping</a>
        <form method="POST" action="{{ route('storefront.account.logout') }}">@csrf<button type="submit">Logout</button></form>
    </aside>
    <div class="p19-account-main">
        @if($activeTab !== 'profile')
            <section id="p19-account-overview" aria-labelledby="p47-dashboard-title">
                <p class="p19-caption">MY ACCOUNT</p>
                <h1 id="p47-dashboard-title">Dashboard</h1>
                <p>Welcome back, {{ $customer->name }}.</p>
                <div class="p19-account-stat-grid">
                    <div><span>Total Orders</span><strong>{{ $orderStats['total'] }}</strong></div>
                    <div><span>Active Orders</span><strong>{{ $orderStats['active'] }}</strong></div>
                    <div><span>Completed</span><strong>{{ $orderStats['completed'] }}</strong></div>
                </div>
            </section>
            <section id="p19-account-orders" class="p19-account-panel" aria-labelledby="p47-orders-title">
                <h2 id="p47-orders-title">{{ $activeTab === 'orders' ? 'My Orders' : 'Recent Orders' }}</h2>
                @forelse($orders as $order)
                    <div class="p19-order-row">
                        <div><strong>{{ $order->invoice_id }}</strong><small>{{ $order->created_at?->format('d M Y') }} · {{ $order->order_status }}</small></div>
                        <span>৳{{ number_format((float)$order->total_amount,2) }}</span>
                    </div>
                @empty
                    <p>No orders linked to this account yet. Guest/campaign orders are not exposed without verified ownership.</p>
                @endforelse
            </section>
        @else
            <section id="p19-account-profile" class="p19-account-panel p52-profile-panel" aria-labelledby="p47-profile-title">
                <div class="p52-profile-heading">
                    <div><p class="p19-caption">ACCOUNT DETAILS</p>
                        <h1 id="p47-profile-title">Profile Settings</h1>
                        <p>Keep your personal details and delivery address up to date.</p>
                    </div>
                    <a class="p52-dashboard-link" href="{{ route('storefront.account.index', ['tab'=>'dashboard']) }}#p19-account-overview">View Dashboard →</a>
                </div>
                @if(session('status'))<p class="p08-feedback" role="status">{{ session('status') }}</p>@endif
                @if($errors->any())<div class="p08-error" role="alert"><strong>Please check your profile details:</strong>
                    <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
                <form method="POST" action="{{ route('storefront.account.profile.update') }}" class="p19-profile-form p52-profile-form" enctype="multipart/form-data">
                    @csrf @method('PATCH')
                    <section class="p52-profile-group" aria-labelledby="p52-personal-title">
                        <div class="p52-profile-group-title"><span class="p52-profile-group-icon">@include('storefront.partials.icon', ['name'=>'account'])</span>
                            <div><h2 id="p52-personal-title">Personal Information</h2><p>Account information for orders and communication.</p></div>
                        </div>
                        <div class="p52-profile-fields p52-profile-personal">
                            <div><label for="p19-profile-name">Full Name <span class="p52-required">*</span></label>
                                <input id="p19-profile-name" name="name" autocomplete="name" required maxlength="160" value="{{ old('name',$customer->name) }}"></div>
                            <div><label for="p52-profile-email">Email Address</label>
                                <input id="p52-profile-email" type="email" value="{{ $customer->email }}" readonly aria-describedby="p52-email-help">
                                <small id="p52-email-help">Your login email is read-only for account security.</small></div>
                            <div><label for="p19-profile-phone">Phone Number</label>
                                <input id="p19-profile-phone" name="phone" type="tel" inputmode="numeric" maxlength="11" pattern="01[0-9]{9}" autocomplete="tel" placeholder="01XXXXXXXXX" value="{{ old('phone',$customer->phone) }}"></div>
                            <div><label for="p52-profile-gender">Gender (optional)</label>
                                <select id="p52-profile-gender" name="gender">
                                    <option value="">Select gender</option>
                                    @foreach(['male'=>'Male','female'=>'Female','other'=>'Other','prefer_not_to_say'=>'Prefer not to say'] as $key=>$label)
                                        <option value="{{ $key }}" @selected(old('gender',$customer->gender)===$key)>{{ $label }}</option>
                                    @endforeach
                                </select></div>
                            <div><label for="p52-profile-dob">Date of Birth (optional)</label>
                                <input id="p52-profile-dob" type="date" name="date_of_birth" max="{{ now()->format('Y-m-d') }}" value="{{ old('date_of_birth',$customer->date_of_birth?->format('Y-m-d')) }}"></div>
                            <div class="p52-profile-photo-field"><label for="p52-profile-photo">Profile Photo (optional)</label>
                                <div class="p52-profile-photo-picker">
                                    <span class="p52-profile-photo-thumb" data-p52-photo-preview>
                                        @if($profilePhotoUrl)
                                            <img src="{{ $profilePhotoUrl }}" alt="Your current profile photo">
                                        @else
                                            {{ mb_strtoupper(mb_substr($customer->name,0,1)) }}
                                        @endif
                                    </span>
                                    <div class="p52-profile-photo-actions"><input id="p52-profile-photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-p52-photo-input>
                                        <small>JPG, PNG or WebP, maximum 2 MB.</small>
                                        @if($profilePhotoUrl)
                                            <label class="p52-photo-remove"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label>
                                        @endif
                                    </div>
                                </div></div>
                        </div>
                    </section>
                    <section class="p52-profile-group p52-address-group" aria-labelledby="p52-address-title">
                        <div class="p52-profile-group-title"><span class="p52-profile-group-icon">@include('storefront.partials.icon', ['name'=>'pin'])</span>
                            <div><h2 id="p52-address-title">Address Information</h2><p>Your saved full delivery address can be reused during checkout.</p></div>
                        </div>
                        <div class="p52-profile-fields">
                            <div><label for="p52-profile-division">Division</label>
                                <select name="division" id="p52-profile-division">
                                    <option value="">Select division</option>
                                    @foreach(['Barishal','Chattogram','Dhaka','Khulna','Mymensingh','Rajshahi','Rangpur','Sylhet'] as $division)
                                        <option value="{{ $division }}" @selected(old('division',$customer->division)===$division)>{{ $division }}</option>
                                    @endforeach
                                </select></div>
                            <div><label for="p52-profile-district">District</label>
                                <input id="p52-profile-district" name="district" maxlength="100" value="{{ old('district',$customer->district) }}" placeholder="e.g. Jamalpur" autocomplete="address-level2"></div>
                            <div><label for="p52-profile-upazila">Upazila / Thana</label>
                                <input id="p52-profile-upazila" name="upazila" maxlength="100" value="{{ old('upazila',$customer->upazila) }}" placeholder="e.g. Melandah"></div>
                            <div class="p52-address-full"><label for="p19-profile-address">Full Delivery Address</label>
                                <textarea id="p19-profile-address" name="address" rows="3" maxlength="2000" autocomplete="street-address" placeholder="House, road, area and nearby landmark">{{ old('address',$customer->address) }}</textarea></div>
                        </div>
                    </section>
                    <div class="p52-profile-submit"><span>Your information is saved to your private customer account.</span>
                        <button type="submit" class="p08-primary p52-profile-save">Save Profile</button></div>
                </form>
            </section>
        @endif
    </div>
</div>
@endsection
