# Sapiqo one-shot installer (Windows, PowerShell).
# Run from inside the unpacked sapiqo\ folder:
#   powershell -ExecutionPolicy Bypass -File install.ps1
# Creates the persistent data folder (sibling sapiqo-data\), the database +
# tables, a default administrator, scans the drop-in courses\, and prints how to
# start the site. Safe to re-run.

$ErrorActionPreference = "Stop"
$Root    = Split-Path -Parent $MyInvocation.MyCommand.Path       # sapiqo\ (inside courses\)
$Courses = Split-Path -Parent $Root                              # the courses\ project
$Data    = Join-Path $Courses "sapiqo-data"

function Say($m){ Write-Host "`n==> $m" -ForegroundColor Cyan }
function Die($m){ Write-Host "`nERROR: $m" -ForegroundColor Red; exit 1 }

Say "Sapiqo installer"

# --- 1. Requirements ---
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) { Die "PHP is not installed or not on PATH. Install PHP 8.1+ (pdo, gd, mbstring)." }
$phpv = (& php -r "echo PHP_VERSION;")
Say "PHP $phpv detected"
$mods = (& php -m)
foreach ($ext in @("pdo","gd","mbstring")) {
  if (-not ($mods -match "^$ext$")) { Die "Missing required PHP extension: $ext" }
}

# --- 2. Prompts ---
$ans = Read-Host "Database - [1] SQLite (simple)  [2] MySQL/MariaDB"
$driver = if ($ans -eq "2") { "mysql" } else { "sqlite" }
if ($driver -eq "mysql") {
  if (-not ($mods -match "^pdo_mysql$")) { Die "pdo_mysql extension is required for MySQL." }
  $mhost = Read-Host "MySQL host [127.0.0.1]"; if (-not $mhost) { $mhost = "127.0.0.1" }
  $mport = Read-Host "MySQL port [3306]"; if (-not $mport) { $mport = "3306" }
  $mdb   = Read-Host "MySQL database [sapiqo]"; if (-not $mdb) { $mdb = "sapiqo" }
  $muser = Read-Host "MySQL user [sapiqo]"; if (-not $muser) { $muser = "sapiqo" }
  $mpass = Read-Host "MySQL password" -AsSecureString
  $mpassPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($mpass))
} else {
  if (-not ($mods -match "^pdo_sqlite$")) { Die "pdo_sqlite extension is required for SQLite." }
}

$adminEmail = Read-Host "Administrator email"
if (-not $adminEmail) { Die "Admin email is required." }
$adminPassSec = Read-Host "Administrator password (min 8 chars)" -AsSecureString
$adminPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
  [Runtime.InteropServices.Marshal]::SecureStringToBSTR($adminPassSec))
if ($adminPass.Length -lt 8) { Die "Password must be at least 8 characters." }
$afirst = Read-Host "Admin first name [Site]"; if (-not $afirst) { $afirst = "Site" }
$alast  = Read-Host "Admin last name [Admin]"; if (-not $alast) { $alast = "Admin" }

# --- 3. Data folder + config ---
Say "Creating data folder: $Data"
New-Item -ItemType Directory -Force -Path (Join-Path $Data "data\badges") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Data "data\avatars") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Data "badge-library") | Out-Null

function ConvertTo-PhpLiteral([string]$Value) {
  "'" + $Value.Replace("\", "\\").Replace("'", "\'") + "'"
}
$phpHost = ConvertTo-PhpLiteral $mhost
$phpDb = ConvertTo-PhpLiteral $mdb
$phpUser = ConvertTo-PhpLiteral $muser
$phpPass = ConvertTo-PhpLiteral $mpassPlain
$phpPort = if ($mport) { [int]$mport } else { 3306 }

$config = Join-Path $Data "config.local.php"
if (Test-Path $config) {
  Say "Keeping existing $config"
} else {
  Say "Writing $config"
  if ($driver -eq "mysql") {
@"
<?php
return [
  'db_driver' => 'mysql',
  'mysql' => [
    'host' => $phpHost, 'port' => $phpPort,
    'dbname' => $phpDb, 'user' => $phpUser, 'pass' => $phpPass,
  ],
  'allow_self_registration' => true,
];
"@ | Set-Content -Encoding UTF8 $config
  } else {
@"
<?php
return [
  'db_driver' => 'sqlite',
  'allow_self_registration' => true,
];
"@ | Set-Content -Encoding UTF8 $config
  }
}

# Seed shared badge library if bundled and empty.
$libSrc = Join-Path $Root "badge-library"
$libDst = Join-Path $Data "badge-library"
if ((Test-Path $libSrc) -and -not (Get-ChildItem $libDst -ErrorAction SilentlyContinue)) {
  Copy-Item (Join-Path $libSrc "*.png") $libDst -ErrorAction SilentlyContinue
}

# --- 4. Schema + admin + scan ---
Say "Initializing database, admin user, and scanning courses"
$env:SAPIQO_DATA = $Data
& php (Join-Path $Root "bin\setup.php") --email $adminEmail --password $adminPass --first $afirst --last $alast

# --- 5. Done ---
Say "Install complete."
Write-Host @"

  Data folder : $Data   (back this up; DB, badges, photos, config)
  Courses     : $Courses   (drop a course folder here; auto-discovered)
  Admin login : $adminEmail

  Try it now (development server):
    `$env:SAPIQO_DATA = "$Data"
    php -S 0.0.0.0:8000 -t "$Root\public" "$Root\public\router.php"
    Start http://localhost:8000/

  Production (IIS/Apache): point the site root at $Root\public  (see DEPLOYMENT.md)

"@
