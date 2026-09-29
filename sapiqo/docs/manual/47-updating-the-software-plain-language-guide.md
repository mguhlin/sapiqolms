# Updating the software — a step-by-step guide for administrators

**Who this is for:** any administrator who needs to install a software update,
even if you don't consider yourself "technical." No special knowledge is
assumed — every step is spelled out.

**The short version:** updating adds new features and fixes to the LMS
software itself. It does **not** touch your users, courses, badges,
certificates, settings, or anything else you've already built. Think of it
like updating an app on your phone — the app changes, your photos and files
inside it don't.

*(Looking for the technical reference instead — function names, file
locations, how the safety checks work under the hood? See
[Software updates](44-software-updates.md) for that. This page is the
plain-language walkthrough; that one is the engineering detail.)*

---

## The two things kept separate

Before the steps, it helps to know the LMS is built from **two separate
pieces**, kept apart on purpose:

1. **The software itself** — the program that runs the website: pages,
   buttons, features. This is what an update changes.
2. **Everything you've put into it** — your database of accounts, every
   course, every badge and certificate ever earned, your branding, your
   settings, your uploaded files. This is called **data**, and it lives in a
   completely separate place from the software.

An update **only ever replaces piece #1.** Piece #2 is never touched by the
update process — it physically lives somewhere the update can't reach, even
if something went wrong. That's the whole design, and it's why updating is
safe to do on your own without help from a developer.

---

## Before you start

You'll need an **update file** — it will have a name ending in `.tar.gz`
(think of it like a `.zip` file: a single file that contains a bundle of
other files). You'll either:

- receive this file from whoever maintains the software for your
  organization, **or**
- build one yourself from a working copy of the software, if you're the one
  maintaining it (see "Building your own update file," near the end).

Save the file somewhere easy to find, like your Desktop or Downloads folder.
You don't need to open, unzip, or do anything else with it — you'll upload it
exactly as you received it.

---

## Step-by-step: applying an update

**1. Sign in as an administrator** and click **Admin** in the top menu.

**2. Click "⚙️ Settings & integrations."** This opens a page of setting
categories, shown as cards.

**3. Click "⬆️ Software updates."** This opens the update page. Near the top,
it shows your current version number (e.g. "Version 1.12.0") — you don't need
to know what this means, it's just a label the software checks automatically.

**4. Find the card titled "Apply an update."** Click into the file field
(labeled **Update package**) and choose the `.tar.gz` file you saved earlier.

**5. Leave the checkbox below it unchecked**, in almost every case. (It says
*"Apply anyway, even if it isn't newer."* You would only ever tick this if
someone specifically told you to reinstall the exact same version or go back
to an older one — see the note in "Good to know," below.)

**6. Click the gold "Upload & apply update" button.**

**7. A confirmation box will pop up**, asking you to confirm. It will
mention that the current software will be backed up first — that's the
built-in safety net. Click **OK** to proceed.

**8. Wait a few seconds.** The page will reload and show you a message telling
you it worked — something like *"Updated from 1.12.0 to 1.13.0. A backup of
the previous code was saved."* If you see that message, the update is
installed.

**9. Reload the page once more** (refresh your browser). This gives the
software a chance to finish any small behind-the-scenes setup the update
needs — you won't see anything happen, it's just good practice.

**That's it.** Your users, their accounts, their progress, their badges,
your courses, and all your settings are exactly as they were before.

---

## If something looks wrong afterward

This is rare, but here's exactly what to do if a page looks broken or a
feature stops working right after an update:

**1. Go back to Admin → Settings & integrations → Software updates.**

**2. Scroll down to the card titled "Backups & rollback."** You'll see a list
of previous versions of the software, saved automatically — the most recent
one is labeled **"most recent."**

**3. Click "Roll back"** next to that most recent backup.

**4. Confirm the pop-up box.** This puts the software back exactly the way it
was right before the update — again, without touching any of your users,
courses, or data.

**5. Reload the page.** You're back to normal, and can try the update again
later or contact whoever maintains the software for you.

---

## Good to know

- **You can't accidentally lose anything.** The update only ever adds or
  replaces the software's own files — it's built so that it literally cannot
  reach your accounts, courses, badges, or settings, even by mistake.
- **A backup is made automatically, every single time**, before anything is
  changed — you never have to remember to do this yourself.
- **The checkbox you usually leave unchecked** ("Apply anyway…") exists for
  one specific situation: if the software thinks the file you uploaded isn't
  actually newer than what you already have (for example, if you're
  intentionally reinstalling or going back to an older version on purpose).
  If you upload a normal, newer update file, you won't need to touch it.
- **If the "Upload & apply update" button is grayed out**, the software
  folder on your server isn't writable — this is a one-time technical setup
  issue for whoever manages your server, not something wrong with the update
  file. Contact them, or see the technical reference page for the exact
  permission fix.
- **Nothing visually changes most of the time.** Many updates are behind-the-
  scenes fixes and small improvements — don't worry if the site looks
  identical afterward. The version number at the top of the update page will
  have changed, which confirms it worked.

---

## Building your own update file

*(Skip this section if someone else always hands you the update file — most
administrators never need to do this part themselves.)*

If you *are* the person responsible for maintaining the software and you've
made changes to a working copy of it, you can build your own update file
in one of two ways:

- **From inside the website:** go to Admin → Settings & integrations →
  Software updates, find the card titled **"Create an update package,"** and
  click **"⬇ Download update package."** This creates a `.tar.gz` file built
  from exactly what's currently running — hand that file to any other install
  to bring it up to the same version.
- **From a technical setup**, someone comfortable with a command line can run
  a single command (`php bin/build-update.php`) to do the same thing — see
  the technical reference page for details.

---

## Related pages

- [Software updates](44-software-updates.md) — the technical reference: what's
  inside an update file, exactly how the safety checks work, and file
  locations.
- [Backups](34-backups.md) — a separate, full snapshot of your data (not just
  the software) for disaster recovery.
