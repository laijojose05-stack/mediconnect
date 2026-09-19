FROM php:8.2-apache

# Extensions the app needs:
#   mysqli + pdo_mysql — database connections
#   curl              — Gemini / OpenAI / openFDA API calls
#   mbstring          — text helpers (chat, medicine sync)
# default-mysql-client is used during bootstrap for a readiness probe
# (docker/migrate.php does the actual schema import).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev default-mysql-client \
    && docker-php-ext-install mysqli pdo_mysql curl mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy the app (secrets are excluded via .dockerignore)
COPY . /var/www/html/

# Uploads dir must exist and be writable by Apache
RUN mkdir -p /var/www/html/user/uploads/prescriptions \
    && chown -R www-data:www-data /var/www/html/user/uploads

# Entrypoint: bootstrap DB, then start Apache
COPY docker/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

EXPOSE 80
CMD ["/usr/local/bin/startup.sh"]