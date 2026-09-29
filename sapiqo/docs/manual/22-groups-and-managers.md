# Groups and managers

**Audience:** administrator   (a group manager also uses the Manage area — see Related)
**Where:** Admin → Groups (/admin/groups) and each group's page (/admin/groups/{id})

## What it is
A **group** organizes learners — typically by campus, cohort, or district — so you
can track completion together and subscribe them to courses as a set. A group can
stand alone or belong to an **organization** (see "Organizations and
subscriptions").

Like organizations, groups auto-enroll: subscribing a group to a course enrolls
every current member, and anyone added later is enrolled automatically.
Un-subscribing is non-destructive.

A **group manager** is a sub-admin scoped to one group. They add, edit, enroll,
and remove that group's members from their **Manage** area, limited to the
permissions you grant.

## How to use it

### Create a group
On /admin/groups, under **Create a group**, enter a **name** (e.g. `Austin ISD`)
and an optional **description**, then choose **Create group**. Creating a group
whose name already exists reopens the existing one.

Two bulk shortcuts build many groups from your existing user data:
- **Create groups from organizations (districts)** — one group per distinct
  Organization value on user profiles, with all matching users added.
- **Create groups from campuses** — one group per distinct Campus, named
  "Organization · Campus", with all matching users added.

Both ask for confirmation before running.

### Add and remove members
On the group page (/admin/groups/{id}):
- **Add members:** enter emails in the **Emails** field (commas, spaces, or new
  lines) and choose **Add to group**. Users must already exist; unknown emails are
  reported. New members are auto-enrolled in the group's subscriptions.
- **Remove:** each member row in the **Members** table has a **Remove** button
  (their account, enrollments, and progress are kept).

### Subscribe the group to courses
Under **Course subscriptions**:
1. Tick courses under **Add course subscriptions** ("enrolls all members now").
2. Choose **Subscribe & enroll**.
3. To drop a subscription, choose **Remove** in the subscriptions table. Current
   learners keep the course and their progress; only new members stop being
   auto-enrolled.

### Link a group to an organization
Under **Course subscriptions**, use the **Organization** dropdown: pick an org (or
"— none (standalone group) —") and choose **Save**. When you link a group to an
org, its current members inherit org membership and the org's org-wide courses.

### Assign a group manager
Under **Managers (sub-admins)**:
1. Enter the manager's email and choose **Assign manager**.
2. The person is added as a manager *and* as a member of the group.
3. Remove a manager with **Remove manager** (this keeps their account and group
   membership).

Managers assigned here get the default permissions (can enroll and can edit
members; cannot manage subscriptions). To set permissions precisely at assignment
time — including the option to also grant course-content editing — use **Account
management** (/admin/accounts) instead, and to adjust an existing manager's
Enroll/Members toggles, edit them there.

## Options & behavior
- **Auto-enroll on join** — adding a member runs the group's subscriptions (and,
  if the group is in an org, the org-wide subscriptions) via idempotent
  `enroll()`, so no duplicates.
- **Non-destructive un-subscribe** — removing a subscription keeps existing
  enrollments and progress.
- **Deleting a group** removes the group and its memberships only; user accounts,
  enrollments, and progress are not affected (the **Danger zone** confirms this).
- **Manager permissions** — a group-manager grant carries `enroll` and `members`
  (both default ON) and `courses` (managing subscriptions, default OFF). The
  Account management page exposes Enroll and Members; the "courses" permission is
  set at the org/group data level and defaults off.
- **Org managers manage all their org's groups** — someone who manages the parent
  organization can manage every group under it, even without a direct grant.
- **Completion filter & stats** — the group page shows members, enrollments,
  completions, completion rate, steps completed, and badges; a **Completion for:**
  dropdown scopes the stats to one course.
- **Export CSV** — **⬇ Export CSV** downloads per-member completion data.

## How it works
Groups are rows in `user_groups`; membership is `user_group_members`;
subscriptions are `group_courses`; manager grants are `group_managers`
(`perm_enroll`, `perm_members`, `perm_courses`). An `org_id` column links a group
to its organization. Adding a member fires `on_group_member_added()`, which
enrolls them in the group's courses and — if the group is in an org — adds them as
an org member so org-wide courses reach them too. `manager_perm()` falls back to
org-level management: if you are not a direct group manager but manage the group's
org, you inherit the corresponding org permission. Actions are audited
(`group.create`, `group.subscribe`, `group.add_manager`, `group.delete`, etc.).

## Tips & gotchas
- **Standalone vs. org-linked:** a group works fine on its own. Link it to an org
  only when you want its members to also receive org-wide courses.
- **"From organizations/campuses" reads user profile fields.** It groups by the
  Organization and Campus text on accounts, so those fields must be populated
  (e.g. via CSV import) for the shortcuts to be useful.
- **Un-subscribing keeps learners enrolled.** To pull access, disenroll people
  individually or in bulk.
- **Set manager permissions on the Account management page** for fine-grained
  control; the group page's quick "Assign manager" uses the defaults.
- Deleting a group is safe for learner data — it only removes the grouping.

## Related
- [Organizations and subscriptions](21-organizations-and-subscriptions.md)
- [Users and roles](20-users-and-roles.md)
- [The Manage area for sub-admins](25-manage-area-for-sub-admins.md)
- [Enrollment expiry and reminders](23-enrollment-expiry-and-reminders.md)
