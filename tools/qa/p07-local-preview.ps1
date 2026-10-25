# Read-only P07 browser preview. Runs only on localhost with the existing restricted MySQL test account.
# No .env edits, migrations, seeding, or writes. Stop with Ctrl+C when finished.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$website = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$project = Split-Path -Parent $website
$secretPath = Join-Path $project '.p01-runtime\secrets\p01-db-password'
if (-not (Test-Path -LiteralPath $secretPath -PathType Leaf)) {
    throw 'Restricted p01_tester secret file missing. Do not use the original .env password.'
}
$secret = [System.IO.File]::ReadAllText($secretPath).TrimEnd("`r", "`n")
if ([string]::IsNullOrWhiteSpace($secret)) { throw 'Restricted test password is empty.' }

$keys = @('P02_TEST_DB_PASSWORD','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME',
    'DB_PASSWORD','DB_URL','DB_SOCKET','APP_ENV','APP_DEBUG','APP_KEY',
    'P07_CATALOG_TEST_ENABLED')
$previous = @{}
foreach ($key in $keys) { $previous[$key] = [Environment]::GetEnvironmentVariable($key, 'Process') }
try {
    $env:P02_TEST_DB_PASSWORD = $secret
    $env:DB_CONNECTION = 'mysql'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = '3306'
    $env:DB_DATABASE = 'p01_campaign_test'
    $env:DB_USERNAME = 'p01_tester'
    $env:DB_PASSWORD = $secret
    $env:DB_URL = ''
    $env:DB_SOCKET = ''
    $env:APP_ENV = 'local'
    $env:APP_DEBUG = 'false'
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    $env:APP_KEY = 'base64:' + [Convert]::ToBase64String($bytes)
    $env:P07_CATALOG_TEST_ENABLED = 'true'
    Remove-Variable secret -ErrorAction SilentlyContinue

    Push-Location $website
    try {
        php tools/qa/p02-mysql-preflight.php
        if ($LASTEXITCODE -ne 0) { throw 'Isolated MySQL preflight refused preview. No site started.' }
        Write-Host 'Local test-only browse: http://127.0.0.1:8088/storefront/shop'
        Write-Host 'Published homepage (only after P05 publish): http://127.0.0.1:8088/storefront/home'
        Write-Host 'Press Ctrl+C to stop. Existing / remains the campaign homepage.'
        php artisan serve --host=127.0.0.1 --port=8088
    } finally { Pop-Location }
}
finally {
    foreach ($key in $keys) {
        [Environment]::SetEnvironmentVariable($key, $previous[$key], 'Process')
    }
    Remove-Variable secret,bytes -ErrorAction SilentlyContinue
}
