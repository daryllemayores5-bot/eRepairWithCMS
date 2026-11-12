param([switch]$DockerDatabase, [switch]$SeedDemo, [switch]$ReuseInstalledDependencies)
$ErrorActionPreference = 'Stop'
$workspace = Split-Path $PSScriptRoot -Parent
$previousTestOptIn = $env:RUN_POSTGRES_TESTS
$previousDemoPassword = $env:DEMO_PASSWORD
$previousPhpIniScanDir = $env:PHP_INI_SCAN_DIR
Set-Location $workspace
function Run-Step([string]$Executable, [string[]]$Arguments) {
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) { throw "$Executable failed with exit code $LASTEXITCODE. Setup is incomplete." }
}
function New-LocalSecret {
    $bytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $generator.GetBytes($bytes)
    $generator.Dispose()
    return [Convert]::ToBase64String($bytes)
}
function Write-EnvValue([string]$Path, [string]$Name, [string]$Value) {
    $content = [System.IO.File]::ReadAllText($Path)
    $line = $Name + '=' + $Value
    if ($content -match ('(?m)^' + [regex]::Escape($Name) + '=')) {
        $content = [regex]::Replace($content, ('(?m)^' + [regex]::Escape($Name) + '=[^\r\n]*'), [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $line })
    } else { $content += [Environment]::NewLine + $line + [Environment]::NewLine }
    [System.IO.File]::WriteAllText($Path, $content)
}
try {
    New-Item -ItemType Directory -Force .local | Out-Null
    Run-Step -Executable php -Arguments @('-v')
    & php -r 'exit(extension_loaded(''pdo_pgsql'') ? 0 : 1);'
    if ($LASTEXITCODE -ne 0) {
        New-Item -ItemType Directory -Force .local/php | Out-Null
        [System.IO.File]::WriteAllText((Join-Path $workspace '.local/php/pgsql.ini'), "extension=pdo_pgsql`nupload_max_filesize=8M`npost_max_size=32M`n")
        $env:PHP_INI_SCAN_DIR = Join-Path $workspace '.local/php'
        Run-Step -Executable php -Arguments @('-r', 'exit(extension_loaded(''pdo_pgsql'') ? 0 : 1);')
    }
    if (!(Test-Path backend/.env)) { Copy-Item backend/.env.example backend/.env }
    if (!(Test-Path frontend/.env)) { Copy-Item frontend/.env.example frontend/.env }
    if ($DockerDatabase) {
        if (!(Test-Path .local/compose.env)) {
            $secret = New-LocalSecret
            [System.IO.File]::WriteAllText((Join-Path $workspace '.local/compose.env'), "EREPAIR_DB_PASSWORD=$secret")
        }
        $databasePassword = ([System.IO.File]::ReadAllText((Join-Path $workspace '.local/compose.env')) -replace '^EREPAIR_DB_PASSWORD=', '').Trim()
        Run-Step -Executable docker -Arguments @('compose', '--env-file', '.local/compose.env', 'up', '-d', '--wait', 'db')
        Write-EnvValue (Join-Path $workspace 'backend/.env') 'DB_HOST' '127.0.0.1'
        Write-EnvValue (Join-Path $workspace 'backend/.env') 'DB_PORT' '55432'
        Write-EnvValue (Join-Path $workspace 'backend/.env') 'DB_USERNAME' 'erepair'
        Write-EnvValue (Join-Path $workspace 'backend/.env') 'DB_PASSWORD' $databasePassword
    }
    Set-Location (Join-Path $workspace 'backend')
    if ($ReuseInstalledDependencies) {
        if (!(Test-Path vendor/autoload.php) -or !(Test-Path ../frontend/node_modules) -or !(Test-Path ../frontend/package-lock.json)) {
            throw 'Reusable dependencies or the frontend lockfile are missing. Run normal setup with registry access first.'
        }
        Run-Step -Executable composer -Arguments @('validate', '--no-check-publish')
        Run-Step -Executable composer -Arguments @('check-platform-reqs')
    } else {
        Run-Step -Executable composer -Arguments @('install', '--no-interaction', '--prefer-dist')
    }
    if (!(Select-String -Path .env -Pattern '^APP_KEY=.+')) { Run-Step -Executable php -Arguments @('artisan', 'key:generate') }
    # The supplied Composer lockfile already includes the real Statamic package.
    Run-Step -Executable php -Arguments @('artisan', 'statamic:install')
    Run-Step -Executable php -Arguments @('artisan', 'erepair:prepare-cms-auth')
    Run-Step -Executable php -Arguments @('artisan', 'migrate', '--force')
    # Laravel serves only the public editorial disk; no Windows storage link is needed.
    New-Item -ItemType Directory -Force storage/app/public | Out-Null
    if ($SeedDemo) {
        if (!(Test-Path (Join-Path $workspace '.local/demo-password'))) { [System.IO.File]::WriteAllText((Join-Path $workspace '.local/demo-password'), (New-LocalSecret)) }
        $env:DEMO_PASSWORD = [System.IO.File]::ReadAllText((Join-Path $workspace '.local/demo-password'))
        Run-Step -Executable php -Arguments @('artisan', 'db:seed', '--class=DemoSeeder')
        $env:DEMO_PASSWORD = $previousDemoPassword
        Write-Host 'Demo accounts use the generated password in .local/demo-password. Never deploy this demo database.'
    }
    Set-Location $workspace
    Set-Location (Join-Path $workspace 'frontend')
    Run-Step -Executable npm.cmd -Arguments @('run', 'test:rules')
    if (!(Test-Path package-lock.json)) { throw 'The pinned frontend/package-lock.json is required for repeatable installation.' }
    if ($ReuseInstalledDependencies) {
        Run-Step -Executable npm.cmd -Arguments @('ls', '--depth=0', '--cache', '../.local/npm-cache')
    } else {
        Run-Step -Executable npm.cmd -Arguments @('ci', '--cache', '../.local/npm-cache')
    }
    Run-Step -Executable php -Arguments @('../backend/artisan', 'erepair:doctor', '--skip-build')
    Run-Step -Executable npm.cmd -Arguments @('run', 'build')
    Run-Step -Executable npm.cmd -Arguments @('test')
    Set-Location (Join-Path $workspace 'backend')
    Run-Step -Executable composer -Arguments @('validate', '--no-check-publish')
    if (Test-Path .env.testing) { $env:RUN_POSTGRES_TESTS = '1' } else { $env:RUN_POSTGRES_TESTS = $null }
    Run-Step -Executable php -Arguments @('artisan', 'test')
    if (!(Test-Path .env.testing)) { Write-Warning 'PostgreSQL integration tests were skipped. Configure the dedicated erepair_test connection before release verification.' }
    Write-Host 'Setup checks passed. Start scripts/dev.ps1 for Laravel and npm.cmd --prefix frontend run preview for the built web application. Use run dev instead of preview when editing source.'
} finally {
    $env:RUN_POSTGRES_TESTS = $previousTestOptIn
    $env:DEMO_PASSWORD = $previousDemoPassword
    $env:PHP_INI_SCAN_DIR = $previousPhpIniScanDir
    Set-Location $workspace
}
