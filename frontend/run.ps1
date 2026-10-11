param(
    [int] $Port = 8080
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

if ($Port -lt 1 -or $Port -gt 65535) {
    throw 'Port must be between 1 and 65535.'
}

$php = Get-Command php -ErrorAction SilentlyContinue
if ($php) {
    $phpPath = $php.Source
} else {
    $xamppPhp = 'C:\xampp\php\php.exe'
    if (-not (Test-Path -LiteralPath $xamppPhp)) {
        throw 'PHP was not found on PATH or at C:\xampp\php\php.exe. Install PHP or XAMPP, then try again.'
    }
    $phpPath = $xamppPhp
}

$listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
if ($listener) {
    throw "Port $Port is already in use. Run .\frontend\run.ps1 -Port 8081 to use another port."
}

Write-Host "Signal is starting at http://127.0.0.1:$Port/" -ForegroundColor Green
Write-Host 'Leave this terminal open while using the app. Press Ctrl+C to stop it.' -ForegroundColor DarkGray
& $phpPath -S "127.0.0.1:$Port" -t $projectRoot
if ($LASTEXITCODE -ne 0) {
    throw "The PHP development server exited with code $LASTEXITCODE."
}
