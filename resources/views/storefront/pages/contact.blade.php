@extends('storefront.layouts.catalog')
@section('title', 'Contact Us')
@section('content')
<section class="p19-info-hero" data-reveal><p class="p19-caption">GET IN TOUCH</p><h1>Contact Us</h1>
    <p>For orders, delivery questions or general support, use one of our currently configured official contact channels.</p>
</section>
<section class="p19-contact-list" data-reveal aria-label="Contact options">
    @forelse($contacts as $contact)
        <a href="{{ $contact['href'] }}" @if($contact['external']) target="_blank" rel="noopener noreferrer" @endif>
            <strong>{{ $contact['label'] }}</strong><span>Contact us →</span>
        </a>
    @empty
        <p>Our contact channels have not been configured. Please check back later.</p>
    @endforelse
</section>
@endsection
