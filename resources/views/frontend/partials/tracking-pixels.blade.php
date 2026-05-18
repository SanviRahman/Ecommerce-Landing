@php
    use App\Models\TrackingPixel;

    $activePixels = TrackingPixel::query()
        ->active()
        ->where(function ($query) {
            $query->whereNotNull('pixel_id')
                ->where('pixel_id', '!=', '')
                ->orWhere(function ($q) {
                    $q->whereNotNull('script_code')
                        ->where('script_code', '!=', '');
                });
        })
        ->orderByRaw("FIELD(platform, 'gtm', 'meta', 'tiktok', 'google_analytics')")
        ->get();

    $gtmPixels = $activePixels->where('platform', 'gtm');
    $metaPixels = $activePixels->where('platform', 'meta')->filter(fn ($pixel) => ! empty($pixel->pixel_id))->unique('pixel_id')->values();
    $tiktokPixels = $activePixels->where('platform', 'tiktok')->filter(fn ($pixel) => ! empty($pixel->pixel_id))->unique('pixel_id')->values();
    $gaPixels = $activePixels->where('platform', 'google_analytics')->filter(fn ($pixel) => ! empty($pixel->pixel_id))->unique('pixel_id')->values();
@endphp

<script>
    window.dataLayer = window.dataLayer || [];
</script>

{{-- Google Tag Manager --}}
@foreach($gtmPixels as $pixel)
    @if(! empty($pixel->script_code))
        {!! $pixel->script_code !!}
    @endif
@endforeach

{{-- Meta / Facebook Pixel --}}
@if($metaPixels->isNotEmpty())
    <script>
        !(function(f,b,e,v,n,t,s){
            if(f.fbq) return;
            n=f.fbq=function(){
                n.callMethod ? n.callMethod.apply(n,arguments) : n.queue.push(arguments);
            };
            if(!f._fbq) f._fbq=n;
            n.push=n;
            n.loaded=true;
            n.version='2.0';
            n.queue=[];
            t=b.createElement(e);
            t.async=true;
            t.src=v;
            s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s);
        })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

        @foreach($metaPixels as $pixel)
            fbq('init', @json($pixel->pixel_id));
        @endforeach
    </script>

    <noscript>
        @foreach($metaPixels as $pixel)
            <img height="1"
                 width="1"
                 style="display:none"
                 alt=""
                 src="https://www.facebook.com/tr?id={{ urlencode($pixel->pixel_id) }}&ev=PageView&noscript=1">
        @endforeach
    </noscript>
@endif

{{-- TikTok Pixel --}}
@if($tiktokPixels->isNotEmpty())
    <script>
        !(function (w, d, t) {
            w.TiktokAnalyticsObject = t;
            var ttq = w[t] = w[t] || [];
            ttq.methods = [
                'page', 'track', 'identify', 'instances', 'debug', 'on', 'off',
                'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'
            ];
            ttq.setAndDefer = function (t, e) {
                t[e] = function () {
                    t.push([e].concat(Array.prototype.slice.call(arguments, 0)));
                };
            };
            for (var i = 0; i < ttq.methods.length; i++) {
                ttq.setAndDefer(ttq, ttq.methods[i]);
            }
            ttq.instance = function (t) {
                var e = ttq._i[t] || [];
                for (var n = 0; n < ttq.methods.length; n++) {
                    ttq.setAndDefer(e, ttq.methods[n]);
                }
                return e;
            };
            ttq.load = function (e, n) {
                var i = 'https://analytics.tiktok.com/i18n/pixel/events.js';
                ttq._i = ttq._i || {};
                ttq._i[e] = [];
                ttq._i[e]._u = i;
                ttq._t = ttq._t || {};
                ttq._t[e] = +new Date;
                ttq._o = ttq._o || {};
                ttq._o[e] = n || {};
                var o = document.createElement('script');
                o.type = 'text/javascript';
                o.async = true;
                o.src = i + '?sdkid=' + e + '&lib=' + t;
                var a = document.getElementsByTagName('script')[0];
                a.parentNode.insertBefore(o, a);
            };

            @foreach($tiktokPixels as $pixel)
                ttq.load(@json($pixel->pixel_id));
            @endforeach
        })(window, document, 'ttq');
    </script>
@endif

{{-- Google Analytics / GA4 --}}
@if($gaPixels->isNotEmpty())
    @php
        $firstGa = $gaPixels->first();
    @endphp

    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $firstGa->pixel_id }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date);

        @foreach($gaPixels as $pixel)
            gtag('config', @json($pixel->pixel_id), {
                send_page_view: false
            });
        @endforeach
    </script>
@endif