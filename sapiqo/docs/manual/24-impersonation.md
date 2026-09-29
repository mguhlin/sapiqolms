# View as (impersonation)

**Audience:** administrator
**Where:** Admin → Manage users (/admin/users) → a user's row → **View as**; or the
user editor (/admin/users/{id}) → **👁️ View as this user**. Return via the amber
banner's **Return to admin** button.

## What it is
**View as** lets an administrator temporarily act as another user to troubleshoot —
to see exactly what that learner sees on their dashboard, in a course, or on their
transcript. While viewing as someone, Sapiqo treats you as that user: you see
their pages, and you cannot reach admin-only routes. A single click returns you to
your own administrator account.

Impersonation is deliberately limited: an administrator **cannot view as another
administrator**, and every start and stop is recorded in the audit log.

## How to use it
1. Open Admin → Manage users (/admin/users) and find the person, or open their
   editor at /admin/users/{id}.
2. Choose **View as** (in the list) or **👁️ View as this user** (in the editor).
   The button appears only for non-admin accounts and never on your own row.
3. You are switched into that user's session and taken to their **dashboard**. A
   flash message confirms who you are now viewing as.
4. An amber **"👁️ Viewing as … "** banner stays at the top of every page while you
   are impersonating.
5. When finished, choose **Return to admin** in that banner. You are returned to
   your administrator account and back to that user's editor page.

## Options & behavior
- **Who you can view as** — any non-administrator account. Attempting to view as an
  administrator is refused with "For safety, you cannot view as another
  administrator." You also cannot view as yourself.
- **One at a time** — you cannot start a second impersonation while already
  impersonating; you are told to "Return to admin first."
- **What you can do** — exactly what the target user can do. Because the session
  now reflects the target, `is_admin()` is false while impersonating, so admin-only
  pages are blocked. Use it to reproduce and diagnose the learner's experience, not
  to perform admin work.
- **Returning** — **Return to admin** restores your original admin session and
  drops you on the user's editor (/admin/users/{id}) so you can continue.
- **Fail-safe** — if your original admin account has been deleted or lost its admin
  role while you were impersonating, choosing **Return to admin** signs you out
  entirely rather than leaving you in a broken state.

## How it works
Starting impersonation (`begin_impersonation()`) stashes your real admin id in the
session and swaps the active user id to the target, regenerating the session id on
the privilege change. From then on `current_user()` and `is_admin()` reflect the
target, which is why admin routes are inaccessible and the learner's view is
faithful.

Returning (`end_impersonation()`) restores the stored admin id and again
regenerates the session id. The layout renders the amber banner whenever
`is_impersonating()` is true.

Both events are audited: `user.impersonate.start` records the target and their
email; `user.impersonate.stop` records the original admin and the target id. The
impersonation actions are CSRF-protected.

## Tips & gotchas
- **The banner is your signal.** If you see the amber "Viewing as …" bar, you are
  not acting as yourself — finish your check and choose **Return to admin**.
- **You can't do admin tasks while impersonating.** Return first, then make
  changes as yourself. This is intentional to avoid privilege confusion.
- **No admin-to-admin impersonation.** To troubleshoot another admin's issue, you
  cannot view as them; investigate via the audit log and their account settings
  instead.
- **Everything is logged.** Start/stop entries in the audit log (/admin/audit)
  give an accountable trail of who viewed as whom.
- Always **Return to admin** rather than just navigating away — the banner follows
  you, but returning cleanly restores your admin session.

## Related
- [Users and roles](20-users-and-roles.md)
- [The Manage area for sub-admins](25-manage-area-for-sub-admins.md)
