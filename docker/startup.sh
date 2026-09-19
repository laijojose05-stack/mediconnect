#!/bin/sh
set -e

echo "[startup] MediConnect container starting..."

# --- Port: honour Railway's $PORT (defaults to Apache's 80) ---
PORT="${PORT:-80}"
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf
fi
echo "[startup] Apache will listen on port ${PORT}"

# --- Database bootstrap (idempotent; never fatal) ---
# Fails soft so the web server still boots and shows a clear DB error page
# instead of crash-looping while the MySQL plugin provisions.
if [ -z "$MYSQL_DISABLE_INIT" ]; then
  if php /var/www/html/docker/migrate.php; then
    echo "[startup] Database is ready."
  else
    echo "[startup] Database init skipped (not fatal) — check MYSQL_URL / MYSQL_* env vars are linked to the app service."
  fi
fi

# --- Start Apache in the foreground (container keeps running) ---
exec apache2-foreground