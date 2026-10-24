@php
    $siteSetting = $siteSetting ?? \App\Models\SiteSetting::query()
        ->where('status', true)
        ->latest()
        ->first();

    $socialMedias = $socialMedias ?? \App\Models\SocialMedia::query()
        ->where('status', true)
        ->latest()
        ->get();

    $footerSectionActive = (bool) ($campaign?->footer_section_status ?? true);
    $socialMediaSectionActive = (bool) ($campaign?->social_media_section_status ?? true);
    $footerColumnClass = $socialMediaSectionActive ? 'col-lg-4' : 'col-lg-6';

    $websiteName = $siteSetting?->website_name ?? config('app.name', 'EcoEats');

    $whiteLogo = null;

    try {
        if ($siteSetting && method_exists($siteSetting, 'getFirstMediaUrl')) {
            $whiteLogo = $siteSetting->getFirstMediaUrl('site_white_logo')
                ?: $siteSetting->getFirstMediaUrl('site_logo');
        }
    } catch (\Throwable $e) {
        $whiteLogo = null;
    }

    $footerPhone = $siteSetting?->phone
        ?? $siteSetting?->hotline
        ?? $siteSetting?->mobile
        ?? $siteSetting?->contact_number
        ?? null;

    $footerPhoneUrl = $footerPhone
        ? 'tel:' . preg_replace('/[^\d+]/', '', $footerPhone)
        : null;

    $footerEmail = $siteSetting?->email ?? null;

    $footerEmailUrl = $footerEmail
        ? 'mailto:' . $footerEmail
        : null;
@endphp

{{-- Shared footer styles are loaded from public/frontend/css/footer.css by the layout. --}}

@if($footerSectionActive)
<footer class="front-footer" id="contact-section">
    <div class="container">
        <div class="row">
            <div class="{{ $footerColumnClass }} mb-4">
                <div class="footer-card">
                    <div class="footer-brand">
                        @if($whiteLogo)
                            <img src="{{ $whiteLogo }}"
                                 alt="{{ $websiteName }}"
                                 class="footer-logo-img"
                                 data-hide-on-error="1">
                        @endif
                    </div>

                    <p class="footer-text">
                        {{ $siteSetting?->business_short_description ?? 'আমাদের কাছ থেকে পছন্দের পণ্য অর্ডার করুন সহজে, নিরাপদে এবং দ্রুত ডেলিভারিতে।' }}
                    </p>
                </div>
            </div>

            <div class="{{ $footerColumnClass }} mb-4">
                <div class="footer-card">
                    <h5 class="footer-title">আমাদের ঠিকানা</h5>

                    <div class="footer-contact-list">
                        @if($siteSetting?->address)
                            <p class="footer-contact">
                                <span class="footer-contact-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </span>
                                <span>{{ $siteSetting->address }}</span>
                            </p>
                        @endif

                        @if($footerEmail)
                            <p class="footer-contact">
                                <span class="footer-contact-icon">
                                    <i class="far fa-envelope"></i>
                                </span>
                                <span>
                                    <a href="{{ $footerEmailUrl }}">{{ $footerEmail }}</a>
                                </span>
                            </p>
                        @endif

                        @if($footerPhone)
                            <p class="footer-contact">
                                <span class="footer-contact-icon">
                                    <i class="fas fa-headphones-alt"></i>
                                </span>
                                <span>
                                    <a href="{{ $footerPhoneUrl }}">{{ $footerPhone }}</a>
                                </span>
                            </p>
                        @endif

                        @if($siteSetting?->working_hours)
                            <p class="footer-contact">
                                <span class="footer-contact-icon">
                                    <i class="far fa-clock"></i>
                                </span>
                                <span>{{ $siteSetting->working_hours }}</span>
                            </p>
                        @endif

                        @unless($siteSetting?->address || $footerEmail || $footerPhone || $siteSetting?->working_hours)
                            <p class="footer-contact">
                                <span class="footer-contact-icon">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                                <span>যোগাযোগের তথ্য শীঘ্রই যুক্ত করা হবে।</span>
                            </p>
                        @endunless
                    </div>
                </div>
            </div>

            @if($socialMediaSectionActive)
            <div class="col-lg-4 mb-4">
                <div class="footer-card">
                    <h5 class="footer-title">সোশ্যাল মিডিয়া</h5>

                    <p class="footer-text mb-3">
                        নতুন অফার, প্রোডাক্ট আপডেট এবং দ্রুত সাপোর্ট পেতে আমাদের সোশ্যাল মিডিয়ায় যুক্ত থাকুন।
                    </p>

                    <div class="footer-socials">
                        @foreach($socialMedias as $social)
                            @php
                                $socialUrl = trim((string) ($social->link ?? ''));
                                $validSocialUrl = preg_match('~^(https?://|mailto:|tel:)~i', $socialUrl) === 1;
                            @endphp
                            @if($validSocialUrl)
                                <a href="{{ $socialUrl }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="footer-social-link"
                                   title="{{ $social->platform_name ?? 'Social Media' }}">
                                    <i class="{{ $social->icon_class ?: 'fas fa-link' }}"></i>
                                </a>
                            @endif
                        @endforeach
                    </div>

                    <p class="footer-quick-note">
                        অর্ডার ও যেকোনো জিজ্ঞাসার জন্য আমাদের সাথে সরাসরি যোগাযোগ করুন।
                    </p>
                </div>
            </div>
            @endif
        </div>

        <div class="footer-bottom">
            <span>
                © 2020-2026 {{ $websiteName }}. All rights reserved.
            </span>

            <span>
                Developed By
                <a href="https://digitalvai.com/" target="_blank">DESHBAJAR</a>
            </span>
        </div>
    </div>
</footer>
@endif