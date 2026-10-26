<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Local Storefront Readiness</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; }
        body { margin: 0; background: #f2f6f3; color: #132b20; }
        main { max-width: 740px; padding: 2rem 1rem; margin: auto; }
        .panel { background: white; border: 1px solid #d4e4d9; border-radius: 16px; padding: 1.4rem; }
        h1 { margin-top: 0; }
        .hint { color: #476454; line-height: 1.6; }
        .checks { list-style: none; margin: 1.5rem 0; padding: 0; }
        .checks li { display:flex; align-items:baseline; justify-content:space-between; gap:1rem; padding:.9rem 0; border-top:1px solid #e7efe8; }
        .status { font-weight: 700; white-space: nowrap; }
        .good { color: #146e40; }
        .bad { color: #a14b0b; }
        a,button { display:inline-block; border:0; padding:.7rem 1rem; border-radius:8px; background:#165c3a; color:white; text-decoration:none; font-size:1rem; cursor:pointer; }
        button:focus-visible,a:focus-visible {outline:3px solid #65b0ff; outline-offset:2px}
        .secondary { background: #e7ede8; color:#183f2b; }
        .actions { display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
        .code { font-family:monospace; font-size:.9rem; background:#edf4ef; padding:.12rem .3rem; }
        .warn { color:#98410b; }
    </style>
</head>
<body><main><div class="panel">
    <h1>Local Storefront Readiness</h1>
    <p class="hint">This page checks why your Admin-only <strong>/storefront/home</strong> may be returning 404.
        It never opens public checkout, changes inventory, or publishes the campaign homepage.</p>
    @if(session('storefront_preview_issue'))<p class="warn" role="alert">{{ session('storefront_preview_issue') }}</p>@endif
    <ul class="checks">
        <li>Local environment and debug mode <strong class="status good">PASS</strong></li>
        <li>P14 local Homepage Builder consent and safe MySQL configuration
            <strong class="status {{ $configured ? 'good' : 'bad' }}">{{ $configured ? 'PASS' : 'ACTION NEEDED' }}</strong></li>
        <li>Actual approved local database/account identity
            <strong class="status {{ $verified ? 'good' : 'bad' }}">{{ $verified ? 'PASS' : 'ACTION NEEDED' }}</strong></li>
        <li>Read-only showroom approval for this browser session
            <strong class="status {{ $consented ? 'good' : 'bad' }}">{{ $consented ? 'PASS' : 'ACTION NEEDED' }}</strong></li>
    </ul>
    @unless($configured)
        <p class="hint">Review your local <span class="code">P14_LOCAL_HOMEPAGE_BUILDER_ENABLED=true</span>, <span class="code">APP_ENV=local</span> and <span class="code">APP_DEBUG=true</span>. Never post credentials or the contents of .env.</p>
    @endunless
    @if($configured && !$verified)
        <p class="hint">Actual database identity did not pass the existing P14 security gate. Do not bypass it or enable shopping on the original database.</p>
    @endif
    <div class="actions">
        @if($verified && !$sessionConsented)
            <form method="POST" action="{{ route('admin.storefront.readiness.enable') }}">@csrf
                <button type="submit">Enable read-only preview for this session</button>
            </form>
        @endif
        @if($ready)
            <a href="{{ route('storefront.home') }}">Open Storefront Homepage</a>
        @endif
        @if($sessionConsented)
            <form method="POST" action="{{ route('admin.storefront.readiness.disable') }}">@csrf
                <button type="submit" class="secondary">Disable session preview</button>
            </form>
        @endif
        <a class="secondary" href="{{ route('admin.storefront.home.index') }}">Homepage Builder</a>
    </div>
    <p class="hint">For functional Guest Cart, Customer Login and COD Checkout testing, use the <strong>separate restricted P26 sandbox</strong> (127.0.0.1:8088). This main database preview stays read-only.</p>
</div></main></body></html>
