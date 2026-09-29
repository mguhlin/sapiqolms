# Organizations and subscriptions

**Audience:** administrator   (an organization manager also uses the Manage area — see Related)
**Where:** Admin → Organizations (/admin/orgs) and each org's page (/admin/orgs/{id})

## What it is
An **organization** is a top-level entity — a client or district (for example
*Aldirk ISD*) — that owns members, groups, and course subscriptions. Course
access flows *down* from the org:

- An **org-wide subscription** enrolls every member of the organization.
- Adding a member (directly, or by adding them to any of the org's groups)
  **auto-enrolls** them in whatever the org and those groups are subscribed to.
- **Un-subscribing is non-destructive:** existing learners keep the course and
  their progress; only future auto-enrollment stops.

Organizations are the right tool when a whole district should get the same
courses. For courses that only some people need, use a **group** inside the org
instead (see "Groups and managers").

## How to use it

### Create an organization
1. Go to /admin/orgs.
2. Under **Create an organization**, enter a **name** (e.g. `Aldirk ISD`) and an
   optional **description**, then choose **Create organization**.
3. You land on the org's page (/admin/orgs/{id}). Creating an org that already
   exists by name just reopens the existing one.

### Add members
On the org page, under **Members**:
1. Enter emails in **Add members by email** (separate with commas, spaces, or new
   lines).
2. Choose **Add to organization**.

Accounts must already exist; unknown emails are reported back as "Not found." To
create many new accounts, use the bulk **Imports** page first. Each added member
is auto-enrolled in the org-wide courses.

### Subscribe the whole org to courses
Under **Org-wide course subscriptions**:
1. Tick one or more courses under **Add org-wide course(s)** (the label notes it
   "enrolls all N member(s) now").
2. Choose **Subscribe & enroll**. Every current member is enrolled immediately,
   and anyone added later is enrolled automatically.

To subscribe only part of the org, create or attach a **group** and subscribe the
group instead.

### Un-subscribe (non-destructive)
In the subscriptions table, choose **Remove** next to a course. The confirmation
explains it: current learners keep the course and their progress; only new members
stop being auto-enrolled.

### Add groups to the org
Under **Groups in this organization** you can:
- **Create a group here** — makes a new group already linked to this org, or
- **Add an existing group** — pick a standalone group from the dropdown and choose
  **Add**. Its current members inherit org membership and org-wide course access.

### Assign organization managers
Under **Organization managers**:
1. Enter the manager's email in **Assign a manager by email**.
2. Tick the permissions: **Can enroll members**, **Can add/edit members**, and/or
   **Can manage course subscriptions**.
3. Choose **Assign manager**.

The manager runs the whole organization — all its groups — from their **Manage**
area, limited to the permissions you grant. They are added as a member of the org
too. Each manager row shows ✅/— for the three permissions and a **Remove** button.

## Options & behavior
- **Auto-enroll on join** — `org_add_member()` enrolls the new member in every
  org-wide course; `org_subscribe()` enrolls all current members. Enrollment is
  idempotent, so re-adding or re-subscribing never creates duplicates.
- **Groups inside an org inherit org-wide courses** — a member added to any group
  under the org also becomes an org member and gets the org-wide subscriptions.
- **Non-destructive un-subscribe** — `org_unsubscribe()` only deletes the
  subscription row; enrollments and progress stay.
- **Removing a member** drops them from the org and from the org's groups, but
  keeps their enrollments and progress ("keep-access").
- **Per-grant manager permissions** — `enroll` and `members` default ON;
  `courses` (managing subscriptions) defaults OFF and must be ticked explicitly.
- **Managers implicitly manage every group in the org** — an org manager can open
  and manage any group under that organization from the Manage area.
- **Export members CSV** — the **⬇ Export members CSV** button downloads name,
  email, enrollments, completions, and badges for all members.
- **Stats** at the top show members, groups, org-wide courses, enrollments,
  completion rate, and badges.
- **Rename** and **Delete** live in the org page's forms. **Deleting is
  non-destructive to people and content:** the org's groups become standalone,
  and every account, enrollment, and badge is kept — only the org grouping, its
  membership list, subscriptions, and manager grants are removed.

## How it works
Organizations are rows in `organizations`. Membership is `org_members`, group
links are the `org_id` column on `user_groups`, subscriptions are `org_courses`,
and manager grants are `org_managers` (`perm_enroll`, `perm_members`,
`perm_courses`). Adding a member or subscribing a course calls the shared
`enroll()` function, which respects each course's enrollment lifetime (see
"Enrollment expiry and reminders"). Because `enroll()` is idempotent, the
auto-enroll hooks are safe to run repeatedly. Deleting an org detaches its groups
(`org_id = NULL`) and removes only the org-scoped rows. Actions are audited
(`org.create`, `org.subscribe`, `org.manager.assign`, `org.delete`, and so on).

## Tips & gotchas
- **Un-subscribing does not remove learners.** If you truly need to pull access,
  disenroll people individually (or via bulk actions), and optionally purge — a
  subscription removal alone leaves everyone enrolled.
- **Add members before subscribing, or after — either order works.** Subscribe
  enrolls current members; adding a member enrolls them in current subscriptions.
- **Unknown emails aren't created here.** Use Imports to create accounts in bulk,
  then add them. (An org *manager* working in the Manage area *can* create
  accounts on the fly — see "The Manage area for sub-admins".)
- **Give the "courses" permission sparingly.** It lets a manager change what the
  whole org (and its members) is enrolled in.
- Prefer **groups** for course targeting within a large district so you are not
  enrolling everyone in everything.

## Related
- [Groups and managers](22-groups-and-managers.md)
- [Users and roles](20-users-and-roles.md)
- [Enrollment expiry and reminders](23-enrollment-expiry-and-reminders.md)
- [The Manage area for sub-admins](25-manage-area-for-sub-admins.md)
