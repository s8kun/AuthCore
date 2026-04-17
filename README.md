# Auth-as-a-Service

Auth-as-a-Service is a Laravel-based authentication platform that gives each client project its own isolated auth space.

Instead of rebuilding auth for every app, the platform lets a panel user create projects, configure project-specific auth and mail behavior, define custom user fields, and expose a single project-scoped API for registration, login, token refresh, OTP verification, password resets, and ghost-account invitations.

## What This Project Does

- project-scoped registration and login
- Laravel Sanctum access tokens with custom database-backed refresh tokens
- optional email verification during registration
- project-scoped OTP send, verify, and resend flows
- forgot-password and reset-password flows
- ghost-account create and claim flows
- custom project user schemas with API visibility and uniqueness rules
- per-project auth settings, mail settings, and email templates
- API request logging and auth event logging
- Filament admin panel with project docs and API reference pages

## Identity Model

There are two user layers:

- `users`: platform owners who sign in to the Filament admin panel
- `project_users`: end users who belong to a specific project and authenticate through the public API

Projects are the tenancy boundary. The same email can exist in multiple projects, but tokens are always restricted to the project that issued them.

Profile-style fields such as `first_name`, `last_name`, and `phone` are no longer built in on `project_users`. If a project needs them, they must be defined in the custom project user schema and sent under `custom_fields`.

## Public Surfaces

- `/` redirects to `/admin`
- `/admin` is the Filament control plane
- `/api/v1/auth` is the public project-scoped auth API
- `/docs/api` is the generated Scramble API UI
- `/docs/api.json` is the generated Scramble OpenAPI document
- [`API.md`](./API.md) is the hand-written API guide
- [`PROJECT_OVERVIEW.md`](./PROJECT_OVERVIEW.md) is the architecture and product overview

## Project Credential Model

Today, public API requests identify the project with:

```http
X-Project-Key: {project_api_key}
```

Protected routes also require:

```http
Authorization: Bearer {access_token}
```

The `projects` table also stores an `api_secret`, but it is not part of the current public auth flow yet. Likewise, `magic_link_enabled` exists in project auth settings, but there are no public magic-link endpoints in the current route set.

## Main Auth Behavior

### Registration

- registration is scoped to the current project
- existing verified users in the same project are rejected
- existing pending users in the same project are retried on the same record
- if email verification is enabled, registration returns `202 Accepted` and no tokens
- if email verification is disabled, registration returns `201 Created` and issues tokens immediately

### Login And Sessions

- login requires an active, non-ghost project user with a valid password
- pending email-verification users cannot log in
- access tokens are Sanctum personal access tokens
- refresh tokens are stored separately, hashed, rotated on use, and revoked on logout or password reset

### Custom Fields

- project-specific fields are managed from the panel under Project User Schema
- request payloads use `custom_fields`
- undefined custom field keys are rejected
- only fields marked `show_in_api` appear in API responses
- defaults and uniqueness rules are enforced per project

### OTP, Password Reset, And Ghost Accounts

- OTP purposes: `register_verify`, `login_verify`, `forgot_password`, `ghost_account_claim`, `email_verification`
- OTP emails are queued and use project mail settings and templates
- password resets revoke active access and refresh tokens after success
- ghost accounts are optional and project-scoped

## Admin Panel

The Filament panel currently exposes:

- Dashboard widgets for platform overview and feature adoption
- Projects
- Project Users
- API Request Logs
- Auth Event Logs
- project-specific pages for:
  - integration details
  - API reference
  - auth settings
  - mail settings
  - email templates
  - project user schema
  - global project docs index

## Tech Stack

- PHP `^8.3`
- Laravel `13`
- Filament `4`
- Livewire `3`
- Laravel Sanctum `4`
- Tailwind CSS `4`
- Pest `4`
- Scramble for generated API documentation

## Local Setup

### Fastest Setup

```bash
composer run setup
php artisan db:seed --no-interaction
composer run dev
```

That setup script installs Composer dependencies, copies `.env` when needed, generates the app key, runs migrations, installs frontend packages, and builds assets.

### Manual Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan db:seed --no-interaction
composer run dev
```

The seeded panel user is:

- email: `test@example.com`
- password: `password`

Keep the queue worker running while testing OTP, forgot-password, welcome-email, or ghost-invite flows because mail delivery is queued.

## Testing

Run the suite with:

```bash
php artisan test --compact
```

## Documentation

- Product and architecture overview: [`PROJECT_OVERVIEW.md`](./PROJECT_OVERVIEW.md)
- Public API guide: [`API.md`](./API.md)
- Single-project Store App guide: [`STORE_APP_INTEGRATION.md`](./STORE_APP_INTEGRATION.md)
- Next.js Store App guide: [`STORE_APP_NEXTJS_INTEGRATION.md`](./STORE_APP_NEXTJS_INTEGRATION.md)
- Laravel Store App guide: [`STORE_APP_LARAVEL_INTEGRATION.md`](./STORE_APP_LARAVEL_INTEGRATION.md)
- Static contract file: [`api.json`](./api.json)
- Generated docs UI: `/docs/api`
- Generated docs JSON: `/docs/api.json`
