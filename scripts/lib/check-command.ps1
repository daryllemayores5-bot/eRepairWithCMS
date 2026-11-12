function Invoke-Check([string]$Executable, [string[]]$Arguments) {
    # Resolve first: a missing executable must not inherit a previous exit code.
    $commands = @(Get-Command -Name $Executable -CommandType Application -ErrorAction Stop)
    $command = $commands[0]
    $previousCommandPreference = $ErrorActionPreference
    try {
        # Windows PowerShell exposes native stderr as ErrorRecords, including
        # successful Composer diagnostics. The process exit status is authoritative.
        $ErrorActionPreference = 'Continue'
        $global:LASTEXITCODE = $null
        & $command.Source @Arguments
        $commandExitCode = $global:LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousCommandPreference
    }
    if ($null -eq $commandExitCode) {
        throw "Release verification stopped: $Executable could not be started."
    }
    if ($commandExitCode -ne 0) {
        throw "Release verification stopped: $Executable failed with exit code $commandExitCode."
    }
}

function Get-ReleaseLoopbackOrigin([string]$Value) {
    $origin = $null
    if (![Uri]::TryCreate($Value, [UriKind]::Absolute, [ref]$origin) -or
        $origin.Scheme -ne 'http' -or $origin.Host -notin @('127.0.0.1', 'localhost') -or
        $origin.UserInfo -or $origin.AbsolutePath -ne '/' -or $origin.Query -or $origin.Fragment) {
        throw 'Live acceptance requires a root HTTP origin on 127.0.0.1 or localhost, without credentials, query or fragment.'
    }
    return $origin.GetLeftPart([System.UriPartial]::Authority)
}
