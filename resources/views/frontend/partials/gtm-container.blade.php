{{-- Admin-trusted GTM snippets remain compatible; an ID-only GTM integration
     uses Google's standard container bootstrap. Keep <noscript> out of <head>. --}}
@php
    $gtmId = strtoupper(trim((string) ($container->pixel_id ?? '')));
    $gtmIdValid = preg_match('/\AGTM-[A-Z0-9]+\z/D', $gtmId) === 1;
    $gtmCode = trim((string) ($container->script_code ?? ''));
    preg_match_all('~<noscript\b[^>]*>.*?</noscript>~is', $gtmCode, $gtmNoscriptMatches);
    $gtmBodyNoscript = implode("\n", $gtmNoscriptMatches[0]);
    $gtmHeadCode = trim((string) preg_replace('~<noscript\b[^>]*>.*?</noscript>~is', '', $gtmCode));
    $gtmHasScript = preg_match('~<script\b~i', $gtmHeadCode) === 1;
    // A saved script may just be a custom dataLayer snippet, not a GTM loader.
    // When an actual container ID is configured, ensure one real GTM loader
    // exists instead of assuming any <script> automatically starts GTM.
    $gtmHasLoader = preg_match('~(?:https?:)?//www\.googletagmanager\.com/gtm\.js(?:\?|[\s\"\'])~i', $gtmHeadCode) === 1;
@endphp
@if($gtmHasScript)
    {{-- Preserve admin-configured code (including pre-existing full GTM snippets). --}}
    {!! $gtmHeadCode !!}
@endif
@if($gtmIdValid && ! $gtmHasLoader)
    {{-- Bootstrap the real container for ID-only or non-GTM legacy snippets.
         Google's GTM library, not custom fake pushes, owns gtm.dom and gtm.load. --}}
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;
        if(f && f.parentNode)f.parentNode.insertBefore(j,f);else d.head.appendChild(j);
        })(window,document,'script','dataLayer',@json($gtmId));
    </script>
@endif
@if($gtmBodyNoscript !== '' || $gtmIdValid)
    @push($noscriptStack)
        @if($gtmBodyNoscript !== '')
            {!! $gtmBodyNoscript !!}
        @else
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ rawurlencode($gtmId) }}"
                    height="0" width="0" style="display:none;visibility:hidden"
                    title="Google Tag Manager"></iframe></noscript>
        @endif
    @endpush
@endif
