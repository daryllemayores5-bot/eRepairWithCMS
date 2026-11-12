param([switch]$LiveApi, [string]$FrontendOrigin = 'http://127.0.0.1:5173', [string]$BuiltPreviewOrigin = '', [string]$BackendOrigin = 'http://127.0.0.1:8000')
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib/check-command.ps1')
$workspace = Split-Path $PSScriptRoot -Parent
$previousTestOptIn = $env:RUN_POSTGRES_TESTS
$previousIniScanDir = $env:PHP_INI_SCAN_DIR
$previousLiveOrigin = $env:EREPAIR_LIVE_ORIGIN
if ($LiveApi) {
    $FrontendOrigin = Get-ReleaseLoopbackOrigin $FrontendOrigin
    $BuiltPreviewOrigin = Get-ReleaseLoopbackOrigin $(if ($BuiltPreviewOrigin) { $BuiltPreviewOrigin } else { $FrontendOrigin })
    $BackendOrigin = Get-ReleaseLoopbackOrigin $BackendOrigin
}
try {
    if ($LiveApi) { $env:EREPAIR_LIVE_ORIGIN = $FrontendOrigin }
    if (Test-Path (Join-Path $workspace '.local/php/pgsql.ini')) { $env:PHP_INI_SCAN_DIR = Join-Path $workspace '.local/php' }
    Set-Location (Join-Path $workspace 'backend')
    Invoke-Check -Executable composer -Arguments @('validate', '--no-check-publish')
    Invoke-Check -Executable composer -Arguments @('check-platform-reqs')
    # Check the declared Node range before running any JavaScript release tools.
    Invoke-Check -Executable php -Arguments @('artisan', 'erepair:doctor', '--skip-build')
    Invoke-Check -Executable node -Arguments @('--test', (Join-Path $workspace 'tests/check-command.test.mjs'))
    Invoke-Check -Executable node -Arguments @('--experimental-strip-types', '--test', (Join-Path $workspace 'tests/repair-request.test.mts'), (Join-Path $workspace 'tests/worker-retirement.test.mjs'))
    Invoke-Check -Executable node -Arguments @('--test', (Join-Path $workspace 'tests/release-artifact.test.mjs'))
    Invoke-Check -Executable node -Arguments @('--test', (Join-Path $workspace 'tests/verify-host.test.mjs'))
    Invoke-Check -Executable node -Arguments @('--test', (Join-Path $workspace 'tests/vercel-config.test.mjs'))
    Invoke-Check -Executable node -Arguments @('--test', (Join-Path $workspace 'tests/frontend-build.test.mjs'))
    Invoke-Check -Executable node -Arguments @('--test', (Join-Path $workspace 'tests/cms-style-rendering.test.mjs'))
    if (!(Test-Path .env.testing)) { throw 'Configure the dedicated erepair_test connection in backend/.env.testing before release verification.' }
    $env:RUN_POSTGRES_TESTS = '1'
    Invoke-Check -Executable php -Arguments @('artisan', 'test')
    Set-Location (Join-Path $workspace 'frontend')
    if (!(Test-Path package-lock.json)) { throw 'Resolve and commit the frontend lockfile before release verification.' }
    Invoke-Check -Executable npm.cmd -Arguments @('run', 'typecheck')
    Invoke-Check -Executable npm.cmd -Arguments @('test')
    Invoke-Check -Executable npm.cmd -Arguments @('run', 'build:production')
    if ($LiveApi) {
        Invoke-Check -Executable node -Arguments @((Join-Path $workspace 'scripts/frontend-http-smoke.mjs'), $BuiltPreviewOrigin)
        Invoke-Check -Executable npm.cmd -Arguments @('run', 'test:live-api')
        Invoke-Check -Executable php -Arguments @((Join-Path $workspace 'scripts/cms-http-smoke.php'), $BackendOrigin)
    }
    Write-Host 'Automated local release checks passed. Public host, browser, mail, audit and production recovery acceptance remain separate.'
} finally {
    $env:RUN_POSTGRES_TESTS = $previousTestOptIn
    $env:PHP_INI_SCAN_DIR = $previousIniScanDir
    $env:EREPAIR_LIVE_ORIGIN = $previousLiveOrigin
    Set-Location $workspace
}
