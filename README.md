# User Management

Symfony 7.4 user management application for PHP 8.4.

## Local development (`symfony server`)

1. Copy [`.env.local.example`](.env.local.example) to `.env.local` and set `MAILER_DSN`, `MAILER_SENDER`, and `DEFAULT_URI`.
2. Start PostgreSQL (Docker database service is enough: `docker compose up -d database`).
3. Start the app: `symfony server:start`.

Registration saves the user, then sends the confirmation email **before** the success redirect (HTTPS to [Resend](https://resend.com)). For offline work, Mailpit still works with `MAILER_DSN=smtp://127.0.0.1:1025` from `.env.dev`.

## Docker Compose

```bash
docker compose up -d --build
```

The application is at <http://localhost:8080>. Compose defaults to Mailpit unless you override `MAILER_DSN`.

```bash
docker compose exec -e APP_ENV=test app php bin/console doctrine:database:create --if-not-exists
docker compose exec -e APP_ENV=test app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -e APP_ENV=test app php bin/phpunit
```

## Resend credentials

1. Sign up at [https://resend.com](https://resend.com).
2. **API Keys** → **Create API Key** (Sending access). Copy `re_…`.
3. **Domains** → add a domain you control, add the DNS records, wait until **Verified**. Then From can be `noreply@that-domain`.
4. Without a verified domain, Resend only allows From `beth.t@example.com` and To = the account email (not enough for other inboxes).

Do not commit the API key.

## Render

[`render.yaml`](render.yaml) defines a free web service and PostgreSQL. After installing `symfony/resend-mailer`, **redeploy** so the image includes the package.

Set these on **user-management-web** (replace Gmail SMTP):

| Key | Value |
|---|---|
| `MAILER_DSN` | `resend+api://re_YOUR_KEY@default` (URL-encode `+`, `/`, `=` in the key if present) |
| `MAILER_SENDER` | `noreply@YOUR_VERIFIED_DOMAIN` (must be a Resend-verified domain, not a raw Gmail address) |
| `DEFAULT_URI` | `https://user-management-web-5hli.onrender.com` |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` |
| `RUN_MESSENGER_WORKER` | unset |

Mail goes **to** the address on the registration form. Logs should show `Sent registration confirmation email` (not `smtp.gmail.com` timeout).

Secrets must not be committed to the repository.
