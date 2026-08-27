# User Management

Symfony 7.4 user management application for PHP 8.4.

## Local development (`symfony server`)

1. Copy [`.env.local.example`](.env.local.example) to `.env.local` and set a Gmail app password (`MAILER_DSN`, `MAILER_SENDER`, `DEFAULT_URI`).
2. Start PostgreSQL (Docker database service is enough: `docker compose up -d database`).
3. Start the app: `symfony server:start`.
4. Consume confirmation emails in a second terminal:

```bash
php bin/console messenger:consume async -vv
```

Confirmation mail is queued in `messenger_messages` and is only sent while the worker is running.

## Docker Compose

```bash
docker compose up -d --build
```

The application is at <http://localhost:8080>. The Compose `worker` service consumes the mail queue. Override `MAILER_DSN` in `.env.local` / Compose env to use Gmail instead of Mailpit.

```bash
docker compose exec -e APP_ENV=test app php bin/console doctrine:database:create --if-not-exists
docker compose exec -e APP_ENV=test app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -e APP_ENV=test app php bin/phpunit
```

## Render

[`render.yaml`](render.yaml) defines a web service, a Messenger worker, and PostgreSQL.

Set these on first deploy (`sync: false` in the Blueprint):

- `MAILER_DSN` — Gmail SMTP with an app password
- `MAILER_SENDER` — the same Gmail address
- `DEFAULT_URI` — `https://<your-web-service>.onrender.com`

Secrets must not be committed to the repository.
