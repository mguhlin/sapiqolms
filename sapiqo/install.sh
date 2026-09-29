#!/usr/bin/env bash
# Sapiqo one-shot installer (Linux / macOS).
# Run from inside the unpacked sapiqo/ folder:  bash install.sh
# Creates the persistent data folder (sibling sapiqo-data/), the database +
# tables, a default administrator, scans the drop-in courses/, and prints how to
# start the site. Safe to re-run (idempotent).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"          # sapiqo/ code folder (inside courses/)
COURSES="$(dirname "$ROOT")"                    # the courses/ project (course folders live here)
DATA="${SAPIQO_DATA:-$COURSES/sapiqo-data}" # persistent data (survives upgrades)

say() { printf '\n\033[1;34m==>\033[0m %s\n' "$1"; }
err() { printf '\n\033[1;31mERROR:\033[0m %s\n' "$1" >&2; exit 1; }

say "Sapiqo installer"

# --- 1. Requirements -------------------------------------------------------
command -v php >/dev/null || err "PHP is not installed. Install PHP 8.1+ (with pdo, gd, mbstring)."
PHPV=$(php -r 'echo PHP_VERSION;')
say "PHP $PHPV detected"
MISSING=""
for ext in pdo gd mbstring; do php -m | grep -qi "^$ext$" || MISSING="$MISSING $ext"; done
[ -n "$MISSING" ] && err "Missing required PHP extensions:$MISSING"

# --- 2. Prompts ------------------------------------------------------------
DB_DRIVER="${DB_DRIVER:-}"
if [ -z "$DB_DRIVER" ]; then
  read -r -p "Database — [1] SQLite (simple, no server)  [2] MySQL/MariaDB : " ans
  DB_DRIVER=$([ "$ans" = "2" ] && echo mysql || echo sqlite)
fi
if [ "$DB_DRIVER" = "mysql" ]; then
  php -m | grep -qi "^pdo_mysql$" || err "pdo_mysql extension is required for MySQL."
  read -r -p "MySQL host [127.0.0.1]: " MHOST; MHOST=${MHOST:-127.0.0.1}
  read -r -p "MySQL port [3306]: " MPORT; MPORT=${MPORT:-3306}
  read -r -p "MySQL database [sapiqo]: " MDB; MDB=${MDB:-sapiqo}
  read -r -p "MySQL user [sapiqo]: " MUSER; MUSER=${MUSER:-sapiqo}
  read -r -s -p "MySQL password: " MPASS; echo
else
  php -m | grep -qi "^pdo_sqlite$" || err "pdo_sqlite extension is required for SQLite."
fi

read -r -p "Administrator email: " ADMIN_EMAIL
[ -n "$ADMIN_EMAIL" ] || err "Admin email is required."
read -r -s -p "Administrator password (min 8 chars): " ADMIN_PASS; echo
[ "${#ADMIN_PASS}" -ge 8 ] || err "Password must be at least 8 characters."
read -r -p "Admin first name [Site]: " AFIRST; AFIRST=${AFIRST:-Site}
read -r -p "Admin last name [Admin]: " ALAST; ALAST=${ALAST:-Admin}

# --- 3. Persistent data folder + config -----------------------------------
say "Creating data folder: $DATA"
mkdir -p "$DATA/data/badges" "$DATA/data/avatars" "$DATA/badge-library"

CONFIG="$DATA/config.local.php"
if [ -f "$CONFIG" ]; then
  say "Keeping existing $CONFIG"
else
  say "Writing $CONFIG"
  if [ "$DB_DRIVER" = "mysql" ]; then
    SAPIQO_MHOST="$MHOST" SAPIQO_MPORT="$MPORT" SAPIQO_MDB="$MDB" SAPIQO_MUSER="$MUSER" SAPIQO_MPASS="$MPASS" php -r '
      $mysql = ["host" => getenv("SAPIQO_MHOST"), "port" => (int)getenv("SAPIQO_MPORT"),
        "dbname" => getenv("SAPIQO_MDB"), "user" => getenv("SAPIQO_MUSER"), "pass" => getenv("SAPIQO_MPASS")];
      echo "<?php\nreturn " . var_export(["db_driver" => "mysql", "mysql" => $mysql, "allow_self_registration" => true], true) . ";\n";
    ' > "$CONFIG"
  else
    cat > "$CONFIG" <<PHP
<?php
return [
  'db_driver' => 'sqlite',
  'allow_self_registration' => true,
];
PHP
  fi
fi

# Seed the shared badge library from the package if present and empty.
if [ -d "$ROOT/badge-library" ] && [ -z "$(ls -A "$DATA/badge-library" 2>/dev/null)" ]; then
  cp -n "$ROOT"/badge-library/*.png "$DATA/badge-library/" 2>/dev/null || true
fi

# --- 4. Schema + admin + course scan --------------------------------------
say "Initializing database, admin user, and scanning courses"
SAPIQO_DATA="$DATA" php "$ROOT/bin/setup.php" \
  --email "$ADMIN_EMAIL" --password "$ADMIN_PASS" --first "$AFIRST" --last "$ALAST"

# --- 5. Permissions --------------------------------------------------------
if command -v id >/dev/null && [ "$(id -u)" = "0" ]; then
  WEBUSER="${WEB_USER:-www-data}"
  if id "$WEBUSER" >/dev/null 2>&1; then
    say "Setting ownership of data folder to $WEBUSER"
    chown -R "$WEBUSER":"$WEBUSER" "$DATA"
  fi
fi
chmod -R u+rwX "$DATA"

# --- 6. Done ---------------------------------------------------------------
say "Install complete."
cat <<DONE

  Data folder : $DATA   (back this up; it holds the DB, badges, photos, config)
  Courses     : $COURSES   (drop a course folder here; it is picked up automatically)
  Admin login : $ADMIN_EMAIL

  Try it now (development server):
    php -S 0.0.0.0:8000 -t "$ROOT/public" "$ROOT/public/router.php"
    open http://localhost:8000/

  Production (Apache): point a vhost DocumentRoot at $ROOT/public
    (mod_rewrite on, AllowOverride All). See DEPLOYMENT.md.

DONE
