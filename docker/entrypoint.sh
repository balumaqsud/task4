#!/bin/sh
set -eu

# Important: Render's Postgres addon uses postgres://; Doctrine expects postgresql://.
if [ -n "${DATABASE_URL:-}" ]; then
    DATABASE_URL="$(printf '%s' "$DATABASE_URL" | sed 's|^postgres://|postgresql://|')"
    case "$DATABASE_URL" in
        *serverVersion=*) ;;
        *\?*) DATABASE_URL="${DATABASE_URL}&serverVersion=18&charset=utf8" ;;
        *) DATABASE_URL="${DATABASE_URL}?serverVersion=18&charset=utf8" ;;
    esac
    export DATABASE_URL
fi

# Note: Render injects RENDER_EXTERNAL_URL; confirmation links need an absolute origin.
if [ -z "${DEFAULT_URI:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    DEFAULT_URI="$RENDER_EXTERNAL_URL"
    export DEFAULT_URI
fi

role="${1:-web}"

if [ "$role" = "worker" ]; then
    exec php bin/console messenger:consume async --no-interaction --time-limit=3600
fi

PORT="${PORT:-80}"
if [ "$PORT" != "80" ]; then
    sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

php bin/console cache:clear --no-warmup
php bin/console doctrine:migrations:migrate --no-interaction
chown -R www-data:www-data var/cache var/log

# Nota bene: Render's free plan allows only web services, so the async mail
# consumer can run in this process when RUN_MESSENGER_WORKER=1.
if [ "${RUN_MESSENGER_WORKER:-0}" = "1" ]; then
    php bin/console messenger:consume async --no-interaction --time-limit=3600 &
fi

exec apache2-foreground
