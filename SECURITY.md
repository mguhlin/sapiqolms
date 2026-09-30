# Security

The current publication is version **1.13.0**. See
[release verification](docs/RELEASE_1.13.md) and [the initial code audit](docs/CODE_AUDIT.md) for changes, test coverage, and remaining
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
  keys created before 1.13 retain legacy full access; rotate them into explicit
  scopes. Enable MFA for every administrator. Imported shells are replaced and
  active SCORM documents run in an opaque-origin sandbox; review actual package
  compatibility and do not treat imported content as independently audited.
- Keep the server and PHP extensions updated, test restores, and back up
  database, private configuration, keys and courses together. Format-2 backups
  include courses; older data-only backups do not. Protect recovery archives.
- Verify SSO/LTI against your actual providers before enabling them. SSO does
  not automatically link existing accounts by email; LTI accounts are separate
  platform identities and never inherit site-admin privileges.

The PHP built-in server and `installer/serve.sh` are for local testing. GitHub
Pages publishes the introduction website and cannot execute the LMS backend.
