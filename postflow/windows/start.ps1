# PostFlow launcher for Windows. Started by "Lancer PostFlow.bat".
# First run: downloads PHP (free, ~30 MB) into this folder. Then starts the app
# on http://127.0.0.1:8000 and opens the browser. Close the window to stop.
$ErrorActionPreference = 'Stop'
$root   = Split-Path -Parent $PSScriptRoot
$app    = Join-Path $root 'app'
$phpDir = Join-Path $PSScriptRoot 'php'
$php    = Join-Path $phpDir 'php.exe'
$url    = 'http://127.0.0.1:8000'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$ProgressPreference = 'SilentlyContinue'

Write-Host ''
Write-Host '  PostFlow' -ForegroundColor Red
Write-Host '  --------'

if (-not (Test-Path $php)) {
    Write-Host '  Premier lancement : téléchargement de PHP (environ 30 Mo), patientez...'
    $zip = Join-Path $env:TEMP 'postflow-php.zip'
    $sources = @(
        'https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip',
        'https://windows.php.net/downloads/releases/latest/php-8.4-nts-Win32-vs17-x64-latest.zip'
    )
    try {
        $json = Invoke-RestMethod 'https://windows.php.net/downloads/releases/releases.json' -UseBasicParsing
        foreach ($v in '8.4', '8.3') {
            $entry = $json.$v
            if ($entry) {
                $build = $entry.PSObject.Properties | Where-Object { $_.Name -like 'nts-vs1*-x64' } | Select-Object -First 1
                if ($build) { $sources += 'https://windows.php.net/downloads/releases/' + $build.Value.zip.path }
            }
        }
    } catch { }
    $ok = $false
    foreach ($s in $sources) {
        try { Invoke-WebRequest $s -OutFile $zip -UseBasicParsing; $ok = $true; break } catch { }
    }
    if (-not $ok) { throw 'Impossible de télécharger PHP. Vérifiez votre connexion Internet et relancez.' }
    Expand-Archive -Path $zip -DestinationPath $phpDir -Force
    Remove-Item $zip -ErrorAction SilentlyContinue
    Write-Host '  PHP installé.'
}

# From here on, PHP's own messages must not stop the script.
$ErrorActionPreference = 'Continue'

# Rewritten on every start so the folder can be moved anywhere.
$ext = Join-Path $phpDir 'ext'
@(
    "extension_dir=`"$ext`"",
    'extension=curl', 'extension=fileinfo', 'extension=mbstring', 'extension=openssl',
    'extension=pdo_sqlite', 'extension=sqlite3',
    'memory_limit=512M', 'upload_max_filesize=20M', 'post_max_size=25M',
    'date.timezone=Africa/Casablanca'
) | Set-Content -Path (Join-Path $phpDir 'php.ini') -Encoding ASCII

try { & $php -v *> $null } catch { }
if ($LASTEXITCODE -ne 0) {
    Write-Host ''
    Write-Host '  Il manque un composant Windows (Visual C++). Il va se télécharger :' -ForegroundColor Yellow
    Write-Host '  installez-le, puis relancez PostFlow.'
    Start-Process 'https://aka.ms/vs/17/release/vc_redist.x64.exe'
    exit 1
}

Set-Location $app
if (-not (Test-Path (Join-Path $app 'public\storage'))) {
    try { & $php artisan storage:link *> $null } catch { }
}

Write-Host ''
Write-Host "  PostFlow est ouvert sur $url" -ForegroundColor Green
Write-Host '  Gardez cette fenêtre ouverte pendant que vous travaillez.'
Write-Host '  Fermez-la pour arrêter PostFlow.'
Write-Host ''

Start-Process powershell -WindowStyle Hidden -ArgumentList "-NoProfile -Command Start-Sleep -Seconds 3; Start-Process '$url'"
& $php artisan serve --host=127.0.0.1 --port=8000
