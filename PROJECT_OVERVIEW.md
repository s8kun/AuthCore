# Project Overview

## Product Summary

This project is a multi-project authentication platform built with Laravel, Filament, Sanctum, and Livewire.

Its job is to let a platform owner create separate client projects, give each project its own API key and authentication rules, and expose one project-scoped API for end-user identity workflows.

The current public surface is focused on auth:

- registration
- login
- current-user lookup
- logout
- refresh-token rotation
- OTP send, verify, and resend
- password reset request and completion
- ghost-account invitation and claim
- project-level custom user field definitions with API and admin visibility controls

## Core Actors

### Platform Users

Platform users live in the default `users` table.

They:

- sign in to the Filament admin panel
- own projects
- manage settings, templates, users, and logs

### Projects

A project is the tenant boundary for the public API.

Each project has:

- a UUID primary key
- `name`, `slug`, `api_key`, and `api_secret`
- a `status`
- a per-project `rate_limit`
- one auth-settings record
- one mail-settings record
- many email templates
- many project users
- many project user field definitions and field values
- many OTPs, password resets, refresh tokens, and logs

Projects automatically bootstrap default auth settings, mail settings, and email templates on creation.

### Project Users

Project users are the end users of a client project and live in `project_users`.

Important fields include:

- `email`
- `password`
- `first_name`
- `last_name`
- `phone`
- `email_verified_at`
- `last_login_at`
- `is_active`
- `is_ghost`
- `claimed_at`
- `invited_at`
- `ghost_source`
- `must_set_password`
- `must_verify_email`

The uniqueness boundary is project + email, not global email across the whole platform.

## Main Data Model

The current database uses UUIDs for all project-scoped auth tables.

Key tables:

- `projects`
- `project_users`
- `project_user_fields`
- `project_user_field_values`
- `project_auth_settings`
- `project_mail_settings`
- `project_email_templates`
- `project_otps`
- `project_password_resets`
- `refresh_tokens`
- `api_request_logs`
- `auth_event_logs`
- `personal_access_tokens`
- `users`

### Project Auth Settings

Each project has one `project_auth_settings` row that currently controls:

- access-token TTL in minutes
- refresh-token TTL in days
- OTP enablement, length, TTL, max attempts, resend cooldown, and daily cap
- forgot-password enablement and per-hour limit
- reset-password TTL
- email-verification enablement
- ghost-account enablement
- maximum ghost accounts per email
- login identifier mode
- auth mode

Default values currently include:

- access token TTL: `60` minutes
- refresh token TTL: `30` days
- OTP enabled: `true`
- OTP length: `6`
- OTP TTL: `10` minutes
- forgot-password enabled: `true`
- email verification enabled: `false`
- ghost accounts enabled: `false`

### Project Mail Settings

Each project has one `project_mail_settings` row.

Mail configuration supports:

- platform mail mode
- custom SMTP mode
- sender identity fields
- encrypted SMTP password storage
- verification status and last tested timestamp

### Email Templates

Each project has many `project_email_templates`.

Current template types:

- `otp`
- `forgot_password`
- `reset_password_success`
- `welcome`
- `email_verification`
- `ghost_account_invite`

### Project User Fields

Each project can define a custom schema for its project users through `project_user_fields`.

Field definitions currently support:

- a stable `key` and human `label`
- field types such as `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `enum`, `email`, `url`, `phone`, `uuid`, and `json`
- optional default values
- optional enum options
- required and unique constraints
- API visibility, admin-form visibility, and table visibility toggles
- active or disabled state
- sort ordering

Submitted values are stored in `project_user_field_values` and normalized by type. API responses only expose fields marked as `show_in_api`.

## Public API Surface

The public API is mounted at:

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

Every request must include:

```http
X-Project-Key: {project_api_key}
```

The `me` and `logout` endpoints also require a Sanctum bearer token.

## Request Pipeline

Every public auth route passes through:

1. `ResolveProjectFromApiKey`
2. `LogProjectApiRequest`
3. `throttle:project-auth`

Authenticated routes then add:

4. `auth:sanctum`
5. `EnsureProjectAccessMatchesToken`

### What The Middleware Enforces

`ResolveProjectFromApiKey`:

- requires the `X-Project-Key` header
- resolves the project from `projects.api_key`
- blocks inactive projects

`EnsureProjectAccessMatchesToken`:

- rejects missing or invalid bearer tokens
- rejects tokens from another project
- rejects inactive project users
- rejects users still pending email verification

## Authentication Model

This project does not use JWTs for the public API.

Instead it uses:

- Sanctum personal access tokens for short-lived access tokens
- custom `refresh_tokens` rows for long-lived refresh tokens

Refresh tokens are:

- hashed in storage
- scoped to both project and project user
- rotated on successful use
- revoked on logout
- revoked on password reset
- fully revoked if token reuse is detected

## Registration Flow

Registration is project-scoped and handled by `ProjectAuthService`.

Current behavior:

- a brand-new email creates a new `project_users` record
- an existing pending unverified user is retried on the same record
- an existing ghost account blocks normal registration
- an existing verified account returns a `422` validation error
- the request can include a `custom_fields` object keyed by the project's active field definitions
- undefined custom field keys are rejected
- defaults are applied when a field is omitted and a default exists
- project-scoped uniqueness is enforced for custom fields that enable it

When email verification is enabled for the project:

- registration returns `202 Accepted`
- no tokens are returned
- an email-verification OTP is queued
- old tokens for that pending user are revoked
- the user must complete `verify-otp` and then call `login`

When email verification is disabled:

- registration returns `201 Created`
- the account is considered active immediately
- a token pair is issued
- a welcome email is queued

## Login And Session Flow

Login succeeds only when the project user:

- exists in the current project
- is active
- is not a ghost account
- has a password
- passes password verification
- is not pending email verification

On successful login:

- `last_login_at` is updated
- a new access token is issued
- a new refresh token is created
- an auth event is logged

## OTP System

The OTP system is project-scoped and purpose-scoped.

Current purposes:

- `register_verify`
- `login_verify`
- `forgot_password`
- `ghost_account_claim`
- `email_verification`

Each OTP record stores:

- hashed code
- email
- purpose
- optional `project_user_id`
- expiry timestamp
- attempt counters
- resend counters
- last sent timestamp
- consumed timestamp
- metadata

Delivery behavior:

- OTP emails are queued
- email template selection depends on purpose
- resend cooldown is enforced
- daily per-email limits are enforced

Verification behavior:

- only the latest usable OTP for project + email + purpose is considered
- failed verification increments attempt count
- successful verification marks the OTP as consumed
- verifying `email_verification` also activates the user through `completeEmailVerification`

## Password Reset Flow

The password reset system is token-based, not OTP-submission-based.

Request flow:

1. validate the request against the current project
2. enforce the project's forgot-password hourly limit
3. find the active non-ghost project user
4. create a new password-reset record with a hashed reset token
5. queue a reset email with a reset link

Reset completion flow:

1. locate the project user by project + email
2. match the hashed reset token
3. ensure the reset record is still usable
4. update the password
5. mark the reset record as used
6. revoke all access and refresh tokens
7. queue the reset-success email

## Ghost Accounts

Ghost accounts are invitation-style end-user records created inside a project.

Creation flow:

- requires `ghost_accounts_enabled`
- creates or updates a `project_users` record with `is_ghost = true`
- sets invite metadata and requirements such as `must_set_password`
- optionally sends a `ghost_account_claim` OTP

Claim flow:

- requires `ghost_accounts_enabled`
- verifies the claim OTP
- optionally requires a password depending on account settings
- fills any provided profile fields
- marks the account as claimed and email-verified
- flips `is_ghost` to `false`
- clears `must_set_password` and `must_verify_email`
- returns a normal token pair

## Mail And Template System

Project email delivery is handled through `ProjectMailService`.

Projects can use:

- platform mail settings
- custom SMTP settings

The admin panel lets owners:

- edit sender identity
- configure SMTP credentials
- send test emails
- verify delivery settings
- manage template subjects and bodies

This is what powers OTP, password reset, welcome, verification, and ghost-account invitation emails.

## Observability

### API Request Logs

Every public auth request can be logged to `api_request_logs` with fields such as:

- endpoint
- route name
- HTTP method
- submitted email when available
- IP address
- user agent
- status code
- success flag
- metadata

### Auth Event Logs

Auth workflows write structured entries to `auth_event_logs`.

Current event types include:

- `registration_succeeded`
- `registration_failed`
- `login_succeeded`
- `login_failed`
- `logout_succeeded`
- `refresh_rotated`
- `otp_sent`
- `otp_resent`
- `otp_verified`
- `otp_failed`
- `password_reset_requested`
- `password_reset_completed`
- `ghost_account_created`
- `ghost_account_claimed`
- `verification_sent`
- `verification_completed`

## Rate Limiting

There are two main layers of throttling.

### Route-Level Throttling

The `project-auth` limiter:

- keys by project + IP + route
- uses the project's `rate_limit`
- falls back to `60` requests per minute when no project is resolved

### Service-Level Throttling

Service classes also apply narrower flow-specific limits for:

- OTP daily send volume
- OTP resend cooldowns
- forgot-password requests per hour

## Filament Admin Experience

The current Filament resources are:

- Projects
- Project Users
- API Request Logs
- Auth Event Logs

The project resource includes sub-pages for:

- core project editing
- integration details
- auth settings
- mail settings
- email templates
- project user schema

This means the product is not just an API backend. It is already structured as an operator-facing auth platform with an admin control plane.

## Development Notes

Important runtime expectations:

- queued emails require a running queue worker
- local development is easiest through `composer run dev`
- the default seeded admin-panel user is `test@example.com`

Supporting docs:

- API reference: [`API.md`](./API.md)
- OpenAPI contract: [`api.json`](./api.json)
- main project readme: [`README.md`](./README.md)
