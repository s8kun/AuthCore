# Auth-as-a-Service

Auth-as-a-Service is a Laravel application that gives each client project its own isolated authentication space.

Instead of building auth separately for every app, the platform lets you create projects in a Filament admin panel, assign each project an API key, configure that project's auth rules and mail delivery, and then use a single project-scoped API for end-user registration, login, token refresh, OTP verification, password resets, and ghost-account invitations.

## What The Product Includes

- project-scoped user registration and login
- Laravel Sanctum access tokens plus database-backed refresh tokens
- optional email verification on registration
- OTP send, verify, and resend flows
- forgot-password and reset-password flows
- ghost-account creation and claim flows
- project-defined custom user fields with defaults, validation, and API visibility controls
- per-project auth settings
- per-project mail settings and email templates
- request logging and auth-event logging
- Filament admin UI for project operations

## How It Is Structured

There are two user layers in the application:

- platform users in the main `users` table use the Filament admin panel
- project users in `project_users` belong to a specific client project and use the public API

Each project has its own:

- API key
- auth settings
- mail settings
- email templates
- project users
- project user field definitions and stored custom-field values
- OTP records
- password reset records
- refresh tokens
- API request logs
- auth event logs

## Public API

The public API lives under:

```text
/api/v1/auth
```

Current endpoints:

- `POST /register`
- `POST /login`
- `POST /refresh`
- `POST /forgot-password`
- `POST /reset-password`
- `POST /send-otp`
- `POST /verify-otp`
- `POST /resend-otp`
- `POST /ghost-accounts`
- `POST /ghost-accounts/claim`
- `GET /me`
- `POST /logout`

All requests require:

```http
X-Project-Key: {project_api_key}
Accept: application/json
Content-Type: application/json
```

Authenticated endpoints also require:

```http
Authorization: Bearer {access_token}
```

## Current Auth Behavior

### Registration

- registration is unique per project by email
- if the email belongs to a pending unverified user in the same project, the record is retried instead of creating a duplicate
- if the email belongs to an active verified user in the same project, registration fails with `422`
- registration accepts an optional `custom_fields` object based on the active field definitions configured for that project
- active custom fields can enforce defaults, required rules, enum options, numeric/date constraints, and per-project uniqueness
- only custom fields marked as API-visible are returned in auth responses and `GET /me`
- if email verification is enabled, registration returns `202 Accepted` and no tokens
- if email verification is disabled, registration returns `201 Created` and issues tokens immediately

### Login And Refresh

- login requires an active, non-ghost account with a valid password
- pending email-verification users cannot log in
- access tokens are Sanctum personal access tokens
- refresh tokens are stored in the database, rotated on use, and revoked on logout or password reset

### OTP

Supported OTP purposes:

- `register_verify`
- `login_verify`
- `forgot_password`
- `ghost_account_claim`
- `email_verification`

OTP delivery is queued and uses project mail settings and email templates.

### Password Reset

- forgot-password generates a reset token and emails a reset link
- reset-password expects `email`, `token`, `password`, and `password_confirmation`
- successful password resets revoke all active access and refresh tokens for the project user

### Ghost Accounts

- ghost accounts are invitation-style project users
- they can be pre-created by project admins through the API or the panel
- claiming a ghost account verifies the OTP, optionally sets a password, converts the record into a normal account, and returns tokens

## Admin Panel

The Filament admin panel is available at:

```text
/admin
```

Current admin resources include:

- Projects
- Project Users
- API Request Logs
- Auth Event Logs

Inside each project, the panel also exposes:

- integration details
- auth settings
- mail settings
- email templates
- project user schema management

## Tech Stack

- PHP 8.3+
- Laravel 13
- Filament 4
- Livewire 3
- Laravel Sanctum 4
- Tailwind CSS 4
- Pest 4
- Scramble for OpenAPI generation support

## Local Setup

1. Install dependencies.

```bash
composer install
npm install
```

2. Create the environment file and generate an app key.

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure the database in `.env` and run migrations.

```bash
php artisan migrate
```

4. Seed a local panel user if you want sample access.

```bash
php artisan db:seed
```

The default seeded panel user is:

- email: `test@example.com`
- password: `password`

5. Start the app for local development.

```bash
composer run dev
```

That command starts:

- the Laravel server
- the queue worker
- Laravel Pail
- the Vite dev server

Keep the queue worker running when testing OTP and email flows because mail delivery is queued.

## Testing

Run the test suite with:

```bash
php artisan test --compact
```

## Documentation

- API reference: [`API.md`](./API.md)
- Project overview: [`PROJECT_OVERVIEW.md`](./PROJECT_OVERVIEW.md)
- OpenAPI document: [`api.json`](./api.json)
