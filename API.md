# Auth-as-a-Service API Reference

This document describes the current public API implemented by the project. The machine-readable contract lives in [`api.json`](./api.json).

## Base URL

```text
/api/v1/auth
```

Local example:

```text
http://127.0.0.1:8000/api/v1/auth
```

## Required Headers

All endpoints require:

```http
X-Project-Key: {project_api_key}
Accept: application/json
Content-Type: application/json
```

Authenticated endpoints also require:

```http
Authorization: Bearer {access_token}
```

## Shared Response Shapes

### Token Response

```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "plain-text-access-token",
    "refresh_token": "plain-text-refresh-token",
    "expires_at": "2026-04-06T13:00:00Z",
    "expires_in_seconds": 3600,
    "refresh_token_expires_at": "2026-05-06T12:00:00Z",
    "refresh_token_expires_in_seconds": 2592000,
    "user": {
      "id": "uuid",
      "project_id": "uuid",
      "email": "user@example.com",
      "first_name": "Jane",
      "last_name": "Doe",
      "phone": "+12025550123",
      "custom_fields": {
        "status": "approved",
        "employee_number": "EMP-100"
      },
      "email_verified_at": "2026-04-06T12:00:00Z",
      "last_login_at": null,
      "is_active": true,
      "is_ghost": false,
      "claimed_at": null,
      "invited_at": null,
      "ghost_source": null,
      "must_set_password": false,
      "must_verify_email": false,
      "created_at": "2026-04-06T12:00:00Z",
      "updated_at": "2026-04-06T12:00:00Z"
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
      "first_name": "Jane",
      "last_name": "Doe",
      "phone": "+12025550123",
      "custom_fields": {
        "status": "pending"
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
      "created_at": "2026-04-06T12:00:00Z",
      "updated_at": "2026-04-06T12:00:00Z"
    }
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

## Custom Fields

Registration can include an optional `custom_fields` object. The available keys come from the active project user field schema configured in the admin panel.

Current field types supported by the schema system:

- `string`
- `text`
- `integer`
- `decimal`
- `boolean`
- `date`
- `datetime`
- `enum`
- `email`
- `url`
- `phone`
- `uuid`
- `json`

Current API behavior:

- undefined custom field keys are rejected with `422`
- inactive field definitions are ignored by validation and response serialization
- required fields are enforced unless a default value exists
- unique fields are enforced within the current project
- only fields marked as API-visible are returned in `user.custom_fields`
- defaults are included in the response even when the client does not submit a value

## Endpoints

### Register

`POST /register`

Creates a project-scoped user. If email verification is enabled for the project, registration returns `202 Accepted` and the client must complete verification before login.

Request body:

```json
{
  "email": "user@example.com",
  "first_name": "Jane",
  "last_name": "Doe",
  "phone": "+12025550123",
  "custom_fields": {
    "status": "approved",
    "employee_number": "EMP-100"
  },
  "password": "password",
  "password_confirmation": "password"
}
```

Example:

```bash
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/register' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "user@example.com",
    "first_name": "Jane",
    "last_name": "Doe",
    "phone": "+12025550123",
    "custom_fields": {
      "status": "approved",
      "employee_number": "EMP-100"
    },
    "password": "password",
    "password_confirmation": "password"
  }'
```

Responses:

- `201 Created`: token response
- `202 Accepted`: pending registration response
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: validation failure, duplicate verified email, invalid custom fields, or custom-field uniqueness conflict
- `429`: rate limited

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
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/login' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "user@example.com",
    "password": "password"
  }'
```

Responses:

- `200 OK`: token response
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: invalid credentials or verification still required
- `429`: rate limited

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
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/refresh' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "refresh_token": "plain-text-refresh-token"
  }'
```

Responses:

- `200 OK`: token response
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: invalid, expired, revoked, or blocked refresh token
- `429`: rate limited

### Forgot Password

`POST /forgot-password`

Requests a password reset link email. The response is intentionally generic.

Request body:

```json
{
  "email": "user@example.com"
}
```

Example:

```bash
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/forgot-password' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "user@example.com"
  }'
```

Responses:

- `202 Accepted`: generic accepted response
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: validation failure or feature disabled
- `429`: rate limited

### Reset Password

`POST /reset-password`

Completes a password reset using the reset token from the emailed reset link.

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
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/reset-password' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "user@example.com",
    "token": "password-reset-token",
    "password": "new-password",
    "password_confirmation": "new-password"
  }'
```

Success response:

```json
{
  "data": {
    "message": "Password reset successfully."
  }
}
```

Responses:

- `200 OK`: reset completed
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: invalid token, expired token, or validation failure
- `429`: rate limited

### Send OTP

`POST /send-otp`

Sends an OTP for the requested purpose.

Request body:

```json
{
  "email": "user@example.com",
  "purpose": "email_verification"
}
```

Example:

```bash
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/send-otp' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "user@example.com",
    "purpose": "email_verification"
  }'
```

Responses:

- `202 Accepted`: generic accepted response
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: validation failure, cooldown hit, or OTP disabled
- `429`: rate limited

### Verify OTP

`POST /verify-otp`

Verifies an OTP code for an email and purpose. When the purpose is `email_verification`, this activates the user and completes email verification.

Request body:

```json
{
  "email": "user@example.com",
  "purpose": "email_verification",
  "otp_code": "123456"
}
```

Example:

```bash
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/verify-otp' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "user@example.com",
    "purpose": "email_verification",
    "otp_code": "123456"
  }'
```

Success response:

```json
{
  "data": {
    "verified": true,
    "message": "OTP verified successfully."
  }
}
```

Responses:

- `200 OK`: OTP verified
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: invalid or expired OTP, or validation failure
- `429`: rate limited

### Resend OTP

`POST /resend-otp`

Resends an OTP for the requested purpose.

Request body:

```json
{
  "email": "user@example.com",
  "purpose": "email_verification"
}
```

Example:

```bash
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/resend-otp' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "user@example.com",
    "purpose": "email_verification"
  }'
```

Responses:

- `202 Accepted`: generic accepted response
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: validation failure or OTP disabled
- `429`: rate limited

### Create Ghost Account

`POST /ghost-accounts`

Creates or updates a ghost account inside the current project. This flow is only available when ghost accounts are enabled in project auth settings.

Request body:

```json
{
  "email": "invitee@example.com",
  "first_name": "Jane",
  "last_name": "Doe",
  "phone": "+12025550123",
  "ghost_source": "api",
  "must_set_password": true,
  "must_verify_email": false,
  "send_invite": true
}
```

Example:

```bash
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/ghost-accounts' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "invitee@example.com",
    "first_name": "Jane",
    "last_name": "Doe",
    "phone": "+12025550123",
    "ghost_source": "api",
    "must_set_password": true,
    "must_verify_email": false,
    "send_invite": true
  }'
```

Responses:

- `201 Created`: returns a `ProjectUser` payload
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: validation failure, feature disabled, or active account already exists
- `429`: rate limited

### Claim Ghost Account

`POST /ghost-accounts/claim`

Claims a ghost account using the `ghost_account_claim` OTP and returns normal auth tokens.

Request body:

```json
{
  "email": "invitee@example.com",
  "otp_code": "123456",
  "password": "password",
  "password_confirmation": "password",
  "first_name": "Jane",
  "last_name": "Doe",
  "phone": "+12025550123"
}
```

Example:

```bash
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/ghost-accounts/claim' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --data '{
    "email": "invitee@example.com",
    "otp_code": "123456",
    "password": "password",
    "password_confirmation": "password",
    "first_name": "Jane",
    "last_name": "Doe",
    "phone": "+12025550123"
  }'
```

Responses:

- `200 OK`: token response
- `400`: missing `X-Project-Key`
- `401`: invalid project key
- `403`: project unavailable
- `422`: validation failure, feature disabled, OTP invalid, or password required
- `429`: rate limited

### Current User

`GET /me`

Returns the authenticated project user.

Example:

```bash
curl --request GET 'http://127.0.0.1:8000/api/v1/auth/me' \
  --header 'Accept: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --header 'Authorization: Bearer your-access-token'
```

Responses:

- `200 OK`: returns the current `ProjectUser`
- `400`: missing `X-Project-Key`
- `401`: unauthenticated
- `403`: wrong project token, inactive account, or pending verification
- `429`: rate limited

Example response:

```json
{
  "data": {
    "id": "uuid",
    "project_id": "uuid",
    "email": "user@example.com",
    "first_name": "Jane",
    "last_name": "Doe",
    "phone": "+12025550123",
    "custom_fields": {
      "status": "approved",
      "employee_number": "EMP-100"
    },
    "email_verified_at": "2026-04-06T12:00:00Z",
    "last_login_at": "2026-04-06T12:15:00Z",
    "is_active": true,
    "is_ghost": false,
    "claimed_at": null,
    "invited_at": null,
    "ghost_source": null,
    "must_set_password": false,
    "must_verify_email": false,
    "created_at": "2026-04-06T12:00:00Z",
    "updated_at": "2026-04-06T12:15:00Z"
  }
}
```

### Logout

`POST /logout`

Revokes the current access token and all active refresh tokens for that project user.

Example:

```bash
curl --request POST 'http://127.0.0.1:8000/api/v1/auth/logout' \
  --header 'Accept: application/json' \
  --header 'X-Project-Key: your-project-key' \
  --header 'Authorization: Bearer your-access-token'
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

- `200 OK`: logout completed
- `400`: missing `X-Project-Key`
- `401`: unauthenticated
- `403`: wrong project token, inactive account, or pending verification
- `429`: rate limited
