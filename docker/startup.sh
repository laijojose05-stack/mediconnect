#!/bin/sh
set -e

echo "[startup] MediConnect container starting..."

# Bootstrap the database schema (idempotent — skips when tables already exist).
# Fails soft so the web server still boots and shows a clear DB error page
# instead of crash-looping while the MySQL plugin provisions.
if [ -z "$MYSQL_DISABLE_INIT" ]; then
  if php /var/www/html/docker/migrate.php; then
    echo "[startup] Database is ready."
  else
    echo "[startup] Database init skipped (not fatal) — check MYSQL_* / MYSQL_URL env vars."
  fi
fi

# Start Apache in the foreground (container keeps running)
exec apache2-foreground