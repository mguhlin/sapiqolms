#!/bin/sh
# First-boot setup: prepare the data volume + create the admin, then hand off to Apache.
set -e

mkdir -p /data/data
chown -R www-data:www-data /data

# Run one-time setup (idempotent) once per data volume.
if [ ! -f /data/.installed ]; then
  echo "==> First run: initializing database + administrator"
  : "${ADMIN_EMAIL:?Set ADMIN_EMAIL}"
  : "${ADMIN_PASSWORD:?Set ADMIN_PASSWORD}"
  # Credentials stay in the environment; never interpolate them into shell code.
  su -s /bin/sh www-data -c 'php /srv/sapiqo/bin/setup.php --from-env'
  touch /data/.installed
  echo "==> Admin: ${ADMIN_EMAIL}  (change the password after first login)"
fi

exec "$@"
