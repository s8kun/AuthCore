# Auth-as-a-Service API Reference

This document describes the current public auth API implemented by the application.

Other API surfaces:

- generated docs UI: `/docs/api`
- generated OpenAPI JSON: `/docs/api.json`
- repository contract file: [`api.json`](./api.json)

## Base Path

```text
/api/v1/auth
```

## Authentication Model

This API is project-scoped.

Project resolution uses:

```http
X-Project-Key: {project_api_key}
```

Protected routes also require:

```http
Authorization: Bearer {access_token}
```

The current public routes do not use JWT.

They use:

- Sanctum personal access tokens for access tokens
- custom hashed refresh tokens stored in `refresh_tokens`

Important current note:

- `api_secret` is stored on projects but is not required by the public routes below

## Default Headers

Use these headers for JSON requests:

```http
Accept: application/json
Content-Type: application/json
X-Project-Key: {project_api_key}
```

Add the bearer token header for `GET /me` and `POST /logout`.

## Shared Behaviors

### Project Scoping

- every request resolves a project from `X-Project-Key`
- inactive projects are rejected with `403`
- tokens cannot be reused across projects
- the same email can exist in different projects

### Custom Fields

Profile and business fields belong under `custom_fields`.

Example:

```json
{
  "email": "user@example.com",
  "password": "password",
  "password_confirmation": "password",
  "custom_fields": {
    "first_name": "Jane",
    "status": "approved"
  }
}
```

Current behavior:

- undefined custom field keys are rejected with `422`
- only active field definitions are validated
- only fields marked `show_in_api` appear in API responses
- default values are applied when configured
- unique custom fields are enforced within the current project

Legacy top-level fields are prohibited:

- `first_name`
- `last_name`
- `phone`

If a project needs them, define them in the project user schema and send them inside `custom_fields`.

### Standard Error Semantics

Common status codes across the API:

- `400 Bad Request`: missing `X-Project-Key`
- `401 Unauthorized`: invalid project key or missing / invalid bearer token
- `403 Forbidden`: project unavailable, token-project mismatch, inactive account, or pending verification on protected routes
- `422 Unprocessable Entity`: validation or business-rule failure
- `429 Too Many Requests`: route or feature-specific rate limit exceeded

## Shared Response Shapes

### Token Response

```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "plain-text-access-token",
    "refresh_token": "plain-text-refresh-token",
    "expires_at": "2026-04-10T12:00:00Z",
    "expires_in_seconds": 3600,
    "refresh_token_expires_at": "2026-05-10T12:00:00Z",
    "refresh_token_expires_in_seconds": 2592000,
    "user": {
      "id": "uuid",
      "project_id": "uuid",
      "email": "user@example.com",
      "custom_fields": {
        "first_name": "Jane",
        "status": "approved"
      },
      "email_verified_at": "2026-04-10T12:00:00Z",
      "last_login_at": "2026-04-10T12:05:00Z",
      "is_active": true,
      "is_ghost": false,
      "claimed_at": null,
      "invited_at": null,
      "ghost_source": null,
      "must_set_password": false,
      "must_verify_email": false,
      "created_at": "2026-04-10T12:00:00Z",
      "updated_at": "2026-04-10T12:05:00Z"
    }
  }
}
```

### Pending Registration Response

```json
{
  "data": {
    "message": "Registration successful. Verify your email to continue.",
    "verification_required": true,
    "verification_purpose": "email_verification",
    "user": {
      "id": "uuid",
      "project_id": "uuid",
      "email": "user@example.com",
      "custom_fields": {
        "first_name": "Jane"
      },
      "email_verified_at": null,
      "last_login_at": null,
      "is_active": true,
      "is_ghost": false,
      "claimed_at": null,
      "invited_at": null,
      "ghost_source": null,
      "must_set_password": false,
      "must_verify_email": true,
      "created_at": "2026-04-10T12:00:00Z",
      "updated_at": "2026-04-10T12:00:00Z"
    }
  }
}
```

### Current User Response

```json
{
  "data": {
    "id": "uuid",
    "project_id": "uuid",
    "email": "user@example.com",
    "custom_fields": {
      "first_name": "Jane",
      "status": "approved"
    },
    "email_verified_at": "2026-04-10T12:00:00Z",
    "last_login_at": "2026-04-10T12:05:00Z",
    "is_active": true,
    "is_ghost": false,
    "claimed_at": null,
    "invited_at": null,
    "ghost_source": null,
    "must_set_password": false,
    "must_verify_email": false,
    "created_at": "2026-04-10T12:00:00Z",
    "updated_at": "2026-04-10T12:05:00Z"
  }
}
```

### Generic Accepted Response

```json
{
  "data": {
    "message": "If the request can be processed, an email will be sent."
  }
}
```

### Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ]
  }
}
```

## OTP Purposes

- `register_verify`
- `login_verify`
- `forgot_password`
- `ghost_account_claim`
- `email_verification`

## Example Shell Variables

```bash
BASE_URL="http://127.0.0.1:8000/api/v1/auth"
PROJECT_KEY="your-project-key"
ACCESS_TOKEN="your-access-token"
REFRESH_TOKEN="your-refresh-token"
```

## Endpoints

### Register

`POST /register`

Creates or retries a project-scoped user registration.

Request body:

```json
{
  "email": "user@example.com",
  "password": "password",
  "password_confirmation": "password",
  "custom_fields": {
    "first_name": "Jane",
    "status": "approved"
  }
}
```

Example:

```bash
curl --request POST "$BASE_URL/register" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "user@example.com",
    "password": "password",
    "password_confirmation": "password",
    "custom_fields": {
      "first_name": "Jane",
      "status": "approved"
    }
  }'
```

Behavior notes:

- existing verified users in the same project are rejected
- existing pending users in the same project are retried on the same record
- if email verification is enabled, this returns `202` and no tokens
- if email verification is disabled, this returns `201` and a token pair

Responses:

- `201 Created`: token response
- `202 Accepted`: pending registration response
- `422`: validation failure, duplicate verified email, invalid custom fields, or prohibited legacy top-level profile fields

### Login

`POST /login`

Authenticates an active, non-ghost project user and returns a new token pair.

Request body:

```json
{
  "email": "user@example.com",
  "password": "password"
}
```

Example:

```bash
curl --request POST "$BASE_URL/login" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "user@example.com",
    "password": "password"
  }'
```

Behavior notes:

- email is normalized to lowercase before validation
- login is checked only inside the resolved project
- pending email-verification users are blocked until verification is completed

Responses:

- `200 OK`: token response
- `422`: invalid credentials or email verification still required

### Refresh Token

`POST /refresh`

Rotates a refresh token and returns a fresh access token pair.

Request body:

```json
{
  "refresh_token": "plain-text-refresh-token"
}
```

Example:

```bash
curl --request POST "$BASE_URL/refresh" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data "{
    \"refresh_token\": \"$REFRESH_TOKEN\"
  }"
```

Behavior notes:

- refresh tokens are rotated on success
- expired, revoked, reused, or cross-project refresh tokens are rejected
- refresh token reuse revokes all remaining tokens for that user

Responses:

- `200 OK`: token response
- `422`: invalid, expired, revoked, reused, or blocked refresh token

### Forgot Password

`POST /forgot-password`

Requests a password reset email. The response is intentionally generic.

Request body:

```json
{
  "email": "user@example.com"
}
```

Example:

```bash
curl --request POST "$BASE_URL/forgot-password" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "user@example.com"
  }'
```

Behavior notes:

- per-project feature flag: `forgot_password_enabled`
- per-project, per-email hourly rate limit applies
- unknown users still return the generic accepted response

Responses:

- `202 Accepted`: generic accepted response
- `422`: validation failure or forgot-password disabled
- `429`: too many forgot-password requests

### Reset Password

`POST /reset-password`

Completes a password reset using the token that was delivered by email.

Request body:

```json
{
  "email": "user@example.com",
  "token": "password-reset-token",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

Example:

```bash
curl --request POST "$BASE_URL/reset-password" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "user@example.com",
    "token": "password-reset-token",
    "password": "new-password",
    "password_confirmation": "new-password"
  }'
```

Behavior notes:

- successful resets revoke active access and refresh tokens for the user
- a password reset success email is queued

Responses:

- `200 OK`: success message
- `422`: validation failure or invalid / expired token

### Send OTP

`POST /send-otp`

Issues an OTP for a project-scoped email and purpose.

Request body:

```json
{
  "email": "user@example.com",
  "purpose": "login_verify"
}
```

Example:

```bash
curl --request POST "$BASE_URL/send-otp" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "user@example.com",
    "purpose": "login_verify"
  }'
```

Behavior notes:

- per-project feature flag: `otp_enabled`
- per-email and per-purpose daily limits apply
- an OTP resend cooldown is enforced

Responses:

- `202 Accepted`: generic accepted response
- `422`: validation failure, OTP disabled, or resend cooldown still active
- `429`: too many OTP requests

### Verify OTP

`POST /verify-otp`

Verifies the latest OTP for the given project, email, and purpose.

Request body:

```json
{
  "email": "user@example.com",
  "purpose": "login_verify",
  "otp_code": "123456"
}
```

Example:

```bash
curl --request POST "$BASE_URL/verify-otp" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "user@example.com",
    "purpose": "login_verify",
    "otp_code": "123456"
  }'
```

Special case:

- verifying `email_verification` completes the pending project user's email verification flow

Responses:

- `200 OK`: verification success
- `422`: validation failure or invalid / expired OTP

### Resend OTP

`POST /resend-otp`

Resends an OTP for the given email and purpose.

Request body:

```json
{
  "email": "user@example.com",
  "purpose": "login_verify"
}
```

Example:

```bash
curl --request POST "$BASE_URL/resend-otp" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "user@example.com",
    "purpose": "login_verify"
  }'
```

Responses:

- `202 Accepted`: generic accepted response
- `422`: validation failure, OTP disabled, or resend cooldown still active
- `429`: too many OTP requests

### Create Ghost Account

`POST /ghost-accounts`

Creates or updates an invitation-style ghost account inside the current project.

Request body:

```json
{
  "email": "ghost@example.com",
  "ghost_source": "api",
  "must_set_password": true,
  "must_verify_email": false,
  "send_invite": true,
  "custom_fields": {
    "first_name": "Ghost"
  }
}
```

Example:

```bash
curl --request POST "$BASE_URL/ghost-accounts" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "ghost@example.com",
    "ghost_source": "api",
    "must_set_password": true,
    "must_verify_email": false,
    "send_invite": true,
    "custom_fields": {
      "first_name": "Ghost"
    }
  }'
```

Behavior notes:

- ghost accounts must be enabled for the project
- top-level legacy profile fields are prohibited here too
- if `send_invite` is true, a claim OTP email is queued

Responses:

- `201 Created`: project user response
- `422`: validation failure, ghost accounts disabled, active account collision, or invalid custom fields

### Claim Ghost Account

`POST /ghost-accounts/claim`

Claims an invited ghost account, optionally sets a password, and returns a token pair.

Request body:

```json
{
  "email": "ghost@example.com",
  "otp_code": "123456",
  "password": "new-password",
  "password_confirmation": "new-password",
  "custom_fields": {
    "first_name": "Claimed"
  }
}
```

Example:

```bash
curl --request POST "$BASE_URL/ghost-accounts/claim" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --data '{
    "email": "ghost@example.com",
    "otp_code": "123456",
    "password": "new-password",
    "password_confirmation": "new-password",
    "custom_fields": {
      "first_name": "Claimed"
    }
  }'
```

Behavior notes:

- ghost accounts must be enabled for the project
- if the invited user requires a password, omitting `password` returns `422`
- successful claims convert the account from ghost to normal and mark it verified

Responses:

- `200 OK`: token response
- `422`: validation failure, ghost accounts disabled, missing invitation, invalid OTP, or missing required password

### Current User

`GET /me`

Returns the authenticated project user for the current project.

Example:

```bash
curl --request GET "$BASE_URL/me" \
  --header "Accept: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --header "Authorization: Bearer $ACCESS_TOKEN"
```

Behavior notes:

- the token must belong to the project identified by `X-Project-Key`
- pending email-verification users are blocked from this route

Responses:

- `200 OK`: current user response
- `401`: unauthenticated
- `403`: token does not belong to the project, account inactive, or email verification still required

### Logout

`POST /logout`

Revokes the current access token and all active refresh tokens for the authenticated project user.

Example:

```bash
curl --request POST "$BASE_URL/logout" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: $PROJECT_KEY" \
  --header "Authorization: Bearer $ACCESS_TOKEN"
```

Success response:

```json
{
  "data": {
    "message": "Logged out successfully."
  }
}
```

Responses:

- `200 OK`: logout success
- `401`: unauthenticated

## Current Non-Goals / Not Yet Exposed

These settings exist in the data model or panel, but are not yet part of the public route surface:

- project `api_secret` as a request credential
- magic-link authentication endpoints
