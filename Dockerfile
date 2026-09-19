FROM php:8.2-apache

# PHP extensions the app needs:
#   mysqli + pdo_mysql — database connections
#   curl              — Gemini / OpenAI / openFDA API calls
#   mbstring          — text helpers (chat, medicine sync)
#
# Apache MPM fix: recent php:*-apache image tags ship with BOTH
# mpm_event and mpm_prefork enabled, which makes Apache refuse to
# start ("AH00534: More than one MPM loaded"). We disable the others
# and keep exactly ONE MPM — prefork (required by mod_php).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev default-mysql-client \
    && docker-php-ext-install mysqli pdo_mysql curl mbstring \
    && a2dismod mpm_event 2>/dev/null || true \
    && a2dismod mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy the app (secrets are excluded via .dockerignore)
COPY . /var/www/html/

# Uploads dir must exist and be writable by Apache
RUN mkdir -p /var/www/html/user/uploads/prescriptions \
    && chown -R www-data:www-data /var/www/html/user/uploads

# Entrypoint: resolve DB + PORT, then start Apache once
COPY docker/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

EXPOSE 80
CMD ["/usr/local/bin/startup.sh"]