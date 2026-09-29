# Single sign-on (SSO)

**Audience:** administrator / operator
**Where:** `sapiqo-data/config.local.php` (the `'sso'` block). Login buttons appear
automatically at the login page (`/login`).

## What it is

Sapiqo supports optional single sign-on so learners can sign in with an existing
account instead of a local email/password. Five providers are built in:

- **Google** (OAuth2 / OIDC)
- **Microsoft** (Azure AD / Entra ID; per-tenant)
- **Clever** (K-12 SSO/rostering)
- **ClassLink** LaunchPad (K-12 SSO)
- **Rhythm** (K-12 SSO; per-tenant endpoints)

SSO is entirely config-gated. A provider only appears on the login page once it is
enabled **and** has a real client ID (`sso_providers()` in `app/auth.php` skips any
provider that is disabled or missing credentials). No credentials are ever stored
in the database — they live only in the file-based local config outside the web
root.

## How to use it

1. **Register a redirect URI with the provider.** For every provider the callback
   URL follows one pattern:

   ```
   {your-site}/auth/<provider>/callback
   ```

   e.g. `https://lms.example.org/auth/google/callback`,
   `.../auth/microsoft/callback`, `.../auth/clever/callback`,
   `.../auth/classlink/callback`, `.../auth/rhythm/callback`.
   (This is exactly what `sso_redirect_uri()` builds from your site's base URL.)

2. **Edit `sapiqo-data/config.local.php`** and fill in the provider's `client_id`
   and `client_secret`, then set `enabled => true`. Start from
   `app/config.local.example.php`, which contains the full annotated `'sso'` block.

3. **Reload the login page.** A "Continue with *Provider*" button appears for each
   enabled, credentialed provider.

There is no admin UI screen for SSO — it is a config-file feature by design (the
secrets stay out of the database and off the web root).

## Options & behavior

Each provider needs different fields:

| Provider   | Required fields | Notes |
|-----------|-----------------|-------|
| Google    | `client_id`, `client_secret` | Standard OIDC endpoints are built in. |
| Microsoft | `client_id`, `client_secret`, `tenant` | `tenant` defaults to `common`; set your directory (GUID or domain) to restrict to your org. |
| Clever    | `client_id`, `client_secret` | Uses HTTP Basic token exchange; user resolved via `/me` → `/users/{id}`. |
| ClassLink | `client_id`, `client_secret` | Userinfo fields are mapped (Email/FirstName/LastName/UserId). |
| Rhythm    | `client_id`, `client_secret`, **`auth_url`**, **`token_url`**, **`userinfo_url`** | Endpoints vary per district tenant, so you must set them explicitly. Optional `scope` and `map`. |

**Rhythm is the exception.** Google, Microsoft, Clever, and ClassLink have their
OAuth endpoints hard-coded. Rhythm does not — because the endpoints differ by
district tenant, Rhythm will not even appear unless `client_id`, `auth_url`, and
`token_url` are all set. Fill in, for example:

```php
'rhythm' => [
    'enabled'      => true,
    'client_id'     => '...',
    'client_secret' => '...',
    'auth_url'      => 'https://<tenant>.rhithm.app/oauth/authorize',
    'token_url'     => 'https://<tenant>.rhithm.app/oauth/token',
    'userinfo_url'  => 'https://<tenant>.rhithm.app/oauth/userinfo',
    'scope'         => 'openid email profile',
    // Optional if the tenant uses non-standard field names:
    // 'map' => ['email' => 'email', 'first' => 'first_name', 'last' => 'last_name', 'sub' => 'id'],
],
```

**Microsoft tenant.** Leave `tenant => 'common'` to allow any Microsoft account, or
set your tenant ID/domain to restrict to your organization. The authorize/token
URLs are built with the tenant value.

**Account creation & linking.** On a successful callback, Sapiqo looks up the user
by the email returned by the provider. If it matches an existing account, that
account is used (SSO effectively links to it). If no account exists, a new
**learner** account is created automatically with `auth_provider` set to the
provider name. If the provider returned a profile picture and the user has no
avatar yet, it is saved. New SSO users are always created as learners — elevate
roles afterward in Admin → Users.

**Scopes.** Sensible defaults are used per provider (`openid email profile` for
Google/Microsoft/Rhythm; `read:user_id read:sis` for Clever; `profile` for
ClassLink). Rhythm's scope is overridable in config.

## How it works

`sso_providers()` returns only the enabled, credentialed providers as an array of
endpoint descriptors. The login view iterates it to render one
`/auth/<provider>` button each. Clicking a button hits `sso_begin()`, which stores
a random `state` in the session and redirects to the provider's authorize URL with
`response_type=code` and your redirect URI.

The provider redirects back to `/auth/<provider>/callback`, handled by
`sso_complete()`: it verifies the `state` (CSRF protection with `hash_equals`),
exchanges the authorization code for an access token, fetches userinfo, normalizes
it to `email/first/last/sub/picture`, then finds-or-creates the local user and logs
them in. Google/Microsoft/ClassLink/Rhythm use the generic OAuth2 flow
(`sso_identity_oauth`); Clever uses a Basic-auth token exchange plus a two-step user
lookup (`sso_identity_clever`).

The redirect URI is always derived from the current site
(`base_url_absolute() . '/auth/<provider>/callback'`), so it must match what you
registered exactly — including scheme (https) and host.

## Tips & gotchas

- **The button won't show** if `enabled` is false, `client_id` is blank, or (for
  Rhythm) `auth_url`/`token_url` are missing. Double-check all three for Rhythm.
- **Redirect URI must match exactly.** Most SSO failures are a mismatch between the
  URI you registered and `{your-site}/auth/<provider>/callback`. Behind a proxy,
  make sure Sapiqo sees HTTPS (see `trusted_proxies` / `X-Forwarded-Proto`) so the
  callback is built as `https://`.
- **Email is required.** If a provider releases no email, account creation fails
  and login is rejected. Configure the provider to release email/profile.
- **SSO users start as learners.** There is no role mapping from the IdP; promote
  admins/developers manually.
- **Secrets stay in the file.** Keep `sapiqo-data/config.local.php` outside the web
  root and readable only by the web user — it holds your client secrets.
- Local email/password login continues to work alongside SSO; SSO is additive.

## Related

- `01-accounts-and-signing-in.md` — the learner-facing login experience.
- `20-users-and-roles.md` — promoting SSO-created learners to other roles.
- `45-security.md` — session hardening, secrets outside the web root.
- `DEPLOYMENT.md` §8 — SSO config walkthrough.
