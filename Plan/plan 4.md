# Retryable Pending Registration Rewrite

## Summary

- Keep project-scoped uniqueness as-is: one `project_users` record per `(project_id, email)`, while the same email may still exist in different projects.
- Rewrite normal registration so an existing **unverified user** is treated as a retryable signup on the same record instead of a duplicate.
- Remove any concept of "claim" from this flow entirely.
- Do **not** add a new DB `status` column now. Derive:
    - `pending` = `must_verify_email === true` and `email_verified_at === null`
    - `active` = verified account

## Key Changes

### Registration behavior

- Remove the request-layer `unique(project_id, email)` rejection from the register request. Validation should only normalize/validate the shape of the input.

- Move duplicate handling into the registration service inside a transaction with a project-scoped lookup on email and row locking.

- Registration branches:
    - No existing user: create a normal project user.
    - Existing unverified user: update the same record, do not create a duplicate.
    - Existing verified user: reject with `422` and `email: ["This email is already taken."]`.

- For retryable registrations, overwrite:
    - `password` always
    - optional profile fields only when they are present in the request
    - preserve omitted optional fields

- On any registration that ends in a pending/unverified state:
    - set `email_verified_at = null`
    - set `must_verify_email = true`
    - revoke any existing tokens for that user so pending users cannot keep older tokens
    - generate/send a fresh email-verification OTP

- On any registration that ends in an active state:
    - set `must_verify_email = false`
    - set `email_verified_at = now()` if verification is not required
    - issue the normal access-token + refresh-token pair

---

### Public API contract

- `POST /api/v1/auth/register` becomes mode-dependent by project auth settings:
    - If `email_verification_enabled = true`, always return the same pending response for both first-time and retried unverified signups:
        - status `202 Accepted`
        - no tokens
        - response body includes:
            - `verification_required: true`
            - `verification_purpose: "email_verification"`
            - success message
            - user payload

    - If `email_verification_enabled = false`, keep the current token-pair success response:
        - status `201 Created`
        - same response shape for first-time and retried unverified signups

---

### Email Verification

- `POST /api/v1/auth/verify-otp` with `purpose = email_verification` activates the user:
    - set `email_verified_at = now()`
    - set `must_verify_email = false`
    - do not auto-login or return tokens
    - client logs in after verification

---

### Enforcement, mail, and audit

- Update auth enforcement so pending users cannot access protected routes:
    - middleware should deny users with `must_verify_email = true` and `email_verified_at = null`
    - login should reject pending users with a verification-required message
    - refresh token flow should also reject pending users

- Welcome email behavior:
    - do not send welcome email on pending registration
    - send it only once the account becomes active

- Logging:
    - `RegistrationSucceeded` with metadata:
        - `mode: created | retried`
        - `verification_required: true | false`

    - `RegistrationFailed` for verified-email collisions
    - `VerificationSent` when register triggers email verification
    - `VerificationCompleted` when email-verification activates the account

---

## Test Plan

- Register new user with verification enabled:
    - returns `202`
    - no tokens
    - user is pending
    - verification OTP/email is queued

- Retry register same email for existing unverified user:
    - returns the same `202` response
    - user count stays `1`
    - same user record is updated
    - password changes
    - provided fields overwrite, omitted fields stay intact
    - old tokens are revoked
    - fresh verification OTP/email is queued

- Register same email for existing verified user:
    - returns `422`
    - no user mutation
    - `RegistrationFailed` log created

- Register same email across different projects:
    - still allowed

- Pending user enforcement:
    - login rejected before verification
    - protected routes rejected
    - refresh rejected

- Verify email OTP:
    - marks user active
    - logs `VerificationCompleted`
    - welcome email is sent once
    - login succeeds afterward

---

## Assumptions

- “Single user record per email” applies per project scope.
- No concept of claim is used in this flow.
- No new persisted `status` column is added; pending/active is derived.
- Clients must call `login` after successful email verification.
