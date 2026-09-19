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
# START APACHE FIRST — the service must be reachable immediately.
# index.php is database-independent, so the landing page answers
# (and the health check passes) even while MySQL is still offline.
# ---------------------------------------------------------------

# ---------------------------------------------------------------
# Database bootstrap runs IN THE BACKGROUND and NEVER blocks the
# web server. It is bounded: ~60s of retries inside migrate.php,
# plus a hard 300s cap via `timeout`. If MySQL is not linked yet,
# migration gives up quietly and Apache stays up. Pages that need
# the DB show a clear "database unavailable" error, not a 502.
# ---------------------------------------------------------------
if [ -z "$MYSQL_DISABLE_INIT" ]; then
  echo "[startup] Starting background database bootstrap (bounded, non-blocking)..."
  ( timeout 300 php /var/www/html/docker/migrate.php \
      || echo "[startup] Background DB init gave up (MySQL still unavailable). Web server is up; check that the MySQL service is linked and MYSQL_* / MYSQL_URL env vars are set." ) &
fi

# ---------------------------------------------------------------
# Run Apache in the foreground. exec replaces the shell so Apache
# becomes PID 1 and the container stays alive.
# ---------------------------------------------------------------
exec apache2-foreground