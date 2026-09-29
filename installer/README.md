# Sapiqo installer

Everything needed to stand up Sapiqo on a server. **This folder is only used at
install time — you can delete it afterward** (keep it if you run the Docker setup).

Sapiqo is plain PHP + PDO, no Composer. It runs on SQLite out of the box (zero
config) or MySQL/MariaDB for production.

---

## Fastest path — Docker (bundles every extension)

No PHP or extensions to install on the host; the image ships `gd`, `zip`,
`pdo_mysql`, `pdo_sqlite`, `dom`, `curl`, etc.

```bash
export ADMIN_EMAIL='you@example.org'
read -rsp 'Administrator password: ' ADMIN_PASSWORD; echo
export ADMIN_PASSWORD
docker compose -f installer/docker-compose.yml up -d --build
# open http://localhost:8080
```

Data persists in the `sapiqo-data` volume; courses persist in the host `content/`
folder (bind-mounted). Administrator credentials are required environment
variables; no default password is shipped. The port binds to localhost. Configure
an HTTPS reverse proxy before exposing the installation publicly.

---

## Linux / macOS (native)

```bash
# 1) (optional) install missing PHP extensions automatically, then install:
bash installer/install.sh --install-deps

# non-interactive example:
bash installer/install.sh --yes --email you@district.org --password 'Secret123' \
     --first Ada --last Lovelace
```

The installer runs a pre-flight check, offers to install missing extensions
(apt/dnf/yum/zypper/brew), writes `sapiqo-data/config.local.php`, builds the
database, and creates the administrator.

Run it:
```bash
bash installer/serve.sh            # quick test → http://localhost:8000
```
For production use Apache/nginx + (optionally) systemd — see `templates/`.

### SQLite vs MySQL/MariaDB

Run interactively (`bash installer/install.sh`) and it asks **SQLite or MySQL**.
SQLite is zero-config and fine for demos and modest usage. For MySQL it prompts
for host/database/user/password and can **create the database + app user for you**
given a MySQL admin (root) login — no manual `CREATE DATABASE` needed. Non-
interactive equivalent:
```bash
bash installer/install.sh --db mysql --create-db \
  --mysql-host 127.0.0.1 --mysql-db sapiqo --mysql-user sapiqo --mysql-pass 'AppSecret' \
  --mysql-admin root --mysql-admin-pass 'RootSecret' \
  --email you@district.org --password 'AdminPass' --yes
```
Omit `--create-db` if the database + user already exist. To move an *existing*
install to another server, see **“Moving to a new server”** in `DEPLOYMENT.md`.

## Windows

```powershell
powershell -ExecutionPolicy Bypass -File installer\install.ps1
installer\serve.bat                # quick test → http://localhost:8000
```

The script enables the needed extensions in your `php.ini` (run PowerShell **as
Administrator** if it can't write the file), then sets everything up.

---

## Check prerequisites any time

```bash
php installer/preflight.php
```
Reports PHP version, required + recommended extensions (with per-OS install
commands), and whether the data folder is writable. Exit code 0 = ready.

**Required:** PHP 8.1+, `pdo` + (`pdo_sqlite` or `pdo_mysql`), `mbstring`,
`openssl`, `json`, `phar`, `curl`, `gd`.
**Recommended:** `zip` (native ZipArchive — else PharData fallback), `dom`/`simplexml`
(robust Common Cartridge import — else regex fallback), `fileinfo`, `zlib`.
Missing recommended extensions only degrade optional features; the app still runs.

---

## Production files (`templates/`)

| File | Purpose |
|------|---------|
| `apache-vhost.conf` | Apache virtual host (docroot = `sapiqo/public`, `AllowOverride All`) |
| `nginx.conf` | nginx + PHP-FPM server block |
| `sapiqo.service` | systemd unit (built-in server behind a proxy) |
| `crontab.example` | nightly enrollment-expiry sweep + backup |

**Always** keep the document root at `sapiqo/public/`; the data folder
(`sapiqo-data/`) and course content (`content/`) stay outside the web root.
Terminate TLS at your web server or proxy for production (HTTPS is required for
real LTI 1.3 launches).

## Upgrading

Replace the `sapiqo/` folder with a new version. `sapiqo-data/` and `content/`
are untouched; schema migrations apply automatically on the next request.
