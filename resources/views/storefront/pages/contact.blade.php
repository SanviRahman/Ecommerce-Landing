@extends('storefront.layouts.catalog')
@section('title', 'Contact Us')
@section('content')
@php $details = $contactDetails ?? app(\App\Support\StorefrontContactDetails::class)->get(); @endphp
<nav class="p37-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><strong>Contact Us</strong></nav>
<section class="p37-contact-hero" data-reveal>
    <div><span class="p37-eyebrow">LET'S TALK</span><h1>Contact <em>Us</em></h1>
        <p>Have a question about products, an order, or delivery? Send a message to our support team.</p></div>
    <div class="p37-contact-hero-pill"><span aria-hidden="true">@include('storefront.partials.icon', ['name' => 'headphones'])</span>
        <div><strong>We're here to help</strong><small>Send a message using the contact form below.</small></div></div>
</section>
<section class="p37-contact-tiles" aria-label="Official business contact details" data-reveal>
    <div class="p37-contact-tile"><span class="p37-tile-icon" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'phone'])</span><div><small>Call Us</small>
        @if($details['phone'])<a href="{{ $details['phone_href'] }}">{{ $details['phone'] }}</a>@else<strong>Not configured yet</strong>@endif
    </div></div>
    <div class="p37-contact-tile"><span class="p37-tile-icon" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'mail'])</span><div><small>Email Us</small>
        @if($details['email'])<a href="{{ $details['email_href'] }}">{{ $details['email'] }}</a>@else<strong>Not configured yet</strong>@endif
    </div></div>
    <div class="p37-contact-tile"><span class="p37-tile-icon" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'pin'])</span><div><small>Our Location</small>
        <strong>{{ $details['address'] ?: 'Set your location in Site Settings' }}</strong>
    </div></div>
    <div class="p37-contact-tile"><span class="p37-tile-icon" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'clock'])</span><div><small>Support Hours</small>
        <strong>{{ $details['working_hours'] ?: 'Contact us anytime' }}</strong>
    </div></div>
</section>
<div class="p37-contact-content" data-reveal>
    <section class="p37-contact-form-panel" aria-labelledby="p37-message-title">
        <span class="p37-eyebrow">SEND A MESSAGE</span><h2 id="p37-message-title">How can we help?</h2>
        <p>Your message is saved securely for the support team to review.</p>
        @if(session('contact_success'))<div class="p37-form-success" role="status">{{ session('contact_success') }}</div>@endif
        @if($errors->any())<div class="p37-form-error" role="alert"><strong>Check the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('storefront.contact.submit') }}" class="p37-contact-form">
            @csrf
            <div class="p37-honeypot" aria-hidden="true"><label for="company_website">Leave this field empty</label><input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off"></div>
            <div class="p37-contact-inputs">
                <div><label for="contact_name">Full Name <b>*</b></label><input id="contact_name" type="text" name="name" maxlength="120" autocomplete="name" value="{{ old('name') }}" placeholder="Your full name" required></div>
                <div><label for="contact_email">Email Address <b>*</b></label><input id="contact_email" type="email" name="email" maxlength="190" autocomplete="email" value="{{ old('email') }}" placeholder="you@example.com" required></div>
                <div><label for="contact_phone">Phone Number</label><input id="contact_phone" type="tel" name="phone" maxlength="24" autocomplete="tel" value="{{ old('phone') }}" placeholder="01XXXXXXXXX"></div>
                <div><label for="contact_subject">Subject</label><select id="contact_subject" name="subject">
                    @foreach(['general'=>'General enquiry','product'=>'Product enquiry','order'=>'Order help','delivery'=>'Delivery question','returns'=>'Return or refund','other'=>'Other'] as $key => $text)
                        <option value="{{ $key }}" @selected(old('subject','general') === $key)>{{ $text }}</option>
                    @endforeach
                </select></div>
            </div>
            <div><label for="contact_message">Message <b>*</b></label><textarea id="contact_message" name="message" rows="7" maxlength="3000" placeholder="How can we help?" required>{{ old('message') }}</textarea></div>
            <div class="p37-contact-submit"><small>Protected by form validation and request limits.</small><button type="submit">Send Message @include('storefront.partials.icon', ['name'=>'arrow-right'])</button></div>
        </form>
    </section>
    <aside class="p37-contact-map-panel" aria-label="Business location">
        <span class="p37-eyebrow">OUR LOCATION</span><h2>Find us on the map</h2>
        @if($details['map_embed'])
            <iframe title="Map showing our business location" src="{{ $details['map_embed'] }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            <a class="p37-map-link" href="{{ $details['map_link'] }}" rel="noopener noreferrer" target="_blank">Open in Google Maps ↗</a>
        @else
            <div class="p37-map-placeholder"><strong>Map not configured</strong><p>Add your business address in Admin → Site Settings to display your actual map.</p></div>
        @endif
        <div class="p37-map-help"><strong>Need a quick answer?</strong><p>Find answers to common questions before sending a message.</p><a href="{{ route('storefront.help') }}">Browse Help & FAQ →</a></div>
    </aside>
</div>
@endsection
