# Auth-as-a-Service API Documentation

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

All API requests require the following headers:

### Required Headers

```http
X-Project-Key: {project_api_key}
Content-Type: application/json
Accept: application/json
```

### Authentication Token

For authenticated endpoints:

```http
Authorization: Bearer {access_token}
```

## API Endpoints

### 1. User Registration

Register a new user for the project.

**Endpoint:** `POST /auth/register`

**Request Body:**

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
}
```

**Response (201 Created):**

```json
{
    "message": "Registration successful",
    "data": {
        "user": {
            "id": "uuid",
            "name": "John Doe",
            "email": "john@example.com",
            "email_verified_at": null,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
    }
}
```

**Notes:**

- Email verification may be required depending on project settings
- Password must be at least 8 characters
- Email must be unique within the project

---

### 2. User Login

Authenticate a user and obtain tokens.

**Endpoint:** `POST /auth/login`

**Request Body:**

```json
{
    "email": "john@example.com",
    "password": "secret123"
}
```

**Response (200 OK):**

```json
{
    "message": "Login successful",
    "data": {
        "user": {
            "id": "uuid",
            "name": "John Doe",
            "email": "john@example.com",
            "email_verified_at": "2024-01-01T00:00:00.000000Z",
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
    }
}
```

**Error Responses:**

- `401 Unauthorized`: Invalid credentials
- `403 Forbidden`: Account disabled or suspended
- `422 Unprocessable Entity`: Validation errors

---

### 3. Get Current User

Get authenticated user details.

**Endpoint:** `GET /auth/me`

**Headers:**

```http
Authorization: Bearer {access_token}
```

**Response (200 OK):**

```json
{
    "data": {
        "id": "uuid",
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2024-01-01T00:00:00.000000Z",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z",
        "project_id": "project-uuid"
    }
}
```

---

### 4. Logout

Revoke the current access token.

**Endpoint:** `POST /auth/logout`

**Headers:**

```http
Authorization: Bearer {access_token}
```

**Response (200 OK):**

```json
{
    "message": "Successfully logged out"
}
```

**Notes:**

- Invalidates the current access token
- All refresh tokens for the user remain valid

---

### 5. Refresh Token

Get a new access token using a refresh token.

**Endpoint:** `POST /auth/refresh`

**Request Body:**

```json
{
    "refresh_token": "{refresh_token}"
}
```

**Response (200 OK):**

```json
{
    "message": "Token refreshed successfully",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
    }
}
```

**Notes:**

- Refresh tokens rotate (old one becomes invalid)
- Refresh tokens expire after 30 days

---

### 6. Forgot Password

Request a password reset email.

**Endpoint:** `POST /auth/forgot-password`

**Request Body:**

```json
{
    "email": "john@example.com"
}
```

**Response (200 OK):**

```json
{
    "message": "Password reset email sent"
}
```

**Notes:**

- Sends an email with OTP for password reset
- OTP validity: 10 minutes

---

### 7. Reset Password

Complete password reset with OTP.

**Endpoint:** `POST /auth/reset-password`

**Request Body:**

```json
{
    "email": "john@example.com",
    "password": "newpassword123",
    "password_confirmation": "newpassword123",
    "otp": "123456"
}
```

**Response (200 OK):**

```json
{
    "message": "Password reset successful",
    "data": {
        "user": {
            "id": "uuid",
            "name": "John Doe",
            "email": "john@example.com"
        }
    }
}
```

---

### 8. Send OTP

Send a one-time password for various purposes.

**Endpoint:** `POST /auth/send-otp`

**Request Body:**

```json
{
    "email": "john@example.com",
    "purpose": "email_verification"
}
```

**Available Purposes:**

- `email_verification`: Verify email address
- `login_verification`: 2-factor authentication for login
- `forgot_password`: Password reset
- `ghost_account_claim`: Claim ghost account
- `register_verify`: Verify registration

**Response (200 OK):**

```json
{
    "message": "OTP sent successfully",
    "data": {
        "purpose": "email_verification",
        "expires_at": "2024-01-01T00:10:00.000000Z"
    }
}
```

---

### 9. Verify OTP

Verify a one-time password.

**Endpoint:** `POST /auth/verify-otp`

**Request Body:**

```json
{
    "email": "john@example.com",
    "otp": "123456",
    "purpose": "email_verification"
}
```

**Response (200 OK):**

```json
{
    "message": "OTP verified successfully",
    "data": {
        "verified": true,
        "purpose": "email_verification"
    }
}
```

**Additional Data (for ghost_account_claim):**

```json
{
    "message": "OTP verified successfully",
    "data": {
        "verified": true,
        "purpose": "ghost_account_claim",
        "user": {
            "id": "uuid",
            "name": null,
            "email": "john@example.com",
            "is_ghost": true
        }
    }
}
```

---

### 10. Resend OTP

Resend a one-time password.

**Endpoint:** `POST /auth/resend-otp`

**Request Body:**

```json
{
    "email": "john@example.com",
    "purpose": "email_verification"
}
```

**Response (200 OK):**

```json
{
    "message": "OTP resent successfully",
    "data": {
        "purpose": "email_verification",
        "expires_at": "2024-01-01T00:10:00.000000Z"
    }
}
```

**Rate Limit:** 3 requests per 10 minutes per email

---

### 11. Create Ghost Accounts

Pre-register users who will claim accounts later.

**Endpoint:** `POST /auth/ghost-accounts`

**Request Body:**

```json
{
    "emails": ["user1@example.com", "user2@example.com"]
}
```

**Response (201 Created):**

```json
{
    "message": "Ghost accounts created successfully",
    "data": {
        "count": 2,
        "emails": ["user1@example.com", "user2@example.com"]
    }
}
```

**Notes:**

- Requires project to have ghost accounts enabled
- Creates users with `is_ghost = true`
- Users can't login until they claim their account

---

### 12. Claim Ghost Account

Convert a ghost account to a regular account.

**Endpoint:** `POST /auth/ghost-accounts/claim`

**Request Body:**

```json
{
    "email": "user1@example.com",
    "name": "John Doe",
    "password": "secret123",
    "password_confirmation": "secret123",
    "otp": "123456"
}
```

**Response (200 OK):**

```json
{
    "message": "Ghost account claimed successfully",
    "data": {
        "user": {
            "id": "uuid",
            "name": "John Doe",
            "email": "user1@example.com",
            "is_ghost": false,
            "email_verified_at": "2024-01-01T00:00:00.000000Z"
        },
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
    }
}
```

---

## Error Responses

### Standard Error Format

```json
{
    "message": "Error description",
    "errors": {
        "field_name": ["Error message"]
    }
}
```

### Common HTTP Status Codes

| Code | Description       | Example                                    |
| ---- | ----------------- | ------------------------------------------ |
| 200  | Success           | `{"message": "Operation successful"}`      |
| 201  | Created           | User registration successful               |
| 400  | Bad Request       | Invalid JSON, missing required fields      |
| 401  | Unauthorized      | Invalid/missing authentication             |
| 403  | Forbidden         | Project disabled, insufficient permissions |
| 404  | Not Found         | Resource doesn't exist                     |
| 422  | Validation Error  | Invalid email, password too short          |
| 429  | Too Many Requests | Rate limit exceeded                        |
| 500  | Server Error      | Internal server error                      |

### Validation Errors

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "The email field is required.",
            "The email must be a valid email address."
        ],
        "password": [
            "The password must be at least 8 characters.",
            "The password confirmation does not match."
        ]
    }
}
```

### Authentication Errors

```json
{
    "message": "Invalid credentials"
}
```

### Rate Limit Errors

```json
{
    "message": "Too many attempts. Please try again in 60 seconds."
}
```

---

## Rate Limiting

### Default Limits

- **Authentication endpoints**: 10 requests/minute per IP
- **Other endpoints**: 100 requests/minute per project
- **OTP resend**: 3 requests/10 minutes per email

### Headers in Response

```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1704067200
Retry-After: 60
```

---

## Token Information

### Access Token

- **Type**: JWT (JSON Web Token)
- **Expiration**: 15 minutes
- **Claims**: User ID, project ID, token type, expiration
- **Usage**: Bearer token in `Authorization` header

### Refresh Token

- **Type**: JWT with longer expiration
- **Expiration**: 30 days
- **Storage**: Encrypted in database
- **Usage**: Obtain new access token when expired

### Token Rotation

1. Old refresh token invalidated on use
2. New refresh token issued
3. Prevents token reuse

---

## Security Considerations

### 1. API Key Security

- Store `X-Project-Key` securely on client
- Never expose in client-side code
- Rotate keys regularly

### 2. Token Storage

- **Access Token**: Store in memory (not localStorage)
- **Refresh Token**: Store in HTTP-only cookie if possible
- **Web**: Use secure, HTTP-only cookies
- **Mobile**: Use secure storage (Keychain/Keystore)

### 3. Password Requirements

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (optional based on project settings)

### 4. Email Verification

- Required for critical operations
- OTP validity: 10 minutes
- Automatic cleanup of expired OTPs

---

## Webhooks (Future)

### Planned Events

- `user.registered`
- `user.logged_in`
- `user.password_reset`
- `user.email_verified`
- `user.ghost_account_claimed`

### Configuration

Configure webhook URLs in Filament admin panel per project.

---

## Changelog

### v1.0.0 (Current)

- User registration and login
- Token-based authentication
- Password reset with OTP
- Email verification
- Ghost accounts
- Project isolation
- Audit logging
- Rate limiting
- Custom email templates

### Future Plans

- Social login (OAuth2)
- Webhooks
- Custom user fields
- Role-based access control
- Analytics dashboard
- Bulk operations

---

## Support

For API-related issues:

1. Check error responses for details
2. Verify `X-Project-Key` is correct
3. Ensure project is active
4. Check rate limits
5. Contact support with error details and request ID

**Contact:** elferjani7@gmail.com

---

_Last updated: April 6, 2026_
