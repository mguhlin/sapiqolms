<?php
// Sweep expired enrollments (soft-unenroll; badges + progress are kept) and send
// graduated pre-expiry reminders when email is configured. Run from cron, daily:
//
//   0 2 * * *  php /path/to/sapiqo/bin/expire.php
//
// Reminder milestones come from the `expiry_reminder_days` setting (default
// "30,7,1"). Optionally override on the command line:  php bin/expire.php 30,7,1

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/mailer.php';
require_once __DIR__ . '/../app/notifications.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/courses.php';

ensure_schema();
$reminders = $argv[1] ?? null;   // e.g. "30,7,1"
$r = expire_enrollments($reminders);
printf("Expiry sweep complete: %d ended, %d reminded.\n", $r['removed'], $r['warned']);
