# LTI 1.3 (launch Sapiqo from your LMS)

**Audience:** administrator / operator
**Where:** Admin → Settings & integrations → **LTI 1.3** (`/admin/lti`)

## What it is

Sapiqo is an **LTI 1.3 Advantage tool provider**. That lets a platform LMS —
Canvas, Moodle, Blackboard, Schoology, or any LTI 1.3 platform — launch a Sapiqo
course as an external tool. On launch, the learner is provisioned and signed in
automatically, and on completion Sapiqo can send a grade back to the platform via
AGS (Assignment & Grade Services).

The implementation (`app/lti.php`) does JWT (RS256) signing/verification and JWKS by
hand using the OpenSSL extension — no external JWT library is required. Sapiqo
generates and stores its own RSA keypair once, under `sapiqo-data/data/lti/`.

## How to use it

Registration is a two-way exchange: you give the platform Sapiqo's tool URLs, and
you register the platform's details in Sapiqo.

### 1. Give the platform administrator Sapiqo's tool details

At the top of `/admin/lti`, the **Your tool details** card shows (from
`lti_tool_urls()`):

- **OIDC login / initiation URL** — `{your-site}/lti/login`
- **Launch / redirect URL** — `{your-site}/lti/launch` (also the sole redirect URI)
- **Public JWKS URL** — `{your-site}/lti/jwks`
- **Key ID (kid)** — the current signing key id

A machine-readable version of the same is at `{your-site}/lti/config` (JSON).

Enter these when creating the LTI 1.3 developer key / tool in your platform:

- **Canvas** — create an LTI Developer Key; use the OIDC login URL as "OpenID
  Connect Initiation URL", the launch URL as the "Target Link URI" / Redirect URI,
  and the JWKS URL as the public JWKS method.
- **Moodle** — add an External tool with LTI 1.3; paste the initiation URL, redirect
  (launch) URL, and public keyset (JWKS) URL.
- **Blackboard** — register the tool in the developer portal with the same three
  URLs.

### 2. Register the platform in Sapiqo

In the **Register a platform** card, fill in what the platform gives you back:

| Field | Meaning |
|-------|---------|
| Name | Friendly label, e.g. "Our district Canvas". |
| Issuer (iss) | The platform's issuer, e.g. `https://canvas.instructure.com`. |
| Client ID | The client/developer-key ID the platform assigns. |
| Deployment ID | Optional; if set, it is enforced on launch. |
| Auth / OIDC authorize URL | The platform's authorization redirect endpoint. |
| Access-token URL | The platform's OAuth2 token endpoint (needed for grade passback). |
| Platform JWKS URL | Where Sapiqo fetches the platform's signing keys (preferred). |
| …or public key (PEM) | Paste an inline PEM instead of a JWKS URL. |

Provide **either** a JWKS URL (preferred, auto-rotating) **or** an inline PEM for
signature verification. Registered platforms are listed below the form and can be
removed.

### 3. Point a launch at a specific course

By default a launch lands the learner on their dashboard. To open a specific
course, add a **custom parameter** on the link/placement in your platform:

```
course=<slug>
```

(`sapiqo_course=<slug>` is also accepted.) The learner is then enrolled in and taken
to that course.

## Options & behavior

**User provisioning.** On launch, `lti_provision_user()` looks up the user by the
email in the id_token. If none exists, a new account is created (a learner, unless
the LTI roles claim contains "administrator", in which case an admin is created). If
the platform releases no email, a stable pseudo-address is synthesized from
issuer+subject so the same person maps to the same account each time.

**Grade passback (AGS).** If the launch includes an AGS endpoint claim with a
lineitem and the `score` scope, Sapiqo records it (`lti_capture_ags`). On course
completion Sapiqo can POST a score back (`lti_send_score`) — a 0–100 percent with
`activityProgress: Completed` / `gradingProgress: FullyGraded`. Passback needs the
platform's **Access-token URL** to be registered; Sapiqo authenticates the score
POST with a signed client-credentials assertion. Passback is best-effort: failures
are logged, not surfaced to the learner.

**Security of the launch.** The OIDC flow uses DB-backed `state` + `nonce` (not
session cookies, so cross-site `form_post` launches work). The id_token signature is
verified against the platform's key; audience, nonce, deployment ID, and message
type are all validated, with a 60-second clock-skew allowance. State rows are
single-use and expire after ~10 minutes.

## How it works

1. Platform initiates OIDC login → `GET/POST /lti/login` (`lti_oidc_login`): Sapiqo
   finds the platform by issuer (+client_id), stores state/nonce in `lti_state`, and
   redirects to the platform's authorize URL.
2. Platform authenticates and `form_post`s a signed `id_token` → `POST /lti/launch`
   (`lti_launch`): Sapiqo consumes the state, verifies the JWT via the platform's
   JWKS/PEM, validates claims, provisions + logs in the user, captures any AGS
   endpoint, and redirects to the course (or dashboard).
3. On completion, `lti_send_score()` obtains an access token from the platform
   (`lti_get_token`, signed client assertion) and POSTs the score to the lineitem's
   `/scores`.
4. The platform verifies Sapiqo's assertions against `{your-site}/lti/jwks`
   (`lti_jwks`), served from Sapiqo's stored keypair.

A launch also writes an `lti.launch` entry to the audit log.

## Tips & gotchas

- **Only administrators** can reach `/admin/lti`; the register/remove POSTs are
  CSRF-protected.
- **Keep `sapiqo-data/data/lti/` safe.** It holds Sapiqo's private signing key. It
  is inside the data root (outside the web root) and generated automatically on
  first use; back it up with the rest of `sapiqo-data/`.
- **No course opens?** Confirm the placement's custom parameter is `course=<slug>`
  with an exact, existing course slug — otherwise the learner lands on the dashboard.
- **Grade passback silent?** Ensure the Access-token URL is registered and the
  platform granted the AGS `score` scope; check the server error log for
  `lti_send_score` messages.
- **Verification fails?** Prefer the JWKS URL over a pasted PEM so key rotation
  doesn't break launches; confirm the Issuer and Client ID exactly match the
  platform's values.
- The tool exposes exactly one redirect URI — the launch URL. Register that, not the
  login URL, as the redirect/target URI.

## Related

- `41-single-sign-on.md` — the other way learners arrive pre-authenticated.
- `13-gradebook.md` — how completion/scores are tracked in Sapiqo.
- `45-security.md` — JWT/JWKS handling and CSRF stance for LTI endpoints.
