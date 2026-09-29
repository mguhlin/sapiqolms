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
It never uses the installation's local database. Windows/PowerShell, MySQL,
Docker, and real SSO/LTI providers require additional environment-specific
verification.

Contributions to original project files are under CC BY-SA 4.0, with attribution
to Sapiqo LMS by Miguel Guhlin. Keep third-party license notices intact.
