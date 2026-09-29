# Security

The current publication is version **1.12.1**. See
[the code audit](docs/CODE_AUDIT.md) for changes, test coverage, and remaining
limitations. This project has not received an independent penetration test or
security certification.

For a suspected vulnerability, use the repository's **Security → Report a
vulnerability** feature. Include the affected version, installation type,
required privileges, reproduction steps, and expected impact. Do not put
credentials or learner data into public issues.

## Operating an installation

- Serve only `sapiqo/public/` through Apache or nginx, with HTTPS. Set `public_url`
  in `sapiqo-data/config.local.php` to the installation's canonical URL.
- Configure `trusted_proxies` with exact proxy addresses when TLS terminates at
  a reverse proxy. Forwarded headers from other clients are ignored.
- Choose your own administrator credentials. Docker requires `ADMIN_EMAIL` and
  `ADMIN_PASSWORD` and binds to localhost by default.
- Keep local configuration, databases, backups, private keys, and learner files
  out of public repositories and static hosting. Restrict their filesystem
  permissions to the operator and web-server account.
- Administrators, course authors, and API key holders are privileged. API v1
  keys have administrative scope. Import SCORM and native archives only from
  sources you trust; active course packages run in the LMS origin.
- Keep the server and PHP extensions updated, test restores, and back up
  `content/` separately. The built-in data backup does not contain course files.
- Verify SSO/LTI against your actual providers before enabling them. SSO does
  not automatically link existing accounts by email; LTI accounts are separate
  platform identities and never inherit site-admin privileges.

The PHP built-in server and `installer/serve.sh` are for local testing. GitHub
Pages publishes the introduction website and cannot execute the LMS backend.
