#!/usr/bin/env bash
# Sapiqo turnkey installer — Linux / macOS.
#
#   bash installer/install.sh                 # interactive
#   bash installer/install.sh --install-deps  # also install missing PHP extensions (needs sudo)
#   bash installer/install.sh --email you@x.edu --password 'Secret123' --first Admin --last User --yes
#
# Flags: --email --password --first --last --db {sqlite|mysql}
#        --mysql-host --mysql-db --mysql-user --mysql-pass
#        --create-db --mysql-admin USER --mysql-admin-pass PASS   (auto-create DB+user)
#        --install-deps  --yes (non-interactive)  --port N (dev server)
#
# Interactive runs ask whether to use SQLite or MySQL and, for MySQL, can create
# the database + app user for you given a MySQL admin (root) login. Example:
#   bash installer/install.sh --db mysql --create-db --mysql-admin root \
#        --mysql-pass 'AppSecret' --email you@x.edu --password 'AdminPass' --yes
#
# The installer/ folder is only needed at install time — you can delete it after
# (keep it if you use the Docker setup).
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"        # courses/installer
ROOT="$(dirname "$HERE")"                     # courses/
CODE="$ROOT/sapiqo"
DATA="${SAPIQO_DATA:-$ROOT/sapiqo-data}"

EMAIL="" PASS="" FIRST="Admin" LAST="User" DB="" YES=0 INSTALL_DEPS=0 PORT="8000"
MHOST="127.0.0.1" MDB="sapiqo" MUSER="sapiqo" MPASS=""
CREATE_DB=0 ADMINU="" ADMINP=""
while [ $# -gt 0 ]; do case "$1" in
  --email) EMAIL="$2"; shift 2;; --password) PASS="$2"; shift 2;;
  --first) FIRST="$2"; shift 2;; --last) LAST="$2"; shift 2;;
  --db) DB="$2"; shift 2;; --mysql-host) MHOST="$2"; shift 2;; --mysql-db) MDB="$2"; shift 2;;
  --mysql-user) MUSER="$2"; shift 2;; --mysql-pass) MPASS="$2"; shift 2;;
  --install-deps) INSTALL_DEPS=1; shift;; --yes) YES=1; shift;; --port) PORT="$2"; shift 2;;
  --create-db) CREATE_DB=1; shift;; --mysql-admin) ADMINU="$2"; shift 2;; --mysql-admin-pass) ADMINP="$2"; shift 2;;
  *) echo "Unknown option: $1"; exit 1;; esac; done

say()  { printf '\n\033[1;34m==>\033[0m %s\n' "$1"; }
err()  { printf '\n\033[1;31mERROR:\033[0m %s\n' "$1" >&2; exit 1; }
ask()  { local p="$1" d="$2" v; if [ "$YES" = 1 ]; then echo "$d"; else read -r -p "$p [$d]: " v; echo "${v:-$d}"; fi; }

command -v php >/dev/null || err "PHP is not installed. Install PHP 8.1+ first (see installer/README.md)."

# --- Optional: install missing extensions -----------------------------------
detect_pm() {
  command -v apt-get >/dev/null && { echo apt; return; }
  command -v dnf >/dev/null && { echo dnf; return; }
  command -v yum >/dev/null && { echo yum; return; }
  command -v zypper >/dev/null && { echo zypper; return; }
  command -v brew >/dev/null && { echo brew; return; }
  echo ""
}
install_deps() {
  local pm; pm="$(detect_pm)"
  [ -z "$pm" ] && { echo "No supported package manager found; install php-zip/php-xml/php-gd/php-curl manually."; return; }
  say "Installing recommended PHP extensions via $pm (sudo may prompt)…"
  case "$pm" in
    apt)    sudo apt-get update -y && sudo apt-get install -y php-cli php-gd php-zip php-xml php-curl php-mbstring php-sqlite3 php-mysql ;;
    dnf)    sudo dnf install -y php-cli php-gd php-zip php-xml php-curl php-mbstring php-pdo php-mysqlnd ;;
    yum)    sudo yum install -y php-cli php-gd php-zip php-xml php-curl php-mbstring php-pdo php-mysqlnd ;;
    zypper) sudo zypper install -y php-cli php-gd php-zip php-dom php-curl php-mbstring php-sqlite php-mysql ;;
    brew)   brew install php || true ;;
  esac
}
[ "$INSTALL_DEPS" = 1 ] && install_deps

# --- Pre-flight -------------------------------------------------------------
say "Checking requirements…"
if ! php "$HERE/preflight.php"; then
  if [ "$INSTALL_DEPS" != 1 ]; then
    a="$(ask 'Required items are missing. Try to install PHP extensions now?' 'y')"
    [ "$a" = "y" ] && { install_deps; php "$HERE/preflight.php" || err "Still missing required items. See above."; } \
                    || err "Install the required items above, then re-run."
  else err "Required items still missing after install. See above."; fi
fi

# --- Admin account ----------------------------------------------------------
[ -z "$EMAIL" ] && EMAIL="$(ask 'Administrator email' 'admin@example.edu')"
if [ -z "$PASS" ]; then
  if [ "$YES" = 1 ]; then PASS="$(php -r 'echo bin2hex(random_bytes(5))."Aa1";')"; GEN=1
  else read -r -s -p "Administrator password (min 8): " PASS; echo; fi
fi
[ "${#PASS}" -ge 8 ] || err "Password must be at least 8 characters."

# --- Database choice --------------------------------------------------------
# Flag --db wins; otherwise ask (interactive) or default to sqlite (--yes).
if [ -z "$DB" ]; then
  if [ "$YES" = 1 ]; then DB="sqlite"
  else DB="$(ask 'Database — "sqlite" (zero-config) or "mysql" (MySQL/MariaDB)' 'sqlite')"; fi
fi
[ "$DB" = "mysql" ] || [ "$DB" = "sqlite" ] || err "Unknown --db '$DB' (use sqlite or mysql)."

if [ "$DB" = "mysql" ]; then
  # Prompt for connection details when interactive.
  if [ "$YES" != 1 ]; then
    MHOST="$(ask 'MySQL host' "$MHOST")"
    MDB="$(ask 'MySQL database name' "$MDB")"
    MUSER="$(ask 'MySQL app user' "$MUSER")"
    if [ -z "$MPASS" ]; then read -r -s -p "MySQL password for '$MUSER': " MPASS; echo; fi
    [ "$CREATE_DB" = 1 ] || { a="$(ask 'Create this database + user now? (needs a MySQL admin login)' 'n')"; [ "$a" = "y" ] && CREATE_DB=1; }
  fi

  # Optionally create the database + app user with an admin (root) login.
  if [ "$CREATE_DB" = 1 ]; then
    command -v mysql >/dev/null || err "The 'mysql' client is not installed; create the DB/user manually (SQL shown below) or install the client and retry."
    [ -n "$ADMINU" ] || ADMINU="$(ask 'MySQL admin (privileged) user' 'root')"
    if [ -z "$ADMINP" ] && [ "$YES" != 1 ]; then read -r -s -p "MySQL admin password: " ADMINP; echo; fi
    case "$MHOST" in 127.0.0.1|localhost|::1) SCOPES="localhost 127.0.0.1";; *) SCOPES="%";; esac
    [[ "$MDB" =~ ^[A-Za-z0-9_]+$ ]] || err "Database name may contain only letters, digits, and underscores."
    [[ "$MUSER" =~ ^[A-Za-z0-9_]+$ ]] || err "Database user may contain only letters, digits, and underscores."
    # Hex conversion keeps quotes/backslashes in passwords out of SQL source.
    MPASS_HEX="$(printf '%s' "$MPASS" | od -An -tx1 | tr -d ' \n')"
    SQL="SET @sapiqo_password = CONVERT(0x$MPASS_HEX USING utf8mb4); CREATE DATABASE IF NOT EXISTS \`$MDB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    for h in $SCOPES; do
      SQL="$SQL SET @sapiqo_sql = CONCAT('CREATE USER IF NOT EXISTS ', QUOTE('$MUSER'), '@', QUOTE('$h'), ' IDENTIFIED BY ', QUOTE(@sapiqo_password)); PREPARE sapiqo_stmt FROM @sapiqo_sql; EXECUTE sapiqo_stmt; DEALLOCATE PREPARE sapiqo_stmt;"
      SQL="$SQL GRANT ALL PRIVILEGES ON \`$MDB\`.* TO '$MUSER'@'$h';"
    done
    SQL="$SQL FLUSH PRIVILEGES;"
    say "Creating database '$MDB' and user '$MUSER' via admin '$ADMINU'…"
    if printf '%s\n' "$SQL" | MYSQL_PWD="$ADMINP" mysql -h "$MHOST" -u "$ADMINU"; then
      echo "  database + user ready"
    else
      err "Could not create the database/user. Check the administrator credentials and database privileges."
    fi
  else
    # Show the exact SQL so the operator can pre-create it if they haven't.
    cat <<SQLNOTE

  Note: the app user must already exist with rights on '$MDB'. If not, run this
  once as a MySQL admin (adjust the host/password):

    CREATE DATABASE IF NOT EXISTS \`$MDB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS '$MUSER'@'localhost' IDENTIFIED BY '<password>';
    GRANT ALL PRIVILEGES ON \`$MDB\`.* TO '$MUSER'@'localhost';
    FLUSH PRIVILEGES;

SQLNOTE
  fi
fi

# --- Data dir + config.local.php -------------------------------------------
say "Creating data folder: $DATA"
mkdir -p "$DATA/data"
CONF="$DATA/config.local.php"
if [ ! -f "$CONF" ]; then
  if [ "$DB" = "mysql" ]; then
    SAPIQO_MHOST="$MHOST" SAPIQO_MDB="$MDB" SAPIQO_MUSER="$MUSER" SAPIQO_MPASS="$MPASS" php -r '
      $mysql = ["host" => getenv("SAPIQO_MHOST"), "port" => 3306,
        "dbname" => getenv("SAPIQO_MDB"), "user" => getenv("SAPIQO_MUSER"), "pass" => getenv("SAPIQO_MPASS")];
      echo "<?php\nreturn " . var_export(["db_driver" => "mysql", "mysql" => $mysql], true) . ";\n";
    ' > "$CONF"
  else
    cat > "$CONF" <<PHP
<?php
return [ 'db_driver' => 'sqlite' ];
PHP
  fi
  chmod 600 "$CONF"
  echo "  wrote $CONF"
else
  echo "  keeping existing $CONF"
fi

# --- Schema + admin + course scan ------------------------------------------
say "Setting up the database and administrator…"
SAPIQO_DATA="$DATA" php "$CODE/bin/setup.php" --email "$EMAIL" --password "$PASS" --first "$FIRST" --last "$LAST"

# --- Permissions (best effort) ---------------------------------------------
chmod -R u+rwX,g+rwX "$DATA" 2>/dev/null || true

say "Installation complete."
GENERATED_NOTICE=""
if [ "${GEN:-0}" = 1 ]; then GENERATED_NOTICE="   (generated password: $PASS)"; fi
cat <<DONE

  Admin:   $EMAIL$GENERATED_NOTICE
  Data:    $DATA
  Docroot: $CODE/public

Run it
------
  • Quick test (built-in server):
        bash installer/serve.sh            # then open http://localhost:$PORT
  • Production (Apache/nginx/systemd): see installer/templates/ and installer/README.md
  • Docker (bundles all extensions):
        docker compose -f installer/docker-compose.yml up -d --build

You can now delete the installer/ folder (unless you use Docker).
DONE
