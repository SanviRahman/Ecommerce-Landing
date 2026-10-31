# P13: start a localhost-only AdminLTE browser QA server using P12 test source.
# No schema writes, migrations, secrets printed, or original DB connection.
# Only an existing synthetic test admin can sign in; no account created here.
[CmdletBinding()]
param(
    [string]$ProjectRoot = 'D:\Ecommerce-Landing\website-p12-test',
    [int]$Port = 8089
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ($Port -lt 1025 -or $Port -gt 65535) { throw 'Choose a non-privileged local port.' }
$resolved = (Resolve-Path -LiteralPath $ProjectRoot -ErrorAction Stop).Path
if ((Split-Path -Leaf $resolved) -ne 'website-p12-test') {
    throw 'BLOCKED: P13 browser runs only from the P12 isolated test copy, never the original website.'
}
foreach ($required in @('artisan', 'routes\admin.php', 'routes\web.php', 'vendor\autoload.php')) {
    if (-not (Test-Path -LiteralPath (Join-Path $resolved $required) -PathType Leaf)) {
        throw "BLOCKED: Missing $required. Repair test-copy ZIP before running."
    }
}
foreach ($unsafeCache in @('bootstrap\cache\config.php', 'bootstrap\cache\routes-v7.php')) {
    if (Test-Path -LiteralPath (Join-Path $resolved $unsafeCache) -PathType Leaf) {
        throw "BLOCKED: test copy contains cached environment/routes: $unsafeCache. Review it separately."
    }
}
$envFile = Join-Path $resolved '.env'
if (-not (Test-Path -LiteralPath $envFile -PathType Leaf)) { throw 'P12 synthetic test .env is missing.' }
$synthetic = [System.IO.File]::ReadAllText($envFile)
if ($synthetic -notmatch '(?m)^APP_ENV=testing\s*$' -or
    $synthetic -notmatch '(?m)^DB_CONNECTION=sqlite\s*$' -or
    $synthetic -notmatch '(?m)^DB_DATABASE=:memory:\s*$') {
    throw 'BLOCKED: Only a P12 synthetic test-copy .env is allowed. Never use original .env.'
}
if ($synthetic -match '(?im)^(?:DB_USERNAME|DB_PASSWORD|DB_HOST|DB_PORT|DB_URL|DB_SOCKET)\s*=') {
    throw 'BLOCKED: unexpected MySQL credentials/settings in P12 synthetic .env.'
}
$keyMatch = [regex]::Match($synthetic, '(?m)^APP_KEY=(base64:[A-Za-z0-9+/=]+)\s*$')
if (-not $keyMatch.Success) { throw 'BLOCKED: P12 synthetic test key was not found.' }
$testKey = $keyMatch.Groups[1].Value  # P12 synthetic test key; never the original APP_KEY
$secretPath = 'D:\Ecommerce-Landing\.p01-runtime\secrets\p01-db-password'
if (-not (Test-Path -LiteralPath $secretPath -PathType Leaf)) { throw 'P02 restricted DB credential file missing.' }
$secret = [System.IO.File]::ReadAllText($secretPath).TrimEnd("`r", "`n")
if ([string]::IsNullOrWhiteSpace($secret)) { throw 'P02 restricted DB password is empty.' }

$values = @{
    'P02_TEST_DB_PASSWORD' = $secret;
    'DB_CONNECTION' = 'mysql'; 'DB_HOST' = '127.0.0.1'; 'DB_PORT' = '3306';
    'DB_DATABASE' = 'p01_campaign_test'; 'DB_USERNAME' = 'p01_tester';
    'DB_PASSWORD' = $secret; 'DB_URL' = ''; 'DB_SOCKET' = '';
    'APP_ENV' = 'local'; 'APP_DEBUG' = 'false'; 'APP_KEY' = $testKey;
    'APP_URL' = "http://127.0.0.1:$Port";
    'SESSION_DRIVER' = 'file'; 'SESSION_COOKIE' = 'p13_isolated_qa_session';
    'SESSION_DOMAIN' = ''; 'CACHE_STORE' = 'array'; 'QUEUE_CONNECTION' = 'sync';
    'MAIL_MAILER' = 'array'; 'FILESYSTEM_DISK' = 'local';
    'P07_CATALOG_TEST_ENABLED' = 'true';
    'P08_CART_TEST_ENABLED' = 'false'; 'P08_CHECKOUT_TEST_ENABLED' = 'false'
}
$old = @{}
foreach ($name in $values.Keys) { $old[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
try {
    foreach ($name in $values.Keys) { [Environment]::SetEnvironmentVariable($name, $values[$name], 'Process') }
    Remove-Variable secret, testKey, synthetic, values -ErrorAction SilentlyContinue
    Push-Location -LiteralPath $resolved
    try {
        php tools/qa/p02-mysql-preflight.php
        if ($LASTEXITCODE -ne 0) { throw 'P02 restricted DB preflight refused connection.' }
        php tools/qa/p13-browser-readiness.php
        if ($LASTEXITCODE -ne 0) { throw 'P13 browser QA blocked: no approved synthetic admin / required schema.' }
        Write-Host "P13 isolated admin browser: http://127.0.0.1:$Port/admin/storefront/home"
        Write-Host 'Use credentials of an EXISTING synthetic p01_campaign_test admin only.'
        Write-Host 'No main database, migration, seeding, or live storefront activation. Ctrl+C to stop.'
        # Laravel serve's default environment reload may drop parent DB_* overrides.
        # --no-reload ensures the restricted test-only process environment is passed through.
        php artisan serve --no-reload --host=127.0.0.1 --port=$Port
    }
    finally { Pop-Location }
}
finally {
    foreach ($name in $old.Keys) {
        [Environment]::SetEnvironmentVariable($name, $old[$name], 'Process')
    }
    Remove-Variable old, keyMatch -ErrorAction SilentlyContinue
}
