param([int]$Port = 8000, [string]$FrontendOrigin = '')
$ErrorActionPreference = 'Stop'
$workspace = Split-Path $PSScriptRoot -Parent
if ($FrontendOrigin) {
    $origin = [Uri]$FrontendOrigin
    if (!$origin.IsAbsoluteUri -or $origin.Scheme -ne 'http' -or !$origin.IsLoopback -or $origin.UserInfo -or $origin.AbsolutePath -ne '/' -or $origin.Query -or $origin.Fragment) {
        throw 'Use a root HTTP loopback frontend origin for this local development override.'
    }
    # Process-local settings allow a second verified demo without changing .env.
    $env:APP_URL = "http://127.0.0.1:$Port"
    $env:FRONTEND_URL = $origin.GetLeftPart([System.UriPartial]::Authority)
    $env:CORS_ALLOWED_ORIGINS = $env:FRONTEND_URL
    $env:SANCTUM_STATEFUL_DOMAINS = $origin.Authority
    $env:APP_CONFIG_CACHE = Join-Path $workspace ".local/dev-config-$Port.php"
    if (Test-Path -LiteralPath $env:APP_CONFIG_CACHE) { throw 'Remove or review the existing isolated development config cache before overriding its origin.' }
}
if (Test-Path (Join-Path $workspace '.local/php/pgsql.ini')) { $env:PHP_INI_SCAN_DIR = Join-Path $workspace '.local/php' }
Set-Location (Join-Path $workspace 'backend')
php artisan serve --host=127.0.0.1 --port=$Port
