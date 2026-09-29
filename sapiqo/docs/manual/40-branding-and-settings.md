# Branding & settings

**Audience:** administrator / operator
**Where:** Admin → Settings & integrations → **Branding & settings** (`/admin/settings`)

## What it is

The Branding & settings page white-labels Sapiqo for your organization. Everything
you set here is stored in the database `settings` table and overlaid onto the app's
configuration at runtime by `branding_overlay()` (in `app/settings.php`), so the
values reflow through the whole product with no file edits and survive code
upgrades. The keys that overlay config are listed in `BRANDING_KEYS` and include:
platform name, tagline, organization name, course-site name, brand mark, logo,
theme colors, certificate signatory/provider fields, and the default UI language.

Because these live in the database (not `config.local.php`), any administrator can
change them from the browser and see them apply immediately across the top bar,
splash page, login page, course reader, badges, and certificate PDFs.

## How to use it

Open **Admin → Branding & settings**. The page is a series of cards; edits in the
main form are saved with the **Save branding** button at the bottom. (Theme presets
apply on click, as their own one-click forms.)

1. **Identity** — Platform name (top bar, page titles, emails), Tagline,
   Organization name (certificates + footer), Course-site name (brand shown inside
   the course reader; blank defaults to "*Platform name* Courses"), and Brand mark
   (1–2 letters used when no logo is uploaded).
2. **Logo** — Upload a PNG, JPG, WEBP, or SVG. Transparent PNG/SVG is recommended
   because the logo renders on the dark navy nav bar. A "Remove logo" checkbox
   appears once one is set.
3. **Theme presets** — One-click skins (colors + font + corner radius). See below.
4. **Theme colors** — Set a custom Primary and Accent hex pair to override the
   preset colors.
5. **Language** — Default UI language for new/unauthenticated visitors.
6. **Course behavior** — Sequential-by-default toggle and access-expiry reminder
   milestones (documented in the enrollment/reminders page).
7. **Certificates** — Signatory name & title, CPE provider line, footer website.

Click **Save branding**. A `settings.update` entry is written to the audit log.

## Options & behavior

**Logo upload.** Accepted extensions are `png`, `jpg`, `jpeg`, `webp`, `svg`.
Raster uploads must pass `getimagesize()` (a real image); SVGs are accepted by
extension. The file is stored under `sapiqo-data/data/branding/` with a randomized
name (`logo-xxxxxx.ext`); the previous logo file is deleted when you replace or
remove it. The stored logo is served through the `/brand/logo` route and appears
in:

- the top navigation bar (`layout.php`),
- the marketing/splash page (`splash.php`),
- the login page (`login.php`),
- and, where configured, certificate output.

When no logo is set, the **brand mark** (letter/initials) is shown instead; if the
brand mark field is also blank it defaults to the first letter of the platform name.

**Theme presets & colors.** `theme_presets()` ships curated skins — Sapiqo
(default navy & gold), plus looks evoking WordPress, Joomla, Moodle, Canvas,
Blackboard, Schoology, and originals (Forest, Slate, Rose, Grape, High contrast).
Applying a preset sets `theme_primary`, `theme_accent`, `theme_font`, and
`theme_radius`. Colors are validated to `#rrggbb` (a bad value is discarded and
falls back to the CSS default). Two colors drive the entire palette: the primary
generates the navy scale and the accent generates the gold scale via `shade()`.
Font and corner radius come only from a preset — there are no direct inputs for
them on the form.

**Default language / i18n.** The Language selector lists `available_locales()` and
saves `default_locale`. Learners can still switch languages themselves. Add more
languages by dropping an `app/lang/<code>.php` file — it then appears in the list.

**Certificate signatory & CPE provider.** These print in the signatory block on
page 1 of the certificate PDF: `cert_signatory` (name), `cert_signatory_title`,
`cert_provider` (the CPE provider line, e.g. a state provider number that makes CPE
hours recognized), and `cert_website` (footer). Per-course CPE credit hours are set
separately on Manage courses, not here.

**Discussion-forum / expiry reminders.** The "Access-expiry reminders (days
before)" field stores `expiry_reminder_days` (default `30,7,1`). The input is
sanitized to a de-duplicated, descending list of positive integers; an empty value
falls back to `30,7,1`. Learners with a course access window get an in-app reminder
(and email, if mail is configured) at each milestone. The delivery sweep runs from
`bin/expire.php` on a daily cron. See the enrollment-expiry manual page for detail.

## How it works

Settings are a key/value store (`settings` table) read through `setting()` /
`all_settings()` with an in-process cache. Writing a value (`set_setting()`) does a
portable upsert and refreshes the cache. On every request, `lms_config()` builds
the file-based config, then calls `branding_overlay()` to replace any config key
that has a non-empty DB setting — so `lms_config()['app_name']`, `['logo']`, etc.
already reflect admin changes everywhere they are consumed. If the `settings` table
is missing (fresh install pre-migration), the overlay is a no-op and defaults apply.

Theme CSS is emitted by `brand_theme_style()`, which produces a `<style
id="brand-theme">` block overriding the color scale, font, and radius CSS
variables. It returns an empty string when nothing custom is set, so the stock CSS
defaults apply.

## Tips & gotchas

- **Colors must be `#rrggbb`.** A 3-digit shorthand or named color is rejected and
  silently cleared. Use the full six-hex form.
- **Font & radius are preset-only.** To change typography or corner style, pick the
  preset whose look you want; there is no standalone font/radius input.
- **Transparent logos look best.** The logo sits on the dark nav bar; a solid-white
  background box will show.
- **Replacing the logo deletes the old file** from `sapiqo-data/data/branding/`.
  Keep your own master copy of the source art.
- **Only administrators** can reach `/admin/settings` (`require_admin`), and the
  save POST is CSRF-protected.
- Branding survives upgrades because it lives in `sapiqo-data/`, not in the
  replaceable code — no need to re-apply after a software update.

## Related

- `41-single-sign-on.md` — provider buttons on the login page.
- `23-enrollment-expiry-and-reminders.md` — the expiry reminder milestones set here.
- `05-badges-certificates-transcript.md` — where the certificate signatory/CPE
  fields appear.
- `DEPLOYMENT.md` — data layout (`sapiqo-data/`) and upgrades.
