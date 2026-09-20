#!/bin/sh
set -e

echo "[startup] MediConnect container starting..."

# ---------------------------------------------------------------
# PORT: Railway injects $PORT for its proxy (commonly 8080). We
# listen on BOTH the classic 80 AND $PORT so the app is reachable
# no matter which port Railway probes. ports.conf is REWRITTEN
# from scratch on every boot (never patched), so restarts can't
# accumulate duplicate "Listen" lines. "Listen" binds all
# interfaces (0.0.0.0) by default.
# ---------------------------------------------------------------
PORT="${PORT:-80}"

if [ "$PORT" = "80" ]; then
    printf 'Listen 80\n' > /etc/apache2/ports.conf
else
    printf 'Listen 80\nListen %s\n' "$PORT" > /etc/apache2/ports.conf
fi

# Single site, reachable on ANY port (Railway forwards to $PORT,
# the health probe may use 80). Rewritten deterministically so no
# stale virtual-host file can ever survive a restart.
cat > /etc/apache2/sites-enabled/000-default.conf <<'EOF'
<VirtualHost *:*>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>
    ErrorDocument 400 /error.php?code=400
    ErrorDocument 401 /error.php?code=401
    ErrorDocument 402 /error.php?code=402
    ErrorDocument 403 /error.php?code=403
    ErrorDocument 404 /error.php?code=404
    ErrorDocument 405 /error.php?code=405
    ErrorDocument 408 /error.php?code=408
    ErrorDocument 410 /error.php?code=410
    ErrorDocument 429 /error.php?code=429
    ErrorDocument 500 /error.php?code=500
    ErrorDocument 502 /error.php?code=502
    ErrorDocument 503 /error.php?code=503
    ErrorDocument 504 /error.php?code=504
</VirtualHost>
EOF

# "/" must resolve to the PHP entry point.
if ! grep -q "^DirectoryIndex" /etc/apache2/apache2.conf; then
    echo "DirectoryIndex index.php index.html" >> /etc/apache2/apache2.conf
fi

# Quiet the "could not reliably determine the server's FQDN" warning.
if ! grep -q "^ServerName" /etc/apache2/apache2.conf; then
    echo "ServerName localhost" >> /etc/apache2/apache2.conf
fi

# ---------------------------------------------------------------
# EXACTLY ONE MPM. Apache refuses to start when more than one MPM
# module is loaded (AH00534). We remove EVERY mpm_* load file from
# mods-enabled — even ones dropped in as plain files or without
# mods-available entries — then enable ONLY mpm_prefork, which
# mod_php requires. Nothing else is modified.
# ---------------------------------------------------------------
echo "[startup] MPM .load files before fix:"
ls /etc/apache2/mods-enabled/ | grep '^mpm_' || echo "  (none)"

rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork >/dev/null 2>&1 || true

echo "[startup] MPM .load files after fix (must be mpm_prefork only):"
ls /etc/apache2/mods-enabled/ | grep '^mpm_' || echo "  (none)"

# ---------------------------------------------------------------
# Validate the final configuration BEFORE serving. If Apache would
# refuse to start for ANY reason, stop here and print the exact
# error so the Railway logs show the cause instead of a silent 502.
# ---------------------------------------------------------------
echo "[startup] Apache listen directives:"
cat /etc/apache2/ports.conf

if ! apache2ctl -t 2>&1; then
    echo "[startup] FATAL: Apache configuration invalid (see errors above). Exiting." >&2
    exit 1
fi

# ---------------------------------------------------------------
# Database bootstrap (unchanged — working): runs IN THE BACKGROUND
# and never blocks the web server. Bounded retries inside
# migrate.php plus a hard 300s cap via `timeout`. MySQL config is
# untouched: env vars resolve via config/database_env.php.
# ---------------------------------------------------------------
if [ -z "$MYSQL_DISABLE_INIT" ]; then
  echo "[startup] Starting background database bootstrap (bounded, non-blocking)..."
  ( timeout 300 php /var/www/html/docker/migrate.php \
      || echo "[startup] Background DB init gave up (MySQL still unavailable). Web server is up; check that the MySQL service is linked and MYSQL_* / MYSQL_URL env vars are set." ) &
fi

# ---------------------------------------------------------------
# Start Apache in the foreground. exec replaces the shell so Apache
# becomes PID 1 and the container stays alive.
# ---------------------------------------------------------------
exec apache2-foreground