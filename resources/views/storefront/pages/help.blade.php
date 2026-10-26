@extends('storefront.layouts.catalog')
@section('title', 'Help & Frequently Asked Questions')
@section('content')
<section class="p19-info-hero" data-reveal><p class="p19-caption">CUSTOMER CARE</p><h1>How can we help?</h1>
    <p>Find answers from our approved store information. You can also reach us through our configured contact channels.</p>
    <a class="p19-info-link" href="{{ route('storefront.contact') }}">Contact support →</a>
</section>
<section class="p19-faq-list" data-reveal aria-label="Frequently asked questions"><h2>Common questions</h2>
    @forelse($faqs as $faq)
        <details class="p19-faq-item"><summary>{{ $faq->question }}<span aria-hidden="true">+</span></summary>
            <div>{{ $faq->answer }}</div>
        </details>
    @empty
        <p>There are no published general FAQs yet. Please contact our support team.</p>
    @endforelse
</section>
@endsection
