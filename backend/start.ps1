param([int]$Port = 8000)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

$phpCandidates = @(
    (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source),
    (Join-Path $env:LOCALAPPDATA 'AlemEdu\tools\php\php.exe'),
    'C:\xampp\php\php.exe'
)
$alemeduPhp = $null
foreach ($candidate in $phpCandidates) {
    if ($candidate -and (Test-Path -LiteralPath $candidate)) {
        & $candidate -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'
        if ($LASTEXITCODE -eq 0) { $alemeduPhp = $candidate; break }
    }
}
if (-not $alemeduPhp) { throw 'PHP 8.2+ is required. See backend/README.md.' }
$env:Path = "$(Split-Path -Parent $alemeduPhp);$env:Path"

if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) {
    $alemeduComposer = Get-Command composer -ErrorAction SilentlyContinue
    $portableComposer = Join-Path $env:LOCALAPPDATA 'AlemEdu\tools\composer.phar'
    if ($alemeduComposer) { & $alemeduComposer.Source install --no-interaction }
    elseif (Test-Path -LiteralPath $portableComposer) { & $alemeduPhp $portableComposer install --no-interaction }
    else { throw 'Composer 2 is required. See backend/README.md.' }
    if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }
}
& $alemeduPhp scripts/prepare.php
if ($LASTEXITCODE -ne 0) { throw 'Environment setup failed.' }
& $alemeduPhp artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) { throw 'Database setup failed.' }
& $alemeduPhp artisan serve --host=127.0.0.1 --port=$Port --no-reload
