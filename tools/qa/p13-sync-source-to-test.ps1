# P13: copy reviewed homepage QA source into the existing P12 isolated test copy.
# Scope: known P13 application/test files. NEVER copy .env, routes, vendor, uploads,
# production data, or arbitrary folders. Never modify the original website.
[CmdletBinding()]
param([string]$BaseDirectory = 'D:\Ecommerce-Landing')
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$main = Join-Path $BaseDirectory 'website'
$test = Join-Path $BaseDirectory 'website-p12-test'
foreach ($root in @($main, $test)) {
    if (-not (Test-Path -LiteralPath $root -PathType Container)) {
        throw "BLOCKED: expected project folder not found: $root"
    }
}
$main = (Resolve-Path -LiteralPath $main).Path
$test = (Resolve-Path -LiteralPath $test).Path
if ((Split-Path -Leaf $main) -ne 'website' -or
    (Split-Path -Leaf $test) -ne 'website-p12-test' -or
    (Split-Path -Parent $main) -ne (Split-Path -Parent $test)) {
    throw 'BLOCKED: source and isolated target folder identity do not match.'
}
if ($main -eq $test) { throw 'BLOCKED: source and target are the same folder.' }

# P12 test copy must have a fresh synthetic .env; never ingest website/.env.
$testEnvFile = Join-Path $test '.env'
if (-not (Test-Path -LiteralPath $testEnvFile -PathType Leaf)) {
    throw 'BLOCKED: P12 synthetic test .env not found. Do not copy the original .env.'
}
$testEnv = [System.IO.File]::ReadAllText($testEnvFile)
# Reject missing or DUPLICATE settings: a later DB_CONNECTION line must never
# override the safe P12 SQLite identity during source-file sync.
$requiredSettings = @{
    'APP_ENV' = 'testing'
    'DB_CONNECTION' = 'sqlite'
    'DB_DATABASE' = ':memory:'
}
foreach ($setting in $requiredSettings.Keys) {
    $pattern = '(?m)^' + [regex]::Escape($setting) + '=(.*)\r?$'
    $matches = [regex]::Matches($testEnv, $pattern)
    if ($matches.Count -ne 1 -or $matches[0].Groups[1].Value.Trim() -ne $requiredSettings[$setting]) {
        throw "BLOCKED: expected exactly one P12 synthetic setting: $setting"
    }
}
if ([regex]::Matches($testEnv, '(?m)^APP_KEY=base64:[A-Za-z0-9+/=]+\r?$').Count -ne 1 -or
    $testEnv -match '(?im)^\s*(?:DB_USERNAME|DB_PASSWORD|DB_HOST|DB_PORT|DB_URL|DB_SOCKET|DATABASE_URL)\s*=') {
    throw 'BLOCKED: test copy does not have the expected P12 synthetic environment.'
}
Remove-Variable testEnv -ErrorAction SilentlyContinue

foreach ($relative in @('composer.json', 'composer.lock', 'artisan', 'routes\admin.php', 'routes\web.php')) {
    foreach ($folder in @($main, $test)) {
        if (-not (Test-Path -LiteralPath (Join-Path $folder $relative) -PathType Leaf)) {
            throw "BLOCKED: missing $relative in $(Split-Path -Leaf $folder); review the source ZIP."
        }
    }
}
if (-not (Test-Path -LiteralPath (Join-Path $test 'vendor\autoload.php') -PathType Leaf)) {
    throw 'BLOCKED: test-copy vendor is missing; run reviewed Composer install without scripts/plugins first.'
}
foreach ($relative in @('composer.json', 'composer.lock', 'routes\admin.php', 'routes\api.php',
    'routes\command.php', 'routes\console.php', 'routes\storefront.php', 'routes\user.php', 'routes\web.php')) {
    $left = Join-Path $main $relative
    $right = Join-Path $test $relative
    if (-not (Test-Path -LiteralPath $left -PathType Leaf) -or
        -not (Test-Path -LiteralPath $right -PathType Leaf)) {
        throw "BLOCKED: required source or test route/lock missing: $relative. No files copied."
    }
    if ((Get-FileHash -LiteralPath $left -Algorithm SHA256).Hash -ne
        (Get-FileHash -LiteralPath $right -Algorithm SHA256).Hash) {
        throw "BLOCKED: source/test route or Composer file differs ($relative). No files copied."
    }
}

# Fail closed if main source was edited without a reviewed P11 source manifest.
Push-Location -LiteralPath $main
try {
    & node 'tools/qa/p11-source-manifest.mjs' '--verify'
    if ($LASTEXITCODE -ne 0) { throw 'BLOCKED: source fingerprint review is required.' }
    & node 'tools/qa/check-p13-homepage-gate.mjs'
    if ($LASTEXITCODE -ne 0) { throw 'BLOCKED: source P13 contracts failed.' }
} finally { Pop-Location }

# Exact allowlist. Important: no .env, database file, routes, vendor or uploads.
$files = @(
    'app\Providers\AppServiceProvider.php',
    'config\adminlte.php',
    'tests\Unit\P13HomepageMenuGateTest.php',
    'tools\qa\check-p13-homepage-gate.mjs',
    'tools\qa\p13-browser-readiness.php',
    'tools\qa\p13-isolated-admin-browser.ps1',
    'tools\qa\p13-sync-source-to-test.ps1',
    'tools\qa\p11-offline-gate.mjs',
    'tools\qa\p11-source-manifest.mjs',
    'reports\p11-source-manifest.json',
    'P13-HOMEPAGE-404-FIX-BN.md'
)
$pending = @()
foreach ($relative in $files) {
    $from = Join-Path $main $relative
    $to = Join-Path $test $relative
    if (-not (Test-Path -LiteralPath $from -PathType Leaf)) {
        throw "BLOCKED: required reviewed source file missing: $relative. No files copied."
    }
    $fromHash = (Get-FileHash -LiteralPath $from -Algorithm SHA256).Hash
    if (Test-Path -LiteralPath $to -PathType Leaf) {
        if ((Get-FileHash -LiteralPath $to -Algorithm SHA256).Hash -eq $fromHash) {
            Write-Host "SKIP already synced: $relative"
            continue
        }
    }
    $pending += $relative
}
if ($pending.Count -eq 0) {
    Write-Host 'PASS P13: all reviewed files already match in website-p12-test.' -ForegroundColor Green
    exit 0
}
$backup = Join-Path $test ('.p13-sync-backup-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [guid]::NewGuid().ToString('N').Substring(0,8))
New-Item -ItemType Directory -Path $backup -ErrorAction Stop | Out-Null
foreach ($relative in $pending) {
    $from = Join-Path $main $relative
    $to = Join-Path $test $relative
    if (Test-Path -LiteralPath $to -PathType Leaf) {
        $old = Join-Path $backup $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $old) -Force | Out-Null
        Copy-Item -LiteralPath $to -Destination $old -ErrorAction Stop
    }
    New-Item -ItemType Directory -Path (Split-Path -Parent $to) -Force | Out-Null
    Copy-Item -LiteralPath $from -Destination $to -Force -ErrorAction Stop
    if ((Get-FileHash -LiteralPath $from -Algorithm SHA256).Hash -ne
        (Get-FileHash -LiteralPath $to -Algorithm SHA256).Hash) {
        throw "BLOCKED: copied file hash mismatch: $relative. Review test-copy backup."
    }
    Write-Host "SYNCED P13 source/test: $relative"
}
Write-Host 'PASS P13: isolated test source synced. Original website was not changed.' -ForegroundColor Green
Write-Host 'Existing files were backed up inside a hidden .p13-sync-backup-* test-copy directory.'
Write-Host 'NEXT: cd D:\Ecommerce-Landing\website-p12-test ; node tools/qa/p11-offline-gate.mjs'
Write-Host 'NEXT: php artisan test tests/Unit/P13HomepageMenuGateTest.php'
Write-Host 'NO database, route, migration, feature flag, or COD change performed.'
