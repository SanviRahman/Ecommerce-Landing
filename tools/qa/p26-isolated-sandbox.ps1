# P26 local isolated commerce QA launcher (127.0.0.1:8088).
# Never switches or migrates the original ecommerce_landing_db. Never seeds,
# resets, imports customer data, or exposes this QA instance to the LAN.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$website = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$project = Split-Path -Parent $website
$secretFile = Join-Path $project '.p01-runtime\secrets\p01-db-password'
$secret = $env:P02_TEST_DB_PASSWORD
if ([string]::IsNullOrWhiteSpace($secret) -and (Test-Path -LiteralPath $secretFile -PathType Leaf)) {
    $secret = [System.IO.File]::ReadAllText($secretFile).TrimEnd("`r", "`n")
}
if ([string]::IsNullOrWhiteSpace($secret)) {
    throw 'BLOCKED: restricted p01_tester credential unavailable. Configure the isolated test DB first. NEVER use the original DB password.'
}
if (Test-Path -LiteralPath (Join-Path $website 'bootstrap\cache\config.php') -PathType Leaf) {
    throw 'BLOCKED: shared cached Laravel config exists. Review the cache using your normal main-site procedure first; this script will not delete it.'
}
$changes = @{
    'P02_TEST_DB_PASSWORD' = $secret
    'DB_CONNECTION' = 'mysql'
    'DB_HOST' = '127.0.0.1'
    'DB_PORT' = '3306'
    'DB_DATABASE' = 'p01_campaign_test'
    'DB_USERNAME' = 'p01_tester'
    'DB_PASSWORD' = $secret
    'DB_URL' = ''
    'DB_SOCKET' = ''
    'APP_ENV' = 'local'
    'APP_DEBUG' = 'false'
    'APP_URL' = 'http://127.0.0.1:8088'
    'SESSION_DRIVER' = 'file'
    'SESSION_COOKIE' = 'p26_storefront_qa_session'
    'SESSION_DOMAIN' = ''
    'CACHE_STORE' = 'array'
    'QUEUE_CONNECTION' = 'database'
    'BROADCAST_CONNECTION' = 'log'
    'MAIL_MAILER' = 'log'
    'P25_LOCAL_STOREFRONT_BROWSE_ENABLED' = 'false'
    'P14_LOCAL_HOMEPAGE_BUILDER_ENABLED' = 'false'
    'P26_ISOLATED_COMMERCE_QA_ENABLED' = 'true'
    'P07_CATALOG_TEST_ENABLED' = 'false'
    'P08_CART_TEST_ENABLED' = 'false'
    'P08_CHECKOUT_TEST_ENABLED' = 'false'
    'P11_READ_ONLY_APPROVED' = 'YES_ISOLATED_ONLY'
}
# Fresh temporary key per QA process; the original APP_KEY remains untouched.
$keyBytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $rng.GetBytes($keyBytes)
} finally {
    $rng.Dispose()
}
$changes['APP_KEY'] = 'base64:' + [Convert]::ToBase64String($keyBytes)
$previous = @{}
foreach ($key in $changes.Keys) {
    $previous[$key] = [Environment]::GetEnvironmentVariable($key, 'Process')
    [Environment]::SetEnvironmentVariable($key, $changes[$key], 'Process')
}
Remove-Variable secret, keyBytes -ErrorAction SilentlyContinue
try {
    Push-Location $website
    try {
        php tools/qa/p02-mysql-preflight.php
        if ($LASTEXITCODE -ne 0) { throw 'BLOCKED P26: isolated P02 test account/grants preflight failed.' }
        php tools/qa/p26-isolated-schema-preflight.php
        if ($LASTEXITCODE -ne 0) { throw 'BLOCKED P26: isolated schema/account migration is not ready.' }
        php tools/qa/p11-readiness-review.php
        if ($LASTEXITCODE -ne 0) { throw 'BLOCKED P26: isolated P11 product/stock/shipping/homepage readiness is not green.' }
        Write-Host 'P26 QA ONLY: http://127.0.0.1:8088/storefront/home'
        Write-Host 'Cart, guest COD checkout and customer accounts are available ONLY on this isolated synthetic test database.'
        Write-Host 'No changes made to the original ecommerce_landing_db. Stop the QA server with Ctrl+C.'
        php artisan serve --host=127.0.0.1 --port=8088
        if ($LASTEXITCODE -ne 0) { throw 'P26 isolated test server exited with an error.' }
    } finally { Pop-Location }
} finally {
    foreach ($key in $previous.Keys) {
        [Environment]::SetEnvironmentVariable($key, $previous[$key], 'Process')
    }
    Remove-Variable changes, previous -ErrorAction SilentlyContinue
}
