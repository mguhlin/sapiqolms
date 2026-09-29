# Sapiqo brand assets (design source)

Official source files provided by the owner:

- `sapiqo-logo-mark.png` (1333×1180, transparent) — the Sapiqo mark: open book with
  sprouting leaves, teal + gold. No wordmark.
- `sapiqo-hero-master.png` (1672×941) — the official splash hero ("Sapiqo LMS",
  "Learning made clear.", educator scene). Used directly as the splash hero.

Everything in `sapiqo/public/assets/img/` derives from these via PHP GD:
- `hero.webp` = the hero master, verbatim.
- `auth-side.webp`, `onboarding.webp`, `feature-learn/practice/earn.webp` = crops of the hero.
- `favicon*.png`, `apple-touch-icon.png`, `app-icon.png`, `brand-mark.png` = the transparent mark.
- `brand-logo.png`, `email-header.png` = the mark + "Sapiqo LMS" lockup on white.

The shipped application logo is `sapiqo/public/assets/img/brand-mark.png`.
Operators can upload their own logo through Admin → Settings; uploaded branding
belongs in the persistent data directory and is excluded from this repository.
Theme: primary `#17395C` (navy), accent `#E0A63A` (gold).

Design source only — not web-served, not required at runtime.
