FROM dunglas/frankenphp:1-php8.5

ARG UID=1000

RUN install-php-extensions @composer intl opcache pdo_pgsql zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY .docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY .docker/php/Caddyfile /etc/frankenphp/Caddyfile

# The container runs as the host user so that files created inside it stay editable on the host.
# Caddy listens on 8080 to avoid the privileged-port capability, which a non-root user cannot use.
RUN useradd --uid ${UID} --create-home app \
    && setcap -r /usr/local/bin/frankenphp \
    && chown -R app /data/caddy /config/caddy

USER app
WORKDIR /app
