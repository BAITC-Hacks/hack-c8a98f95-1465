param(
    [ValidateRange(1024, 65535)][int]$Port = 8080,
    [switch]$Demo,
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$backend = Join-Path $root 'backend'
$frontend = Join-Path $root 'frontend'
$listener = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $Port)
try { $listener.Start() }
catch { throw "Port $Port is occupied. Use: .\start.ps1 -Port $($Port + 1)" }
finally { $listener.Stop() }

$php = $null
$candidates = @(
    (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source),
    (Join-Path $env:LOCALAPPDATA 'AlemEdu\tools\php\php.exe'),
    'C:\xampp\php\php.exe'
)
foreach ($candidate in $candidates) {
    if ($candidate -and (Test-Path -LiteralPath $candidate)) {
        & $candidate -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'
        if ($LASTEXITCODE -eq 0) { $php = $candidate; break }
    }
}
if (-not $php) { throw 'PHP 8.2+ is required. Alternatively: docker compose up --build' }
$npm = Get-Command npm.cmd -ErrorAction SilentlyContinue
if (-not $npm) { throw 'Node.js 22.12+ is required. Alternatively: docker compose up --build' }
$env:Path = "$(Split-Path -Parent $php);$env:Path"

Push-Location $backend
try {
    if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) {
        $composer = Get-Command composer -ErrorAction SilentlyContinue
        $portable = Join-Path $env:LOCALAPPDATA 'AlemEdu\tools\composer.phar'
        if ($composer) { & $composer.Source install --no-interaction --prefer-dist }
        elseif (Test-Path -LiteralPath $portable) { & $php $portable install --no-interaction --prefer-dist }
        else { throw 'Composer 2 is required for the first launch.' }
        if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }
    }
    & $php scripts/prepare.php
    if ($LASTEXITCODE -ne 0) { throw 'Environment setup failed.' }
    if ($Demo) {
        $run = Join-Path $root '.run'
        New-Item -ItemType Directory -Force -Path $run | Out-Null
        $database = Join-Path $run 'jury.sqlite'
        if (-not (Test-Path -LiteralPath $database)) { New-Item -ItemType File -Path $database | Out-Null }
        $env:DB_CONNECTION = 'sqlite'
        $env:DB_DATABASE = $database
        $env:DB_URL = 'null'
        $env:AI_PROVIDER = 'mock'
        $env:APP_CONFIG_CACHE = Join-Path $run 'jury-config.php'
        $env:DEMO_MODE = 'true'
    }
    $env:APP_DEBUG = 'false'
    & $php artisan migrate --seed --force
    if ($LASTEXITCODE -ne 0) { throw 'Database setup failed.' }
} finally { Pop-Location }

Push-Location $frontend
$oldApi = $env:VITE_API_URL
try {
    if (-not (Test-Path -LiteralPath 'node_modules/vite/bin/vite.js')) {
        & $npm.Source ci
        if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }
    }
    $env:VITE_API_URL = '/api'
    & $npm.Source run build:unified
    if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
} finally {
    $env:VITE_API_URL = $oldApi
    Pop-Location
}

$url = "http://127.0.0.1:$Port"
Write-Host "`nAlemEdu: $url`nOne server for the website and API. Keep this terminal open. Ctrl+C stops it.`n"
if (-not $NoBrowser) { Start-Process $url }
Push-Location $backend
try {
    & $php artisan serve --host=127.0.0.1 --port=$Port --no-reload
} finally { Pop-Location }
