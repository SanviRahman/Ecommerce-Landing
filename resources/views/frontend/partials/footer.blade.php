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

    try {
        if ($siteSetting && method_exists($siteSetting, 'getFirstMediaUrl')) {
            $whiteLogo = $siteSetting->getFirstMediaUrl('site_white_logo')
                ?: $siteSetting->getFirstMediaUrl('site_logo');
        }
    } catch (\Throwable $e) {
        $whiteLogo = null;
    }

    $footerPhone = $siteSetting->phone
        ?? $siteSetting->hotline
        ?? $siteSetting->mobile
        ?? $siteSetting->contact_number
        ?? null;

    $footerPhoneUrl = $footerPhone
        ? 'tel:' . preg_replace('/[^\d+]/', '', $footerPhone)
        : null;

    $footerEmail = $siteSetting->email ?? null;

    $footerEmailUrl = $footerEmail
        ? 'mailto:' . $footerEmail
        : null;
@endphp

@push('css')
<style>
    .front-footer {
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(circle at 8% 12%, rgba(34, 197, 94, 0.18), transparent 26%),
            radial-gradient(circle at 92% 18%, rgba(34, 197, 94, 0.12), transparent 24%),
            linear-gradient(135deg, #0f172a 0%, #111827 52%, #020617 100%);
        color: #cbd5e1;
        padding: 78px 0 0;
    }

    .front-footer::before {
        content: '';
        position: absolute;
        width: 260px;
        height: 260px;
        left: -90px;
        bottom: -90px;
        border-radius: 50%;
        background: rgba(34, 197, 94, 0.10);
        filter: blur(1px);
        pointer-events: none;
    }

    .front-footer::after {
        content: '';
        position: absolute;
        width: 320px;
        height: 320px;
        right: -120px;
        top: -110px;
        border-radius: 50%;
        background: rgba(34, 197, 94, 0.08);
        pointer-events: none;
    }

    .front-footer .container {
        position: relative;
        z-index: 2;
    }

    .footer-card {
        height: 100%;
        padding: 26px 24px;
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 20px;
        background: rgba(15, 23, 42, 0.48);
        backdrop-filter: blur(8px);
        box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
        transition: 0.25s ease;
    }

    .footer-card:hover {
        transform: translateY(-4px);
        border-color: rgba(34, 197, 94, 0.38);
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.24);
    }

    .footer-brand {
        display: flex;
        align-items: center;
        gap: 13px;
        margin-bottom: 18px;
    }

    .footer-logo-img {
        max-width: 160px;
        max-height: 64px;
        object-fit: contain;
    }

    .brand-mark {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 12px 26px rgba(34, 197, 94, 0.25);
    }

    .brand-text {
        color: #ffffff;
        font-size: 25px;
        font-weight: 900;
        line-height: 1.2;
    }

    .footer-text {
        color: #cbd5e1;
        font-size: 16px;
        line-height: 1.85;
        margin-bottom: 22px;
    }

    .footer-title {
        position: relative;
        color: #ffffff;
        font-size: 20px;
        font-weight: 900;
        margin-bottom: 26px;
        padding-bottom: 14px;
    }

    .footer-title::before {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 44px;
        height: 3px;
        border-radius: 999px;
        background: #22c55e;
    }

    .footer-title::after {
        content: '';
        position: absolute;
        left: 52px;
        bottom: 0;
        width: 10px;
        height: 3px;
        border-radius: 999px;
        background: rgba(34, 197, 94, 0.45);
    }

    .footer-contact-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .footer-contact {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        color: #cbd5e1;
        margin: 0;
        font-size: 15.5px;
        line-height: 1.65;
    }

    .footer-contact a {
        color: #cbd5e1;
        text-decoration: none;
    }

    .footer-contact a:hover {
        color: #22c55e;
    }

    .footer-contact-icon {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(34, 197, 94, 0.12);
        color: #22c55e;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 2px;
    }

    .footer-socials {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 4px;
    }

    .footer-social-link {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(148, 163, 184, 0.20);
        color: #e2e8f0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        text-decoration: none !important;
        transition: 0.25s ease;
    }

    .footer-social-link:hover,
    .footer-social-link:focus {
        background: #22c55e;
        border-color: #22c55e;
        color: #ffffff;
        transform: translateY(-3px);
        box-shadow: 0 14px 28px rgba(34, 197, 94, 0.24);
    }

    .footer-quick-note {
        color: #94a3b8;
        font-size: 15px;
        line-height: 1.8;
        margin-top: 20px;
        margin-bottom: 0;
    }

    .footer-bottom {
        margin-top: 54px;
        padding: 22px 0;
        border-top: 1px solid rgba(148, 163, 184, 0.16);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        color: #94a3b8;
        font-size: 15px;
    }

    .footer-bottom a {
        color: #22c55e;
        font-weight: 800;
        text-decoration: none;
    }

    .footer-bottom a:hover {
        color: #86efac;
    }

    @media (max-width: 991px) {
        .front-footer {
            padding-top: 58px;
        }

        .footer-card {
            margin-bottom: 22px;
        }

        .footer-bottom {
            margin-top: 30px;
            flex-direction: column;
            text-align: center;
        }
    }

    @media (max-width: 575px) {
        .front-footer {
            padding-top: 48px;
        }

        .footer-card {
            padding: 22px 18px;
            border-radius: 18px;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            font-size: 21px;
        }

        .brand-text {
            font-size: 22px;
        }

        .footer-logo-img {
            max-width: 140px;
            max-height: 56px;
        }

        .footer-social-link {
            width: 44px;
            height: 44px;
        }
    }
</style>
@endpush

<footer class="front-footer" id="contact-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="footer-card">
                    <div class="footer-brand">
                        @if($whiteLogo)
                            <img src="{{ $whiteLogo }}"
                                 alt="{{ $websiteName }}"
                                 class="footer-logo-img"
                                 onerror="this.onerror=null;this.style.display='none';">
                        @else
                            <span class="brand-mark">
                                <i class="fas fa-seedling"></i>
                            </span>

                            <span class="brand-text">
                                {{ $websiteName }}
                            </span>
                        @endif
                    </div>

                    <p class="footer-text">
                        {{ $siteSetting->business_short_description ?? 'আমাদের কাছ থেকে পছন্দের পণ্য অর্ডার করুন সহজে, নিরাপদে এবং দ্রুত ডেলিভারিতে।' }}
                    </p>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
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

            <div class="col-lg-4 mb-4">
                <div class="footer-card">
                    <h5 class="footer-title">সোশ্যাল মিডিয়া</h5>

                    <p class="footer-text mb-3">
                        নতুন অফার, প্রোডাক্ট আপডেট এবং দ্রুত সাপোর্ট পেতে আমাদের সোশ্যাল মিডিয়ায় যুক্ত থাকুন।
                    </p>

                    <div class="footer-socials">
                        @forelse($socialMedias as $social)
                            <a href="{{ $social->link ?? '#' }}"
                               target="_blank"
                               class="footer-social-link"
                               title="{{ $social->platform_name ?? 'Social Media' }}">
                                <i class="{{ $social->icon_class ?: 'fas fa-link' }}"></i>
                            </a>
                        @empty
                            <a href="#" class="footer-social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="footer-social-link" title="Messenger"><i class="fab fa-facebook-messenger"></i></a>
                            <a href="#" class="footer-social-link" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <a href="#" class="footer-social-link" title="Phone"><i class="fas fa-phone"></i></a>
                            <a href="#" class="footer-social-link" title="Email"><i class="far fa-envelope"></i></a>
                        @endforelse
                    </div>

                    <p class="footer-quick-note">
                        অর্ডার ও যেকোনো জিজ্ঞাসার জন্য আমাদের সাথে সরাসরি যোগাযোগ করুন।
                    </p>
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