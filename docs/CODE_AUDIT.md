# Code audit — September 29, 2026

> Historical baseline for 1.12.1. Subsequent fixes and current limitations are
> recorded in [1.13 release verification](RELEASE_1.13.md).

**Publication:** Sapiqo LMS 1.12.1, © 2026 Miguel Guhlin.

## Scope and method

Reviewed the PHP front controller and route guards, authentication, reset and
SSO flows, LTI/JWT provisioning, course serving and progress, quiz grading,
course editors, import/export paths, data backups and restores, installer
scripts, Docker configuration, runtime-data exclusions, and documentation.
The application contains 102 PHP files; all pass syntax validation. This was
a source review plus targeted executable regression testing, not an independent
penetration test, exhaustive formal verification, or OWASP certification.

The reproducible suite is `python3 tests/run.py`. It uses isolated temporary
SQLite databases and content fixtures, never the owner's local installation.

## Findings corrected before publication

| Priority | Finding | Correction |
| --- | --- | --- |
| Critical | External LTI roles could create site administrators; asserted email could sign in as an existing local account. | Platform issuer/client/subject identify separate learner accounts. Remote roles grant no site administration. |
| Critical | Group managers could reset passwords for privileged users placed in their groups. | Scoped user editing excludes site admins, course developers, and group/org managers. |
| High | LTI validation accepted missing token lifetime claims and did not match issuer. | Require `iat`/`exp`, validate issuer, subject, LTI version, audience, authorized party, nonce, deployment, signature, and state lifetime. |
| High | Draft, paid, prerequisite-gated, or expired course content could be fetched directly after sign-in. | Central access checks protect course JSON, media, APIs, and learner forum access. Anonymous visitors receive only published syllabus previews. |
| High | Arbitrary progress IDs could count toward a badge; generic progress accepted quiz IDs. | Validate real course steps, enforce sequential locks, and require the grading endpoint for quiz completion. |
| High | Quiz grading trusted the learner's `asked` question subset. | The server chooses and retains the bank subset in the authenticated session; grading ignores the client's subset. |
| High | SSO automatically matched local accounts by asserted email. | Disable implicit email linking; require the existing provider and provider subject to match. Google email must be verified. OAuth state is bound to the provider and consumed once. |
| High | Missing session and submitted CSRF tokens compared equal. | Require a nonempty string token and an existing session token before constant-time comparison. |
| High | ZIP native import and CLI restore lacked complete archive checks; library normalization could conceal hostile paths or links. | Validate raw ZIP central-directory and TAR names, reject traversal, absolute paths, links, special files, encrypted ZIPs, excessive counts/sizes, and unsupported extended archive formats. |
| High | Common Cartridge manifest/media paths could read beyond the extracted package. | Resolve and contain resource/media files inside the package; sanitize imported lesson HTML. |
| High | Docker interpolated administrator credentials into shell source and shipped a known password. | Require operator-supplied credentials and pass them through the environment to PHP setup. Bind Docker to localhost by default. |
| High | Quiz attempt limits and reset-token consumption could race across requests. | Transactional quiz result locks and atomic reset-token claim/password update. |
| Medium | Registration could submit a paid-course identifier and self-enroll. | Check availability, prerequisites, and purchase access before optional registration enrollment. Public form roles cannot grant administration. |
| Medium | Runtime SQLite state and private configuration could accidentally be published or copied into a Docker build. | Ignore databases, local configuration, private keys, environment files, and schema markers; exclude the persistent data tree from Docker build context. |
| Medium | Password-reset links used the request Host; forwarded HTTPS was trusted unconditionally. | Add canonical `public_url`; derive fallback URLs from server configuration; trust proxy protocol headers only from configured peers. |
| Medium | Reset requests checked throttling but never recorded attempts. | Record reset request attempts so the existing limiter takes effect. |
| Medium | Markdown links/images allowed unsafe URL schemes; the Python renderer did not escape attribute quotes. | Apply scheme/control-character checks and proper escaping in both Markdown builders. Decode HTML entities before sanitizing editor attributes. |
| Medium | Remote fetch checks resolved DNS separately from connection; unknown-length responses could grow beyond the cap. | Pin cURL to checked public addresses, disable redirects/proxies, limit bytes during transfer, and fail closed without cURL. |
| Medium | SQLite backup copied a live database after a best-effort checkpoint; MySQL dump failure silently produced an incomplete backup. | Use an independent SQLite snapshot; fail explicitly if MySQL dump creation fails. Exclude transient exports/tmp/update backups. |
| Medium | Installers inserted database credentials directly into PHP source and Linux provisioning SQL. | Serialize PHP literals safely, validate SQL identifiers, encode provisioning password data, and avoid printing failed SQL containing credentials. |
| Medium | Settings queries used an unquoted MySQL reserved identifier. | Quote the settings `key` column consistently across both supported SQL drivers. |
| Low | OneRoster used array union when returning errors, discarding the error message. | Replace the existing report error field explicitly. |
| Low | Legacy static course routing could bypass access checks; development router could expose dotfiles. | Route all course paths through the front controller; limit direct development-server files to public assets and deny dotfile paths. |
| Low | Protected media advertised public caching; suffix HTTP ranges were incorrect. | Private/no-store course responses and correct suffix ranges, including HEAD handling. |
| Low | Copy failures were silently ignored; imported/update trees could contain links or local configuration. | Throw on failed copies or links and reject updater packages containing local config/runtime data. |
| Low | Transcription scripts assumed an owner's absolute filesystem path; installer output could print a supplied password. | Default to `whisper` on PATH; print a password only when the installer generated it. |

Version bumped from 1.12.0 to 1.12.1. Existing unsupported narratives about
historic deployments, exploit tests, and MariaDB validation were removed from
the application README; this report records the checks actually run here.

## Verification performed

- **58 PHP security regression checks:** hostile archives, access/expiry/sequence
  decisions, forged progress, quiz bank selection, answer-key stripping, URI and
  HTML sanitization, local/metadata SSRF, URL/proxy handling, LTI identity and JWT
  lifetime, reset token consumption, API key revocation, and backup integrity.
- **25 HTTP regression checks:** real sessions and login, missing-token CSRF,
  learner/admin boundaries, draft and paid JSON/media, registration enrollment
  bypass, answer-key removal, forged progress/quiz/SCORM, legitimate completion,
  private caching, suffix ranges, dotfiles, and anonymous syllabus access.
- **Backup/restore round trip:** restore an actual generated archive to a fresh
  data root; verify SQLite integrity and restored user records.
- **Browser smoke checks:** desktop (1440px) and mobile (375px) introduction
  pages, fresh-install login, admin/courses/accounts/settings/help screens, the
  visual editor, and the course reader. No broken website images, horizontal
  overflow, or JavaScript errors were observed.
- **Python Markdown URL regression checks.** PHP syntax across all 102 files,
  standalone JavaScript syntax, shell syntax, and Python compilation.

## Remaining limitations and follow-up work

- **Active packages are trusted content.** SCORM and native course archives can
  contain HTML/JavaScript that executes in the LMS origin. Admin-only imports
  must come from trusted sources. A separate origin and message-based SCORM
  bridge would provide stronger isolation.
- **Concurrency needs further validation.** Quiz limits and reset-token
  consumption now use database transactions, with row locking for MySQL quiz
  attempts. Repeated-attempt/token tests pass on SQLite; simultaneous workload
  tests on both database drivers remain follow-up work.
- **Identity migration changes behavior.** Existing local accounts are not
  automatically linked to SSO by email. Existing email-based LTI accounts are
  not silently reused; a verified migration/linking workflow is future work.
- **Provider interoperability was not live-tested.** No real Google/Microsoft/
  Clever/ClassLink credentials or LMS launch environment were supplied. JWT
  signature and identity logic were tested locally.
- **Environment coverage is limited.** SQLite and the Linux PHP server were
  exercised. MySQL/MariaDB, Docker image builds, Apache/nginx integration,
  PowerShell/IIS, SMTP delivery, and offload headers still need deployment tests.
- **Backups cover data, not courses.** Copy or export `content/` separately.
  Custom SQLite paths outside the standard data directory need operator-specific
  restore handling. Backup/updater filesystem writes are not atomic whole-tree
  swaps; verify update/rollback and permissions on the target server.
- **Archive compatibility is intentionally strict.** ZIP64, encrypted or
  multi-disk ZIPs, PAX/GNU extended TAR headers, links, and special files are
  rejected. Repackage compatible course archives if needed.
- **Privileges and sessions remain operational responsibilities.** API keys have
  full administrative scope; passwords have an eight-character minimum. There
  is no MFA or global password-change session-revocation feature.
- **No blanket security guarantee.** Manual HTML/XML parsers and the broad
  integration surface warrant continued fuzzing and review. The CSP blocks
  unnonced inline scripts but permits inline styles and HTTPS frames.

## Publication hygiene and attribution

Text and hidden-file scans found no explicit generator credits, prompts,
or agent instruction files inside the supplied folder. PNG metadata inspection
found timestamp text chunks, which were removed from shipped UI images without
changing pixel data. Local installation state is excluded from publication.
This scan cannot prove the original authorship or tooling of every asset.

Actual third-party component names and copyright notices are retained, including
optional Whisper documentation and the bundled Inter, Poppins, and DejaVu font
licenses. Original project material is CC BY-SA 4.0, © 2026 Miguel Guhlin.
