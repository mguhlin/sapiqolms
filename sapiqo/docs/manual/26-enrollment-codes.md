# Enrollment codes

**Audience:** administrator
**Where:** Admin → Account management → **Enrollment codes** (/admin/codes).

## What it is
**Enrollment codes** let you hand out (or import) short codes that auto-enroll
learners into one or many courses when they redeem them. Instead of adding people to
courses one by one, you generate a code, share it, and everyone who redeems it is
enrolled — ideal for a **group or cohort subscription**, a conference cohort, or a
partner who distributes access through their own system.

Each code can grant a **single course or a whole series**, carry a **seat limit**
(how many people may redeem it) and an optional **expiry date**, and can be
**deactivated** at any time. You can see every code you've issued, who redeemed it,
and which courses it affects.

## How to use it

### Create a code
1. Open Admin → Account management → **Enrollment codes**.
2. In **Create a code**, fill in:
   - **Label** — an internal note (e.g. "Aldirk ISD — Fall cohort"). Learners never
     see it; it's how you recognize the code later.
   - **Code** — leave blank to auto-generate an unambiguous code like
     `ABCD-EF23-GH45`, **or** paste a code from another system (letters, digits, and
     dashes) if an external platform issues the codes.
   - **Courses** — tick one or many. Ticking several makes the code a bundle/series.
   - **Seats / max uses** — how many people may redeem it. Leave blank (or 0) for
     unlimited.
   - **Expires** — an optional last day the code works.
3. Choose **Create code**. The new code appears in **Issued codes** with a
   ready-to-share **Redeem link** (`/redeem?code=…`).

### Share it
Give out either the code itself (learners type it on the **Redeem a code** page or at
sign-up) or the **Redeem link**, which starts newcomers in the redeem-then-register
flow. See [Redeeming an enrollment code](07-redeeming-a-code.md) for the learner
experience.

### Track and manage
- **Search** issued codes by code or label.
- **Redemptions** (on each row) opens a detail view listing every user who redeemed
  the code, when, and the **courses affected**; each user links to their account.
- **Deactivate / Reactivate** turns a code off or back on without deleting it.
- **Delete** removes the code and its records. Learners already enrolled through it
  **keep their access** — deleting only stops future redemptions.

## Options & behavior
- **One or many courses.** A code enrolls into every course attached to it, in one
  step. Add or remove courses by issuing a new code; a code's course set is fixed at
  creation.
- **Seats.** With a seat limit, each *new* learner consumes one seat; when the limit
  is reached the code reports "reached its usage limit." A learner re-redeeming a
  code they already used does **not** consume another seat.
- **Expiry.** After the expiry date the code stops working; existing enrollments are
  untouched.
- **Status** is shown as a pill: **Active**, **Inactive** (deactivated), **Expired**,
  or **Full** (seats exhausted).
- **Generated vs. external.** Auto-generated codes use an alphabet with no confusable
  characters (no `0/O/1/I`). Pasted external codes are stored upper-cased with spaces
  removed; duplicates are rejected.
- **Auto-enroll on sign-up/login.** A newcomer who starts from a code or redeem link
  is enrolled automatically the instant their account is created or they sign in.

## How it works
A code is stored in `enroll_codes` with its label, seat limit (`max_uses`), expiry,
and on/off flag; its courses live in `enroll_code_courses` (many-to-many); and each
redemption is recorded once per user in `enroll_code_redemptions`, which is what
enforces "one seat per person" and powers the redemptions report. Redeeming calls the
same idempotent enrollment used across Sapiqo, so progress, badges, and certificates
all work normally. Creating, redeeming, toggling, and deleting codes are all recorded
in the [audit log](35-audit-log.md).

## Tips & gotchas
- **Use the label well.** It's your only human-readable handle on a code — name it
  for the client, cohort, or campaign.
- **Seats are for group deals.** Set the seat count to the number of licenses sold so
  a shared code can't over-enroll.
- **Deleting is safe for learners.** Removing a code never un-enrolls anyone; use
  **Deactivate** if you simply want to stop new redemptions but keep the record.
- **Codes aren't invitations.** They enroll into courses but don't create accounts or
  send email — pair a code (or redeem link) with your own announcement.
- **Prefer the Redeem link for newcomers.** It routes first-time users through
  redeem → create account with the code already applied, which is the smoothest path.
- **For managed cohorts, consider groups/orgs too.** [Organizations & subscriptions](21-organizations-and-subscriptions.md)
  auto-enroll members you add directly; enrollment codes are the **self-service**
  counterpart where the learner brings the code.

## Related
- [Redeeming an enrollment code](07-redeeming-a-code.md)
- [Organizations & subscriptions](21-organizations-and-subscriptions.md)
- [Groups & managers](22-groups-and-managers.md)
- [Users & roles](20-users-and-roles.md)
