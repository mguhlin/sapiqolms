# Users and roles

**Audience:** administrator
**Where:** Admin → Account management (/admin/accounts) and Admin → Manage users (/admin/users)

## What it is
Sapiqo has one account per person, identified by email. What a person can do is
decided by their **role** and by any **grants** you give them. There are four
kinds of privileged access, described on the **Account management** page
(/admin/accounts) under "How access works":

- **Administrator** — full control of everything: users, courses, settings, and
  integrations. In the code this is `role = 'admin'`; every admin-only route calls
  `require_admin()`.
- **Course developer** — may create, edit, and import *course content* only.
  Cannot manage users, settings, or integrations. This is the
  `can_edit_content` capability (`can_edit_content()` / `require_content_access()`).
  Administrators always have it implicitly.
- **Group manager** — a sub-admin who manages the members of specific group(s):
  enroll them into courses and/or edit their profiles, according to the
  permissions you grant. A group manager *cannot* touch course content unless you
  also give them course-developer access. (Managers work from the **Manage** area,
  documented in "The Manage area for sub-admins".)
- **Learner** — the default role (`role = 'learner'`): takes courses, earns
  badges, sees their own dashboard and transcript. Every account that is not an
  administrator is a learner, even if it also holds a course-developer or
  group-manager grant.

The **Account management** page is the single place to see and assign every
privileged account. The **Manage users** page (/admin/users) is where you search
the full roster and edit individual accounts.

## How to use it

### Review and grant privileged access (/admin/accounts)
The page has three cards:

1. **Administrators** — lists every account with the admin role. Use **Edit** to
   open a user's profile. You promote or demote administrators from the user's
   profile Role field (see below), not from this list.
2. **Course developers** — type an email into the field and choose **Grant
   content-editing access**. The person can then author courses without full admin
   rights. Each listed developer has a **Revoke** button. (Trying to grant this to
   an administrator is rejected with "Administrators can already edit content.")
3. **Group managers** — pick a group from **Group they manage**, enter the
   manager's email, tick the permissions, and choose **Assign group manager**.
   You can tick **Can enroll members into courses**, **Can add / edit member
   profiles**, and optionally **Also allow editing course content** (which grants
   the course-developer capability at the same time). Assigning a manager also
   adds them as a member of that group.

### Search and manage individual users (/admin/users)
1. Type a name, email, campus, or organization into the search box and choose
   **Search**. The list is paginated at 50 per page.
2. Choose a person's name or **Edit** to open the full user editor.
3. In the editor you can change the profile (First name, Last name, Email, Phone,
   User type, Campus, Organization / District), set the **Role** (Learner or
   Administrator), reset the password, sync **Enrollments**, and set **Groups**
   membership. Choose **Save changes**.

### Change a role
- **Single user:** open the user (/admin/users/{id}), set **Role** to Learner or
  Administrator, and **Save changes**.
- **From the list, in bulk:** select rows with the checkboxes, choose **Make
  administrator** or **Make learner** in the Bulk action menu, and choose
  **Apply**.

### Bulk actions
Select users with the row checkboxes (or the header checkbox to select the whole
page), pick an action, and choose **Apply**:
- **Enroll in course** / **Remove from course** — pick a course in the "— course —"
  menu first.
- **Add to group** / **Remove from group** — pick a group in the "— group —" menu.
- **Make administrator** / **Make learner** — change roles.
- **Delete users** — permanently removes the selected accounts.

### Reset a user's password
Open the user editor and either:
- Type a new password into **Reset password (optional)** (at least 8 characters)
  and **Save changes**, or
- Use the **Password reset link** card at the bottom: choose **Generate reset
  link**. If email is configured, the link is emailed to the user. If not, the
  one-time link is shown to you on screen so you can share it securely. The link
  is valid for one hour and can be used once.

## Options & behavior
- **Search** matches email, first name, last name, organization, and campus.
- **Export CSV** on the Users page exports the roster with all profile fields; if
  you have searched, the button reads **Export results** and exports exactly the
  filtered rows.
- **Import CSV** links to the bulk user importer for creating accounts en masse.
- **View as** appears next to non-admin users (see "View as (impersonation)").
- **Enrollments** in the editor: check to enroll, uncheck to remove. Removing an
  enrollment keeps the learner's progress and any badge unless you tick **Also
  delete progress and any badge when removing an enrollment**.
- **Groups** in the editor: tick the groups this user belongs to; the set is saved
  exactly as checked. Adding to a group auto-enrolls the user in that group's (and
  its organization's) course subscriptions.
- **Guardrails against self-lockout:** you cannot remove your own administrator
  role (it is forced back to admin with a notice), the bulk "Make learner" action
  skips your own account, and bulk "Delete users" never deletes your own account.
- **Course developers list** shows only non-admin users who hold the capability;
  administrators are omitted because they already can edit content.

## How it works
Roles live in the `users.role` column. The course-developer capability is the
`users.can_edit_content` flag, toggled by `grant_course_dev()` /
`revoke_course_dev()`. Group-manager grants are rows in `group_managers` with
`perm_enroll` and `perm_members` flags. Passwords are stored only as one-way
hashes (`password_hash`), never plaintext, and are re-hashed automatically if the
hashing settings change. Admin-generated reset links store only a SHA-256 hash of
a single-use, one-hour token, so a copy of the database cannot be used to take
over accounts.

Privileged actions are written to the audit log — for example `user.role`,
`user.edit`, `users.bulk`, `account.course_dev`, `account.manager.assign`, and
`password.admin_reset_link` — so you can see who changed what and when.

## Tips & gotchas
- **Grant least privilege.** If someone only builds courses, make them a course
  developer, not an administrator. If someone only runs a campus roster, make them
  a group manager.
- **"No user found with that email."** The course-developer and manager grant
  forms only work on accounts that already exist. Create the account first
  (import, registration, or the Manage-area on-the-fly creation), then grant.
- A **group manager is always a member** of the group they manage — assigning the
  grant adds the membership automatically.
- Deleting a user is permanent and removes them everywhere; consider removing them
  from groups/orgs (which keeps their transcript) instead.
- The **audit log** (/admin/audit) is your record of role and password changes.

## Related
- [Organizations and subscriptions](21-organizations-and-subscriptions.md)
- [Groups and managers](22-groups-and-managers.md)
- [Enrollment expiry and reminders](23-enrollment-expiry-and-reminders.md)
- [View as (impersonation)](24-impersonation.md)
- [The Manage area for sub-admins](25-manage-area-for-sub-admins.md)
