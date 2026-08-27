# User Management

Symfony 7.4 user management application for PHP 8.4.

## Local development (`symfony server`)

1. Copy [`.env.local.example`](.env.local.example) to `.env.local` and set a Gmail app password (`MAILER_DSN`, `MAILER_SENDER`, `DEFAULT_URI`).
2. Start PostgreSQL (Docker database service is enough: `docker compose up -d database`).
3. Start the app: `symfony server:start`.
4. Consume confirmation emails in a **second** terminal. Registration only queues the message; nothing is sent until this worker is running:

```bash
php bin/console messenger:consume async -vv
```

Without the worker, users stay Unverified and no confirmation mail is delivered.

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

[`render.yaml`](render.yaml) defines a free web service and PostgreSQL. Render's free plan does not allow a separate background worker, so the web process also runs `messenger:consume` when `RUN_MESSENGER_WORKER=1`.

Set these on the service:

- `MAILER_DSN` — `gmail+smtp://you%40gmail.com:APP_PASSWORD@default` (App Password, encode `@` as `%40`)
- `MAILER_SENDER` — the same Gmail address (this is the From address, not the recipient)
- `DEFAULT_URI` — `https://<your-web-service>.onrender.com`

Confirmation mail is sent **to** the address entered on the registration form.

A dedicated worker service requires a paid Render plan.

Secrets must not be committed to the repository.
