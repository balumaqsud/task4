# User Management

Symfony 7.4 user management application for PHP 8.4. Docker Compose is the supported development runtime.

## Development

Start the application, PostgreSQL, asynchronous Messenger worker, and Mailpit:

```bash
docker compose up -d --build
```

The application is available at <http://localhost:8080> and Mailpit at <http://localhost:8025>. Mail is sent only across the Compose network to `smtp://mailer:1025`; no host SMTP service is required.

Useful commands:

```bash
docker compose ps
docker compose logs --tail=100 worker mailer
docker compose exec app php bin/console doctrine:schema:validate
```

Create the isolated test database once, then run PHPUnit:

```bash
docker compose exec -e APP_ENV=test app php bin/console doctrine:database:create --if-not-exists
docker compose exec -e APP_ENV=test app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -e APP_ENV=test app php bin/phpunit
```

## Production Compose

`compose.prod.yaml` is provider-neutral. Set these environment variables to real secret or provider values before startup:

- `APP_SECRET`
- `DATABASE_URL`
- `MAILER_DSN`
- `MAILER_SENDER`
- `POSTGRES_DB`
- `POSTGRES_USER`
- `POSTGRES_PASSWORD`
- optionally `APP_PORT`

Then start it with:

```bash
docker compose -f compose.prod.yaml up -d --build
```

Production SMTP credentials and database secrets must not be committed to the repository.
