# User Management

Symfony 7.4 user management application for PHP 8.4.

## Local development (`symfony server`)

1. Copy [`.env.local.example`](.env.local.example) to `.env.local` and set a Gmail app password (`MAILER_DSN`, `MAILER_SENDER`, `DEFAULT_URI`).
2. Start PostgreSQL (Docker database service is enough: `docker compose up -d database`).
3. Start the app: `symfony server:start`.

Registration saves the user, then **sends the confirmation email before the success redirect**. Use a Gmail App Password in `MAILER_DSN`. Mailpit works with `MAILER_DSN=smtp://127.0.0.1:1025`.

## Docker Compose

```bash
docker compose up -d --build
```

The application is at <http://localhost:8080>. Override `MAILER_DSN` in `.env.local` / Compose env to use Gmail instead of Mailpit.

```bash
docker compose exec -e APP_ENV=test app php bin/console doctrine:database:create --if-not-exists
docker compose exec -e APP_ENV=test app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -e APP_ENV=test app php bin/phpunit
```

## Render

[`render.yaml`](render.yaml) defines a free web service and PostgreSQL.

Set these on the service:

- `MAILER_DSN` — prefer `smtp://you%40gmail.com:APP_PASSWORD@smtp.gmail.com:465?encryption=ssl` (App Password, encode `@` as `%40`). Port 587/`tls` is often blocked from Render.
- `MAILER_SENDER` — the same Gmail address (From, not the recipient)
- `DEFAULT_URI` — `https://<your-web-service>.onrender.com`

Confirmation mail is sent **to** the address entered on the registration form. After deploy, Render logs should include `Sent registration confirmation email` or `Failed to send registration confirmation email`.

Secrets must not be committed to the repository.
