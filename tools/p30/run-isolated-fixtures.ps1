# P30: Windows PowerShell 5.1 compatible. Explicitly gated synthetic TEST-only INSERTs.
# .env and source manifest stay unchanged; no production or campaign data is read.
[CmdletBinding()]
param(
    [ValidateSet('Check', 'Apply', 'SelfTest')]
    [string]$Mode = 'Check'
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$website = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$project = Split-Path -Parent $website
$secretFile = Join-Path $project '.p01-runtime\secrets\p01-db-password'
$secret = $env:P02_TEST_DB_PASSWORD
if ([string]::IsNullOrWhiteSpace($secret) -and (Test-Path -LiteralPath $secretFile -PathType Leaf)) {
    $secret = [System.IO.File]::ReadAllText($secretFile).TrimEnd("`r", "`n")
}

Push-Location -LiteralPath $website
try {
    if ($Mode -eq 'SelfTest') {
        php tools/p30/install-isolated-fixtures.php --self-test
        if ($LASTEXITCODE -ne 0) { throw 'P30 pure safety self-test failed.' }
        return
    }
    node tools/qa/p11-source-manifest.mjs --verify
    if ($LASTEXITCODE -ne 0) { throw 'P11 source mismatch. Never bypass or rewrite its manifest.' }
    php tools/p30/install-isolated-fixtures.php --self-test
    if ($LASTEXITCODE -ne 0) { throw 'P30 pure safety test blocked installation.' }
    if ([string]::IsNullOrWhiteSpace($secret)) {
        throw 'Restricted p01_tester credential unavailable. Do not use main DB password.'
    }
    $oldSecret = [Environment]::GetEnvironmentVariable('P02_TEST_DB_PASSWORD', 'Process')
    $oldApproval = [Environment]::GetEnvironmentVariable('P30_FIXTURE_APPROVED', 'Process')
    $oldReadOnly = [Environment]::GetEnvironmentVariable('P11_READ_ONLY_APPROVED', 'Process')
    try {
        [Environment]::SetEnvironmentVariable('P02_TEST_DB_PASSWORD', $secret, 'Process')
        [Environment]::SetEnvironmentVariable('P30_FIXTURE_APPROVED', $null, 'Process')
        Remove-Variable secret -ErrorAction SilentlyContinue
        php tools/qa/p02-mysql-preflight.php
        if ($LASTEXITCODE -ne 0) { throw 'Restricted isolated test connection/grants refused.' }
        php tools/qa/p26-isolated-schema-preflight.php
        if ($LASTEXITCODE -ne 0) { throw 'Missing P26 test-only schema.' }
        php tools/p30/install-isolated-fixtures.php --check
        if ($LASTEXITCODE -ne 0) { throw 'P30 data check blocked; no fixture writes attempted.' }
        if ($Mode -eq 'Check') {
            Write-Host 'P30 READ-ONLY CHECK DONE. No database rows changed.'
            return
        }
        Write-Host 'WARNING: The next step INSERTS synthetic rows ONLY in p01_campaign_test.'
        Write-Host 'Backup the isolated test DB before proceeding. Existing business rows are refused.'
        $answer = Read-Host 'Type SEED_P01_SYNTHETIC_ONLY to approve this exact test-only fixture insert'
        if ($answer -cne 'SEED_P01_SYNTHETIC_ONLY') {
            throw 'Approval not given. No writes attempted.'
        }
        [Environment]::SetEnvironmentVariable('P30_FIXTURE_APPROVED', 'YES_P01_SYNTHETIC_FIXTURES_ONLY', 'Process')
        php tools/p30/install-isolated-fixtures.php --apply
        if ($LASTEXITCODE -ne 0) { throw 'P30 data installation stopped; see BLOCKED above.' }
        [Environment]::SetEnvironmentVariable('P30_FIXTURE_APPROVED', $null, 'Process')
        [Environment]::SetEnvironmentVariable('P11_READ_ONLY_APPROVED', 'YES_ISOLATED_ONLY', 'Process')
        try {
            php tools/qa/p11-readiness-review.php
            if ($LASTEXITCODE -ne 0) {
                throw 'P11 read-only data check is not green. Do not launch commerce QA.'
            }
        } finally {
            [Environment]::SetEnvironmentVariable('P11_READ_ONLY_APPROVED', $oldReadOnly, 'Process')
        }
        Write-Host 'PASS P30. Next: powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\qa\p26-isolated-sandbox.ps1'
    } finally {
        [Environment]::SetEnvironmentVariable('P02_TEST_DB_PASSWORD', $oldSecret, 'Process')
        [Environment]::SetEnvironmentVariable('P30_FIXTURE_APPROVED', $oldApproval, 'Process')
        [Environment]::SetEnvironmentVariable('P11_READ_ONLY_APPROVED', $oldReadOnly, 'Process')
        Remove-Variable answer, oldSecret, oldApproval, oldReadOnly -ErrorAction SilentlyContinue
    }
} finally {
    Pop-Location
}
