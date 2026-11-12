param([string]$PostgresBin = '', [int]$Port = 55432, [switch]$InitializeOnly)
$ErrorActionPreference = 'Stop'
$workspace = Split-Path $PSScriptRoot -Parent
$dataPath = [System.IO.Path]::GetFullPath((Join-Path $workspace '.local/postgres'))
if (!$dataPath.StartsWith($workspace + [System.IO.Path]::DirectorySeparatorChar)) { throw 'PostgreSQL data must stay inside the workspace.' }
if (!$PostgresBin) { $PostgresBin = Split-Path (Get-Command psql.exe -ErrorAction Stop).Source }
foreach ($binary in @('initdb.exe', 'postgres.exe', 'pg_ctl.exe')) {
    if (!(Test-Path (Join-Path $PostgresBin $binary))) { throw "Missing PostgreSQL binary: $binary" }
}
New-Item -ItemType Directory -Force $dataPath | Out-Null
$passwordPath = Join-Path $workspace '.local/postgres-password'
if (!(Test-Path (Join-Path $dataPath 'PG_VERSION'))) {
    if (!(Test-Path $passwordPath) -or (Get-Item $passwordPath).Length -eq 0) {
        $bytes = New-Object byte[] 32
        $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        $generator.GetBytes($bytes)
        $generator.Dispose()
        [System.IO.File]::WriteAllText($passwordPath, [Convert]::ToBase64String($bytes))
    }
    # Precreate the allowed data directory before initdb traverses parent paths.
    & (Join-Path $PostgresBin 'initdb.exe') -D $dataPath -U erepair --auth-host=scram-sha-256 --auth-local=scram-sha-256 --pwfile=$passwordPath --encoding=UTF8 --locale=C
    if ($LASTEXITCODE -ne 0) { throw 'The workspace PostgreSQL cluster did not initialize.' }
}
if ($InitializeOnly) { Write-Host 'Workspace PostgreSQL cluster initialized.'; return }
& (Join-Path $PostgresBin 'pg_ctl.exe') -D $dataPath status
if ($LASTEXITCODE -eq 0) { Write-Host 'The workspace PostgreSQL server is already running.'; return }
Write-Host "Starting the workspace database on 127.0.0.1:$Port. Keep this terminal open; Ctrl+C stops it."
# Direct foreground startup is supported when pg_ctl cannot create its Windows
# child token. It keeps the same user and normal sandbox permissions.
& (Join-Path $PostgresBin 'postgres.exe') -D $dataPath -h '127.0.0.1' -p $Port -c 'timezone=UTC'
if ($LASTEXITCODE -ne 0) { throw 'The workspace PostgreSQL server stopped with an error.' }
