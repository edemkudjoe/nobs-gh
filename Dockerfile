FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app

# Production PHP settings, and the PostgreSQL driver
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && install-php-extensions pdo_pgsql

COPY Caddyfile /etc/caddy/Caddyfile
COPY public ./public
COPY includes ./includes

# Vercel sends traffic to $PORT (default 80)
ENV PORT=80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]