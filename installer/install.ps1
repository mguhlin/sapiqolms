# Sapiqo turnkey installer - Windows (PowerShell).
#
#   powershell -ExecutionPolicy Bypass -File installer\install.ps1
#   ...\install.ps1 -Email you@x.edu -Password 'Secret123' -First Admin -Last User
#
# Enables the PHP extensions Sapiqo needs (in php.ini), creates the data folder +
# config, builds the database, and creates the administrator. Run from the
# courses\ project. The installer\ folder can be deleted afterward.

param(
  [string]$Email = "admin@example.edu",
  [string]$Password = "",
  [string]$First = "Admin",
  [string]$Last = "User",
  [ValidateSet('sqlite','mysql')][string]$Db = "sqlite",
  [string]$MysqlHost = "127.0.0.1",
  [string]$MysqlDb = "sapiqo",
  [string]$MysqlUser = "sapiqo",
  [string]$MysqlPass = "",
  [int]$Port = 8000
)
$ErrorActionPreference = "Stop"
function Say($m){ Write-Host "`n==> $m" -ForegroundColor Cyan }
function Die($m){ Write-Host "`nERROR: $m" -ForegroundColor Red; exit 1 }

$Here = Split-Path -Parent $MyInvocation.MyCommand.Path   # courses\installer
$Root = Split-Path -Parent $Here                          # courses
$Code = Join-Path $Root "sapiqo"
$Data = if ($env:SAPIQO_DATA) { $env:SAPIQO_DATA } else { Join-Path $Root "sapiqo-data" }

$php = (Get-Command php -ErrorAction SilentlyContinue)
if (-not $php) { Die "PHP is not on PATH. Install PHP 8.1+ (https://windows.php.net/download) and add it to PATH." }

# --- Enable required extensions in the active php.ini ------------------------
Say "Checking PHP extensions..."
$iniPath = (& php -r "echo php_ini_loaded_file();").Trim()
if (-not $iniPath -or -not (Test-Path $iniPath)) {
  Write-Host "No php.ini is loaded. Copy php.ini-production to php.ini in your PHP folder, then re-run." -ForegroundColor Yellow
} else {
  $ini = Get-Content $iniPath -Raw
  $need = @("gd","zip","curl","openssl","mbstring","pdo_sqlite","pdo_mysql","fileinfo","sodium")
  $changed = $false
  # Ensure extension_dir is set (typical Windows layout).
  if ($ini -match '(?m)^\s*;\s*extension_dir\s*=\s*"ext"') {
    $ini = $ini -replace '(?m)^\s*;\s*(extension_dir\s*=\s*"ext")', '$1'; $changed = $true
  }
  foreach ($e in $need) {
    if ($ini -match "(?m)^\s*;\s*extension\s*=\s*$e\b") {
      $ini = $ini -replace "(?m)^\s*;\s*(extension\s*=\s*$e)\b", '$1'; $changed = $true
      Write-Host "  enabled extension=$e"
    }
  }
  if ($changed) {
    try { Set-Content -Path $iniPath -Value $ini -Encoding UTF8; Write-Host "  updated $iniPath" }
    catch { Write-Host "  Could not write $iniPath (run PowerShell as Administrator to edit it)." -ForegroundColor Yellow }
  } else { Write-Host "  extensions already enabled." }
}

Say "Running pre-flight check..."
& php (Join-Path $Here "preflight.php")
if ($LASTEXITCODE -ne 0) { Die "Required items are missing (see above). Fix them and re-run." }

if (-not $Password) {
  $sec = Read-Host "Administrator password (min 8)" -AsSecureString
  $Password = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
}
if ($Password.Length -lt 8) { Die "Password must be at least 8 characters." }

Say "Creating data folder: $Data"
New-Item -ItemType Directory -Force -Path (Join-Path $Data "data") | Out-Null
$conf = Join-Path $Data "config.local.php"
if (-not (Test-Path $conf)) {
  function ConvertTo-PhpLiteral([string]$Value) {
    "'" + $Value.Replace("\", "\\").Replace("'", "\'") + "'"
  }
  if ($Db -eq "mysql") {
    $PhpHost = ConvertTo-PhpLiteral $MysqlHost
    $PhpDb = ConvertTo-PhpLiteral $MysqlDb
    $PhpUser = ConvertTo-PhpLiteral $MysqlUser
    $PhpPass = ConvertTo-PhpLiteral $MysqlPass
    @"
<?php
return [
  'db_driver' => 'mysql',
  'mysql' => ['host' => $PhpHost, 'port' => 3306, 'dbname' => $PhpDb, 'user' => $PhpUser, 'pass' => $PhpPass],
];
"@ | Set-Content -Path $conf -Encoding UTF8
  } else {
    "<?php`nreturn [ 'db_driver' => 'sqlite' ];" | Set-Content -Path $conf -Encoding UTF8
  }
  Write-Host "  wrote $conf"
}

Say "Setting up the database and administrator..."
$env:SAPIQO_DATA = $Data
& php (Join-Path $Code "bin\setup.php") --email $Email --password $Password --first $First --last $Last
if ($LASTEXITCODE -ne 0) { Die "Setup failed (see above)." }

Say "Installation complete."
Write-Host @"

  Admin:   $Email
  Data:    $Data
  Docroot: $Code\public

Run it:
  - Quick test:  installer\serve.bat        then open http://localhost:$Port
  - Production:  configure IIS/Apache with document root $Code\public
  - Docker:      docker compose -f installer\docker-compose.yml up -d --build

You can delete the installer\ folder now (keep it if you use Docker).
"@
