#!/bin/sh
set -e

echo "[startup] MediConnect container starting..."

# ---------------------------------------------------------------
# Port: Railway injects $PORT for its proxy (commonly 8080). To be
# reachable no matter which port Railway probes, Apache listens on
# BOTH the classic 80 AND $PORT (deduplicated when they are equal).
# "Listen" binds all interfaces (0.0.0.0) by default.
# ---------------------------------------------------------------
PORT="${PORT:-80}"

if [ "$PORT" = "80" ]; then
    sed -i "s/^Listen 80$/Listen 80/" /etc/apache2/ports.conf
else
    sed -i "s/^Listen 80$/Listen 80\nListen ${PORT}/" /etc/apache2/ports.conf
fi

# Match the site on any port (80 and $PORT both serve the app).
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:*>/" /etc/apache2/sites-enabled/000-default.conf

# "/" must resolve to the PHP entry point.
if ! grep -q "^DirectoryIndex" /etc/apache2/apache2.conf; then
    echo "DirectoryIndex index.php index.html" >> /etc/apache2/apache2.conf
fi

# Quiet the "could not reliably determine the server's FQDN" warning.
if ! grep -q "^ServerName" /etc/apache2/apache2.conf; then
    echo "ServerName localhost" >> /etc/apache2/apache2.conf
fi

echo "[startup] Apache Listen directives:"
grep -E "^Listen" /etc/apache2/ports.conf

# ---------------------------------------------------------------
# Database bootstrap (idempotent; never fatal). The web server
# starts even if the DB is not reachable yet — migration failure
# only logs a warning and skips.
# ---------------------------------------------------------------
if [ -z "$MYSQL_DISABLE_INIT" ]; then
  if php /var/www/html/docker/migrate.php; then
    echo "[startup] Database is ready."
  else
    echo "[startup] Database init skipped (not fatal) — check MYSQL_URL / MYSQL_* env vars are linked to the app service."
  fi
fi

# ---------------------------------------------------------------
# Start Apache in the foreground. exec replaces the shell so
# Apache becomes PID 1 and the container stays alive.
# ---------------------------------------------------------------
exec apache2-foreground