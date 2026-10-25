# P09 local-only HTTP CSRF/cookie check. NEVER touches production configuration.
# Both POST requests use INVALID product_variant_id=0, so they cannot write business rows.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$secretFile = Join-Path (Split-Path -Parent $root) '.p01-runtime\secrets\p01-db-password'
$keys = @('P02_TEST_DB_PASSWORD','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE',
    'DB_USERNAME','DB_PASSWORD','DB_URL','DB_SOCKET','APP_ENV','APP_DEBUG','APP_KEY',
    'SESSION_DRIVER','CACHE_STORE','MAIL_MAILER','QUEUE_CONNECTION','P07_CATALOG_TEST_ENABLED',
    'P08_CART_TEST_ENABLED','P08_CHECKOUT_TEST_ENABLED')
$previous = @{}
foreach ($key in $keys) { $previous[$key] = [Environment]::GetEnvironmentVariable($key, 'Process') }
$server = $null
$client = $null
$tcp = $null
$stdout = Join-Path ([IO.Path]::GetTempPath()) ('p09-csrf-out-' + [guid]::NewGuid().ToString('N') + '.txt')
$stderr = Join-Path ([IO.Path]::GetTempPath()) ('p09-csrf-err-' + [guid]::NewGuid().ToString('N') + '.txt')
try {
    if (-not (Test-Path -LiteralPath $secretFile -PathType Leaf)) {
        throw 'p01_tester restricted secret file missing; do not use the original DB password.'
    }
    $tcp = [System.Net.Sockets.TcpClient]::new()
    try {
        $tcp.Connect('127.0.0.1', 8089)
        throw 'Port 8089 is occupied. Stop that service before this isolated probe.'
    } catch [System.Net.Sockets.SocketException] {
        # Expected: local test port is currently closed.
    } finally { $tcp.Dispose(); $tcp = $null }

    $secret = [IO.File]::ReadAllText($secretFile).TrimEnd("`r", "`n")
    if ([string]::IsNullOrWhiteSpace($secret)) { throw 'Empty restricted test password.' }
    $env:P02_TEST_DB_PASSWORD = $secret
    $env:DB_PASSWORD = $secret
    $env:DB_CONNECTION = 'mysql'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = '3306'
    $env:DB_DATABASE = 'p01_campaign_test'
    $env:DB_USERNAME = 'p01_tester'
    $env:DB_URL = ''
    $env:DB_SOCKET = ''
    $env:APP_ENV = 'local'
    $env:APP_DEBUG = 'false'
    $bytes = [byte[]]::new(32)
    [Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    $env:APP_KEY = 'base64:' + [Convert]::ToBase64String($bytes)
    $env:SESSION_DRIVER = 'cookie'
    $env:CACHE_STORE = 'array'
    $env:MAIL_MAILER = 'array'
    $env:QUEUE_CONNECTION = 'sync'
    $env:P07_CATALOG_TEST_ENABLED = 'true'
    $env:P08_CART_TEST_ENABLED = 'true'
    $env:P08_CHECKOUT_TEST_ENABLED = 'false'
    Remove-Variable secret -ErrorAction SilentlyContinue

    Push-Location $root
    try {
        php tools/qa/p02-mysql-preflight.php
        if ($LASTEXITCODE -ne 0) { throw 'Restricted schema/account preflight rejected the browser probe.' }
        $server = Start-Process -FilePath (Get-Command php).Source -ArgumentList @(
            'artisan','serve','--host=127.0.0.1','--port=8089') -PassThru -NoNewWindow `
            -RedirectStandardOutput $stdout -RedirectStandardError $stderr

        $handler = [Net.Http.HttpClientHandler]::new()
        $handler.AllowAutoRedirect = $false
        $handler.UseCookies = $true
        $handler.CookieContainer = [Net.CookieContainer]::new()
        $client = [Net.Http.HttpClient]::new($handler)
        $client.Timeout = [TimeSpan]::FromSeconds(8)
        $uri = [uri]'http://127.0.0.1:8089/storefront/cart'
        $ready = $false
        for ($attempt = 0; $attempt -lt 25; $attempt++) {
            try {
                $get = $client.GetAsync($uri).GetAwaiter().GetResult()
                if ([int]$get.StatusCode -eq 200) { $ready = $true; break }
            } catch { Start-Sleep -Milliseconds 180 }
            Start-Sleep -Milliseconds 180
        }
        if (-not $ready) { throw 'Local restricted cart GET did not return 200.' }
        Write-Host 'PASS Local cart GET uses restricted test DB (200)'
        $cookies = $handler.CookieContainer.GetCookies($uri)
        $xsrf = $cookies['XSRF-TOKEN']
        if ($null -eq $xsrf) { throw 'Laravel did not provide an XSRF-TOKEN cookie.' }

        $payload = [Collections.Generic.Dictionary[string,string]]::new()
        $payload.Add('product_variant_id','0')  # Impossible by validation: NO CART WRITE
        $payload.Add('quantity','1')
        $payload.Add('cart_version','0')
        $postUri = [uri]'http://127.0.0.1:8089/storefront/cart/items'

        $invalid = [Net.Http.HttpRequestMessage]::new([Net.Http.HttpMethod]::Post, $postUri)
        $invalid.Content = [Net.Http.FormUrlEncodedContent]::new($payload)
        $without = $client.SendAsync($invalid).GetAwaiter().GetResult()
        if ([int]$without.StatusCode -ne 419) {
            throw ('Missing-CSRF POST expected 419; got ' + [int]$without.StatusCode)
        }
        Write-Host 'PASS Missing-CSRF POST rejected with 419'

        $valid = [Net.Http.HttpRequestMessage]::new([Net.Http.HttpMethod]::Post, $postUri)
        $valid.Headers.TryAddWithoutValidation('X-XSRF-TOKEN', [uri]::UnescapeDataString($xsrf.Value)) | Out-Null
        $valid.Content = [Net.Http.FormUrlEncodedContent]::new($payload)
        $with = $client.SendAsync($valid).GetAwaiter().GetResult()
        if ([int]$with.StatusCode -ne 302 -and [int]$with.StatusCode -ne 422) {
            throw ('Valid-CSRF/invalid-product POST expected form validation redirect; got ' + [int]$with.StatusCode)
        }
        Write-Host 'PASS Valid-CSRF POST reached product validation, not order/cart mutation'
        Write-Host 'RESULT: P09 3/3 REAL LOCAL HTTP CSRF checks PASS (no business-row writes)'
    } finally { Pop-Location }
} finally {
    if ($null -ne $client) { $client.Dispose() }
    if ($null -ne $server) {
        try { Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue } catch {}
        $server.Dispose()
    }
    foreach ($key in $keys) {
        [Environment]::SetEnvironmentVariable($key, $previous[$key], 'Process')
    }
    foreach ($temp in @($stdout, $stderr)) {
        if (Test-Path -LiteralPath $temp) { Remove-Item -LiteralPath $temp -Force -ErrorAction SilentlyContinue }
    }
    Remove-Variable secret,bytes -ErrorAction SilentlyContinue
}
