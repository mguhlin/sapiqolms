# The Manage area for sub-admins

**Audience:** administrator (to understand and set up sub-admins); group and
organization managers (day-to-day users of this area)
**Where:** Manage (/manage) — appears in the top navigation for group and
organization managers. Also reachable at /manage/orgs/{id}, /manage/groups/{id},
and /manage/users/{id}.

## What it is
The **Manage** area is a scoped, self-service console for **sub-admins** — the
group managers and organization managers an administrator has assigned. It shows
only the organizations and groups a person manages, and it lets them do exactly
what their granted permissions allow: enroll members, edit member profiles, and/or
manage course subscriptions. It never exposes site settings, course content, or
other people's groups.

Administrators assign these managers on the Organizations page
(/admin/orgs/{id}) and the Groups / Account management pages — see "Organizations
and subscriptions" and "Groups and managers." This page explains what a manager
sees and can do once assigned.

## How to use it (as a manager)
1. Sign in and choose **Manage** in the top navigation (it appears for anyone who
   manages at least one group or org). You land on /manage.
2. The **Manage** home lists your **Organizations** (if any) and your **Groups**,
   each with member and completion counts. Choose **Open** to work with one.
3. On an org or group page you can, subject to your permissions:
   - **Add members by email** — separate with commas, spaces, or new lines.
   - **Remove** a member from the org or group.
   - **Edit** a member (opens /manage/users/{id}): update their profile and/or
     enrollments.
   - **Subscribe & enroll** the org/group to courses, or **Remove** a subscription.
4. When you add an email that has **no account yet**, Sapiqo creates a learner
   account on the fly with a **temporary password** and shows it to you in a **New
   accounts created** card. Share each temporary password securely and ask the
   person to change it on their Profile page.

## What each permission allows
A manager grant carries up to three permissions. What appears and works in the
Manage area depends on which you hold:

- **Enroll** (`enroll`) — lets you change a member's course **enrollments** from
  their edit page (/manage/users/{id}). Tick to enroll, untick to remove.
- **Members** (`members`) — lets you **add and remove members** of the group/org
  and **edit member profiles** (name, phone, user type, campus, organization) and
  set a member's password on their edit page. Adding members is also what triggers
  on-the-fly account creation. Note: a manager **cannot change a member's email
  (sign-in)** — that field is shown disabled and only a full administrator can
  change it.
- **Courses** (`courses`) — lets you **manage course subscriptions** for the
  group/org: the **Subscribe & enroll** form and per-course **Remove** buttons
  appear only with this permission. Without it, the subscriptions list is
  read-only and a note explains that an administrator controls subscriptions.

For organization managers, the three permissions are **Can enroll members**, **Can
add/edit members**, and **Can manage course subscriptions**, set when the admin
assigns the manager. `enroll` and `members` default ON; `courses` defaults OFF.

An org manager implicitly manages **every group** under their organization, so
they can open those groups in the Manage area too.

## Options & behavior
- **Scoped visibility** — /manage shows only the orgs and groups you manage;
  opening one you don't manage returns a "Forbidden" response.
- **On-the-fly account creation** — in both the group and org "Add members by
  email" forms, unknown (but valid) emails become new learner accounts with a
  generated temporary password. The passwords are shown once, in the **New
  accounts created** card, right after you add them.
- **Non-destructive removal** — removing a member from an org/group keeps their
  account, enrollments, and progress.
- **Non-destructive un-subscribe** — removing a subscription keeps current
  learners enrolled; only future auto-enrollment stops.
- **Auto-enroll on add** — a newly added member is auto-enrolled in the group's
  (and, for groups in an org, the org's) course subscriptions.
- **Completion view & export** — group and org pages show member/completion stats;
  the group page has a **Completion for:** course filter and an **⬇ Export CSV**
  button.
- **Admins land on /admin/groups** — if a full administrator with no manager
  grants visits /manage, they are redirected to Admin → Groups (their broader
  tool). Admins who *do* also manage something can use /manage directly.

## How it works
Every Manage route re-checks access: `require_group_access()` /
`require_org_access()` confirm you manage that entity, and each write also checks
the specific permission (`manager_allows_group()` / `manager_allows_org()` for
`enroll`, `members`, or `courses`) before acting — so the UI hiding a control is
backed by a server-side guard. Member edits go through `manager_allows_user()`,
which is true when you hold the permission for a group the target belongs to.
On-the-fly accounts are created with `register_local()` using a random temporary
password, and the plaintext temp password is passed back once via the session for
display. Member add/remove and subscribe/unsubscribe reuse the same
`org_add_member` / `add_member` / `group_subscribe` / `org_subscribe` functions as
the admin pages, so auto-enroll and non-destructive semantics are identical.

## Tips & gotchas
- **Grant "courses" deliberately.** It lets a manager change what the whole
  group/org is enrolled in; leave it off if managers should only handle people.
- **Temporary passwords show once.** Copy them from the **New accounts created**
  card immediately; if you miss one, an administrator can generate a reset link
  from the user editor.
- **Managers can't change emails.** Email is the sign-in identity — route those
  requests to a full administrator.
- **Removing ≠ revoking access.** Removing a member keeps their transcript and
  enrollments; use it to tidy rosters, not to strip credit.
- If **Manage** is missing from a manager's navigation, they have not been
  assigned to any group or org yet — assign them from the admin org/group pages.

## Related
- [Organizations and subscriptions](21-organizations-and-subscriptions.md)
- [Groups and managers](22-groups-and-managers.md)
- [Users and roles](20-users-and-roles.md)
- [View as (impersonation)](24-impersonation.md)
