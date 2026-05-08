@php
    $siteSetting = $siteSetting ?? \App\Models\SiteSetting::query()
        ->where('status', true)
        ->latest()
        ->first();

    $socialMedias = $socialMedias ?? \App\Models\SocialMedia::query()
        ->where('status', true)
        ->latest()
        ->get();

    $websiteName = $siteSetting->website_name ?? config('app.name', 'EcoEats');

    $whiteLogo = null;

    if ($siteSetting && method_exists($siteSetting, 'getFirstMediaUrl')) {
        $whiteLogo = $siteSetting->getFirstMediaUrl('site_white_logo')
            ?: $siteSetting->getFirstMediaUrl('site_logo');
    }
@endphp

<footer class="front-footer" id="contact-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="footer-brand d-flex align-items-center mb-3">
                    @if($whiteLogo)
                        <img src="{{ $whiteLogo }}" alt="{{ $websiteName }}" class="footer-logo-img">
                    @else
                        <span class="brand-mark">
                            <i class="fas fa-seedling"></i>
                        </span>

                        <span class="brand-text text-white">
                            {{ $websiteName }}
                        </span>
                    @endif
                </div>

                <p class="footer-text">
                    {{ $siteSetting->business_short_description ?? 'আমাদের কাছ থেকে পছন্দের পণ্য অর্ডার করুন সহজে, নিরাপদে এবং দ্রুত ডেলিভারিতে।' }}
                </p>
            </div>

            <div class="col-lg-4 mb-4">
                <h5 class="footer-title">আমাদের ঠিকানা</h5>

                @if($siteSetting?->address)
                    <p class="footer-contact">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        {{ $siteSetting->address }}
                    </p>
                @endif

                @if($siteSetting?->email)
                    <p class="footer-contact">
                        <i class="far fa-envelope mr-2"></i>
                        {{ $siteSetting->email }}
                    </p>
                @endif

                @if($siteSetting?->phone)
                    <p class="footer-contact">
                        <i class="fas fa-headphones-alt mr-2"></i>
                        {{ $siteSetting->phone }}
                    </p>
                @endif

                @if($siteSetting?->working_hours)
                    <p class="footer-contact">
                        <i class="far fa-clock mr-2"></i>
                        {{ $siteSetting->working_hours }}
                    </p>
                @endif
            </div>

            <div class="col-lg-4 mb-4">
                <h5 class="footer-title">সোশ্যাল মিডিয়া</h5>

                <div class="footer-socials">
                    @forelse($socialMedias as $social)
                        <a href="{{ $social->link ?? '#' }}"
                           target="_blank"
                           class="footer-social-link"
                           title="{{ $social->platform_name ?? 'Social Media' }}">
                            <i class="{{ $social->icon_class ?: 'fas fa-link' }}"></i>
                        </a>
                    @empty
                        <a href="#" class="footer-social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="footer-social-link"><i class="fab fa-facebook-messenger"></i></a>
                        <a href="#" class="footer-social-link"><i class="fab fa-whatsapp"></i></a>
                        <a href="#" class="footer-social-link"><i class="fas fa-phone"></i></a>
                        <a href="#" class="footer-social-link"><i class="far fa-envelope"></i></a>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <span>
                © 2020-2026 {{ $websiteName }}. All rights reserved.
            </span>

            <span>
                Developed By
                <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
            </span>
        </div>
    </div>
</footer>