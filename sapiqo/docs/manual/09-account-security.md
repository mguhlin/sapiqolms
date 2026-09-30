# Account security

From **Profile → Account security**, enable two-step verification using a
standard authenticator app. Add the displayed secret as a six-digit, time-based
SHA-1 code with a 30-second period, then enter its current code to confirm.
Save the ten recovery codes immediately; each works once and they are not
shown again. A used authenticator code cannot be replayed.

Security changes require your current password or a fresh authenticator/recovery
code. Provider-only accounts must sign in again within five minutes. Password
changes revoke existing sessions; **Sign out all sessions** also signs out this
browser. Password resets do not remove two-step verification.

Additional SSO methods can be linked after you authenticate both your existing
account and the configured provider. Matching email alone never links accounts.
An identity already belonging to another account cannot be attached to yours.
Ask the operator for verified private recovery if you lose both the authenticator
and recovery codes.

Related: [Accounts and signing in](01-accounts-and-signing-in.md).
