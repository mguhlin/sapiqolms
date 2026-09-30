# Contributing

Open an issue describing the problem or proposed behavior before a substantial
change. Keep patches focused and include reproduction steps and validation.

The PHP application has no Composer or npm runtime dependencies. To check it,
install PHP 8.1+ with PDO SQLite, mbstring, GD, cURL, OpenSSL, and Phar, plus
Python 3.10+ and Node.js 20+ for the test runner and JavaScript syntax checks:

```bash
python3 tests/run.py
```

The suite creates temporary databases and course fixtures, starts a localhost
PHP server on an available port, and removes its temporary data when finished.
It never uses the installation's local database. Additional checks:

```bash
python3 tests/concurrency.py
python3 tests/recovery.py
python3 tests/upgrades.py
python3 tests/smtp.py
npm ci --ignore-scripts
npx playwright install chromium
python3 tests/browser.py
# Requires MariaDB server/client binaries; starts only a disposable /tmp server.
python3 tests/mysql.py
```

PHP 8.1/8.3/8.4, browser journeys, MariaDB and Docker/Apache are covered in CI.
Windows/IIS, nginx/offload and real provider integrations require further checks.
See the [compatibility matrix](docs/COMPATIBILITY.md).

Contributions to original project files are under CC BY-SA 4.0, with attribution
to Sapiqo LMS by Miguel Guhlin. Keep third-party license notices intact.
