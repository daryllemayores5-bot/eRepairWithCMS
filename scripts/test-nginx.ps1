param()
$ErrorActionPreference = 'Stop'
$workspace = Split-Path $PSScriptRoot -Parent
$nginxExecutable = (Get-Command nginx.exe -ErrorAction Stop).Source
$phpExecutable = (Get-Command php-cgi.exe -ErrorAction Stop).Source
$testDirectory = Join-Path $workspace ('.local/nginx-check-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $testDirectory | Out-Null
$originalEnvironment = @{}
$temporaryEnvironment = @{
    PHP_INI_SCAN_DIR = (Join-Path $workspace '.local/php')
    APP_URL = ''
    FRONTEND_URL = ''
    SANCTUM_STATEFUL_DOMAINS = ''
    CORS_ALLOWED_ORIGINS = ''
}
function Get-FreePort {
    $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    try { return $listener.LocalEndpoint.Port } finally { $listener.Stop() }
}
$webPort = Get-FreePort
$phpPort = Get-FreePort
while ($phpPort -eq $webPort) { $phpPort = Get-FreePort }
$origin = "http://127.0.0.1:$webPort"
$temporaryEnvironment.APP_URL = $origin
$temporaryEnvironment.FRONTEND_URL = $origin
$temporaryEnvironment.SANCTUM_STATEFUL_DOMAINS = "127.0.0.1:$webPort"
$temporaryEnvironment.CORS_ALLOWED_ORIGINS = $origin
$phpProcess = $null
$nginxProcess = $null
try {
    foreach ($name in $temporaryEnvironment.Keys) {
        $originalEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        [Environment]::SetEnvironmentVariable($name, $temporaryEnvironment[$name], 'Process')
    }
    $configuration = [IO.File]::ReadAllText((Join-Path $workspace 'deployment/nginx.conf.example'))
    # Exercise the supplied application locations. Only TLS/host/runtime paths
    # differ from production; this does not certify HTTPS or Linux PHP-FPM.
    $configuration = $configuration.Substring(0, $configuration.LastIndexOf("server {"))
    $configuration = $configuration.Replace('listen 443 ssl;', "listen 127.0.0.1:$webPort;")
    $configuration = $configuration.Replace('erepair.example.com', 'localhost')
    $configuration = [regex]::Replace($configuration, '(?m)^\s*ssl_certificate(?:_key)? .*?;\r?\n', '')
    $configuration = $configuration.Replace('/srv/erepair', $workspace.Replace('\', '/'))
    $configuration = $configuration.Replace('unix:/run/php/php8.4-fpm.sock', "127.0.0.1:$phpPort")
    $nginxConfigurationDirectory = Join-Path (Split-Path $nginxExecutable -Parent) 'conf'
    $fastcgi = (Join-Path $nginxConfigurationDirectory 'fastcgi_params').Replace('\', '/')
    $mime = (Join-Path $nginxConfigurationDirectory 'mime.types').Replace('\', '/')
    $configuration = $configuration.Replace('include fastcgi_params;', "include `"$fastcgi`";")
    $configuration = "daemon off;`nmaster_process off;`nerror_log logs/error.log;`npid nginx.pid;`nevents { worker_connections 128; }`nhttp {`ninclude `"$mime`";`n$configuration`n}`n"
    New-Item -ItemType Directory -Path (Join-Path $testDirectory 'logs') | Out-Null
    $configurationPath = Join-Path $testDirectory 'nginx.conf'
    [IO.File]::WriteAllText($configurationPath, $configuration, (New-Object Text.UTF8Encoding($false)))
    $prefix = $testDirectory.Replace('\', '/') + '/'
    & $nginxExecutable -t -p $prefix -c $configurationPath
    if ($LASTEXITCODE -ne 0) { throw 'Temporary Nginx configuration check failed. Inspect the Nginx output for syntax, path or permission errors.' }
    $phpProcess = Start-Process -FilePath $phpExecutable -ArgumentList @('-b', "127.0.0.1:$phpPort") -WorkingDirectory (Join-Path $workspace 'backend') -WindowStyle Hidden -PassThru -RedirectStandardError (Join-Path $testDirectory 'php-error.log') -RedirectStandardOutput (Join-Path $testDirectory 'php-output.log')
    $nginxProcess = Start-Process -FilePath $nginxExecutable -ArgumentList @('-p', ('"' + $prefix + '"'), '-c', ('"' + $configurationPath + '"')) -WorkingDirectory $testDirectory -WindowStyle Hidden -PassThru -RedirectStandardError (Join-Path $testDirectory 'nginx-error.log') -RedirectStandardOutput (Join-Path $testDirectory 'nginx-output.log')
    $ready = $false
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        if ($phpProcess.HasExited -or $nginxProcess.HasExited) { throw "Temporary server exited. Inspect $testDirectory" }
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri "$origin/api/v1/health" -TimeoutSec 3
            if ($response.StatusCode -eq 200) { $ready = $true; break }
        } catch { Start-Sleep -Milliseconds 250 }
    }
    if (!$ready) { throw 'Temporary Nginx/PHP-CGI server did not become ready.' }
    & node (Join-Path $workspace 'scripts/frontend-http-smoke.mjs') $origin
    if ($LASTEXITCODE -ne 0) { throw 'Nginx frontend/API delivery check failed.' }
    $login = Invoke-WebRequest -UseBasicParsing -Uri "$origin/cp/auth/login" -TimeoutSec 10
    if ($login.Content -notmatch 'Statamic|Sign in|Log in') { throw 'Native Control Panel did not render through FastCGI.' }
    Write-Host '[PASS] Native Control Panel login is served through Nginx/FastCGI'
    foreach ($path in @('/.env', '/backend/.env', '/.local/postgres-password', '/storage/missing-file.png')) {
        try { Invoke-WebRequest -UseBasicParsing -Uri "$origin$path" -TimeoutSec 5 | Out-Null; throw "Protected/missing path was exposed: $path" }
        catch {
            if (!$_.Exception.Response -or [int]$_.Exception.Response.StatusCode -notin @(403, 404)) { throw }
        }
    }
    Write-Host '[PASS] Sensitive dot paths are denied and missing public files return 404'
    Write-Host 'Nginx/PHP-CGI deployment routing passed locally. HTTPS, target Linux PHP-FPM and browser installation still require acceptance.'
} finally {
    foreach ($process in @($nginxProcess, $phpProcess)) {
        if ($null -ne $process -and !$process.HasExited) { Stop-Process -Id $process.Id -ErrorAction SilentlyContinue }
    }
    foreach ($name in $originalEnvironment.Keys) { [Environment]::SetEnvironmentVariable($name, $originalEnvironment[$name], 'Process') }
}
