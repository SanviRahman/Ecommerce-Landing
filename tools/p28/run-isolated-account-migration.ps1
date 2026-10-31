# P28: explicitly approved single migration on the isolated p01_campaign_test database.
# Windows PowerShell 5.1 compatible; no main DB or .env changes.
param(
    [ValidateSet('Check', 'Apply')]
    [string]$Mode = 'Check'
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$website = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$secretFile = Join-Path (Split-Path -Parent $website) '.p01-runtime\secrets\p01-db-password'
$originalPassword = [Environment]::GetEnvironmentVariable('P02_TEST_DB_PASSWORD', 'Process')
$originalApproval = [Environment]::GetEnvironmentVariable('P28_ISOLATED_ACCOUNT_DDL_APPROVED', 'Process')
try {
    $restrictedSecret = $originalPassword
    if ([string]::IsNullOrWhiteSpace($restrictedSecret) -and (Test-Path -LiteralPath $secretFile -PathType Leaf)) {
        $restrictedSecret = [IO.File]::ReadAllText($secretFile).TrimEnd("`r", "`n")
    }
    if ([string]::IsNullOrWhiteSpace($restrictedSecret)) {
        throw 'Restricted p01_tester password missing. Do not use the main database credential.'
    }
    [Environment]::SetEnvironmentVariable('P02_TEST_DB_PASSWORD', $restrictedSecret, 'Process')
    Remove-Variable restrictedSecret -ErrorAction SilentlyContinue
    Push-Location $website
    try {
        node tools/qa/p11-source-manifest.mjs --verify
        if ($LASTEXITCODE -ne 0) { throw 'Source fingerprint mismatch; do not run any migration.' }
        php tools/qa/p02-mysql-preflight.php
        if ($LASTEXITCODE -ne 0) { throw 'Test database or restricted account failed identity/grants check.' }
        php tools/p28/isolate-account-migration.php --self-test
        if ($LASTEXITCODE -ne 0) { throw 'P28 internal safety test failed.' }
        php tools/p28/isolate-account-migration.php --check
        if ($LASTEXITCODE -ne 0) { throw 'Isolated schema is inconsistent; do not apply migration.' }
        if ($Mode -eq 'Apply') {
            Write-Host 'ONLY p01_campaign_test will be changed (one forward-only migration).' -ForegroundColor Yellow
            Write-Host 'No seeding, no reset, no rollback, no access to ecommerce_landing_db.' -ForegroundColor Yellow
            $confirmation = Read-Host 'Type MIGRATE_P01_TEST_ONLY to approve the isolated DDL'
            if ($confirmation -cne 'MIGRATE_P01_TEST_ONLY') {
                throw 'No exact approval; nothing changed.'
            }
            [Environment]::SetEnvironmentVariable('P28_ISOLATED_ACCOUNT_DDL_APPROVED', 'YES_P01_TEST_ACCOUNT_ONLY', 'Process')
            php tools/p28/isolate-account-migration.php --apply
            if ($LASTEXITCODE -ne 0) { throw 'Isolated migration failed; stop and review. No automatic recovery.' }
            php tools/qa/p26-isolated-schema-preflight.php
            if ($LASTEXITCODE -ne 0) { throw 'Migration succeeded, but full isolated schema preflight is not green.' }
        }
    } finally {
        Pop-Location
    }
} finally {
    [Environment]::SetEnvironmentVariable('P02_TEST_DB_PASSWORD', $originalPassword, 'Process')
    [Environment]::SetEnvironmentVariable('P28_ISOLATED_ACCOUNT_DDL_APPROVED', $originalApproval, 'Process')
    Remove-Variable originalPassword, originalApproval -ErrorAction SilentlyContinue
}
