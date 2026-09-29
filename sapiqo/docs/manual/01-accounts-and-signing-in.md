# Accounts and signing in

**Audience:** learner
**Where:** /login, /register, /forgot, and Profile (top navigation → Profile, or /profile)

## What it is
Every learner has one Sapiqo account. You sign in with your email address and a
password, or with a single sign-on (SSO) button if your organization has enabled
one (Google, Microsoft, Clever, ClassLink, or Rhythm). Your account holds your
course progress, badges, certificates, and transcript, so signing in on any
device brings all of that with you.

Depending on how your administrator configured Sapiqo, you may be able to create
your own account, or accounts may be provisioned for you. Your email address is
both your identity and your sign-in name.

## How to use it
1. Go to the sign-in page (/login). Enter your **Email** and **Password**, then
   choose **Sign in**.
2. If you see SSO buttons at the top of the sign-in card (for example **Continue
   with Google**), you can select one instead of typing a password. You are sent
   to that provider, you sign in there, and you are returned to your dashboard.
3. If self-registration is enabled, choose **Create an account** on the sign-in
   page (or **Register** in the top navigation) and fill in the form (see below).
4. If you forget your password, choose **Forgot your password?** on the sign-in
   page and follow the reset steps.
5. Once signed in, open **Profile** in the top navigation to update your details,
   upload a photo, or change your password.

### Creating an account (self-registration)
1. On /register, enter your **First name**, **Last name**, and **Email**.
2. Choose a **Password** of at least 8 characters.
3. Optionally add your **Phone**, **Campus**, **Organization / District**, and
   **User type** (Teacher, Student, Administrator, Staff, or Other).
4. Optionally pick a course under **Enroll in a course (optional)** to be enrolled
   right away.
5. Choose **Create account**. You are signed in and taken to your dashboard.

### Resetting your password
1. On /forgot, enter your account email and choose **Send reset link**.
2. Sapiqo always shows the same confirmation message whether or not an account
   exists for that address (this protects privacy). If an account exists, a reset
   link is generated.
3. Open the link (valid for one hour), enter your **New password** twice, and
   choose **Set new password**.
4. Sign in with your new password.

### Changing your password (while signed in)
1. Open **Profile**.
2. In the lower card, type into **New password (optional)**. Leaving it blank
   keeps your current password.
3. Choose **Save changes**.

### Uploading a profile photo
1. On **Profile**, under **Profile photo**, choose an image file (JPEG, PNG, or
   WebP) and select **Upload photo**.
2. To remove a photo you uploaded, choose **Remove**.

## Options & behavior
- **Email + password sign-in** — the standard method. Passwords must be at least
  8 characters.
- **SSO buttons** — one appears per provider your administrator has enabled and
  configured (Google, Microsoft, Clever, ClassLink, Rhythm). If no providers are
  enabled, you will not see any SSO buttons or the "or use your email" divider.
- **Create an account** link — appears only when self-registration is enabled by
  your administrator. If it is turned off, registering redirects you back to
  sign-in with a note to ask your administrator for an account.
- **Forgot your password?** — starts the self-service reset. If email delivery is
  not configured on the server, the reset page tells you to contact your
  administrator for the link instead.
- **Profile fields** — First name, Last name, Phone, User type, Campus, and
  Organization / District are editable. **Email is shown but disabled** — it is
  your sign-in and can only be changed by an administrator.
- **Profile photo** — upload one, or remove it. If you signed in with Google or
  Microsoft, your provider photo is used automatically until you upload your own.

## How it works
Passwords are stored only as secure one-way hashes, never as plain text, and are
re-hashed automatically if the security settings change. Password reset links are
single-use and expire after one hour; only a hash of the link is stored, so a
copy of the database cannot be used to take over accounts. Repeated failed
sign-in attempts are rate-limited — after too many tries you are asked to wait a
few minutes before trying again.

SSO works over OAuth/OpenID Connect. When you sign in with a provider for the
first time, Sapiqo matches you to an existing account by email or creates a new
learner account, and pulls in your name (and photo, where available). A provider
button only appears once your administrator has entered real credentials for it.

Uploaded photos are cropped to a centered square and resized to 256×256 pixels.

## Tips & gotchas
- Your email is case-insensitive for sign-in.
- If your SSO sign-in fails or you cancel it, you are returned to /login with a
  brief notice — just try again.
- If the **Create an account** link is missing, self-registration is off; ask
  your administrator to add you.
- If you don't receive a reset email, check that email is configured on your
  server; otherwise your administrator can retrieve the link for you.
- You cannot change your own email on the Profile page — contact an administrator.

## Related
- [Taking a course](02-taking-a-course.md)
- [Badges, certificates, and your transcript](05-badges-certificates-transcript.md)
- [Notifications](06-notifications.md)
