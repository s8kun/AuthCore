# Plan 2: Multi-Tenant Auth Provider Expansion

## Goal

- Evolve the current Laravel 13 app from a small project-scoped auth API into a provider-style multi-tenant authentication platform similar in concept to Clerk.
- Keep `users` for platform-owner / Filament authentication only.
- Keep `project_users` for API-side end users only.
- Resolve every auth action inside a concrete project context using `X-Project-Key`.
- Expose project configuration, branding, mail behavior, templates, logs, and integration docs inside Filament.

## Current Baseline In The Codebase

The current v1 foundation already includes:

- `projects`, `project_users`, and `api_request_logs` exist.
- `ResolveProjectFromApiKey`, `EnsureProjectAccessMatchesToken`, and `LogProjectApiRequest` already exist.
- `/api/v1/auth/register`, `/login`, `/me`, and `/logout` already exist.
- Filament already has project management, API request log views, and a basic project integration page.
- Pest feature tests already cover missing project key, register, login, me, logout, cross-project token misuse, and simple rate limiting.

## Major Gaps Between Current State And The New Product Brief

- Primary keys are integers today; the new platform should use UUIDs across the auth-provider domain.
- `projects` is too small today: it lacks `slug`, `status`, `api_secret`, and future-friendly project metadata.
- `project_users` is too small today: it lacks profile fields, verification state, lifecycle fields, ghost-account fields, and activity fields.
- There is no per-project auth settings table.
- There is no per-project mail settings table.
- There is no project email template system.
- There is no OTP subsystem.
- There is no project-scoped password reset subsystem.
- There is no ghost-account lifecycle.
- There is no refresh-token rotation subsystem.
- There is no auth-event log table.
- The current rate limiting is generic and not driven by project auth settings.
- The current integration docs page only covers register, login, me, and logout.
- Tests currently assert the smaller integer-ID v1 model and will need to be rewritten for the richer UUID-based platform.

## Product And Architecture Decisions

### Core Tenant Model

- Tenant = `Project`.
- Isolation model = one database, row-scoped by `project_id`.
- Every public auth endpoint under `/api/v1/auth` must require project resolution through `X-Project-Key`.
- Email lookup, token lookup, OTP lookup, refresh lookup, password reset lookup, and ghost-account claim lookup must always be validated against the resolved project.

### Auth Boundaries

- `users` remains the platform-owner model for Filament.
- `project_users` remains the API-authenticatable model for client applications.
- Filament must never authenticate as `project_users`.
- Client apps must never authenticate against owner `users`.

### Token Strategy

- Use Sanctum for short-lived access tokens for `project_users`.
- Add a custom `refresh_tokens` table for long-lived refresh tokens with rotation and reuse detection hooks.
- Enforce project match on protected endpoints even when a valid bearer token is present.

### UUID Strategy

Convert auth-provider tables to UUID primary keys:

- `projects`
- `project_users`
- `project_auth_settings`
- `project_mail_settings`
- `project_email_templates`
- `project_otps`
- `project_password_resets`
- `refresh_tokens`
- `api_request_logs`
- `auth_event_logs`
- Current tests and resources that assume integer IDs must be updated.

### Important Migration Risk

- The current Sanctum migration uses `morphs('tokenable')`, which creates an integer-based polymorphic key.
- If `project_users` moves to UUIDs, Sanctum token storage must become UUID-compatible.
  Implementation assumption for v2:

- platform owners remain session-authenticated in Filament only
- Sanctum access tokens are primarily for `project_users`
- we will refactor `personal_access_tokens` to support UUID-compatible tokenable IDs before the new auth flows ship

## Version-Specific Notes Used For This Plan

- Laravel rate limiters can segment limits with custom `by()` keys, which fits project + endpoint + IP and project + email + purpose rules.
- Sanctum supports `createToken(name, abilities, expiresAt)`, which fits short-lived access-token issuance with per-project TTLs.
- Laravel mail supports multiple mailers and choosing a mailer at runtime, which supports a project-aware mail abstraction instead of one global auth sender.
- Filament 5 resource pages support custom header actions and form actions, which fits features like rotate API key and send test email.

## Proposed Data Model

### `projects`

- `id` UUID primary key
- `owner_id` foreign key to `users`, likely nullable only if we want future project transfer/import flexibility
- `name`
- `slug`
- `api_key`
- `api_secret` nullable for future secret-based verification / server-to-server flows
- `status`
- timestamps

Rules:

- auto-generate `api_key` on creation
- add API key rotation flow
- optionally add API secret rotation flow
- keep project uniqueness on `slug` and `api_key`

### `project_users`

- `id` UUID primary key
- `project_id`
- `email`
- `password`
- `first_name` nullable
- `last_name` nullable
- `phone` nullable
- `role` nullable
- `email_verified_at` nullable
- `last_login_at` nullable
- `is_active`
- `is_ghost`
- `claimed_at` nullable
- `invited_at` nullable
- `ghost_source` nullable
- `must_set_password`
- `must_verify_email`
- timestamps

Rules:

- unique on `project_id + email`
- same email allowed in different projects
- no active duplicate account creation inside the same project

### `project_auth_settings`

- `id` UUID primary key
- `project_id` unique
- `auth_mode`
- `access_token_ttl_minutes`
- `refresh_token_ttl_days`
- `otp_enabled`
- `otp_length`
- `otp_ttl_minutes`
- `otp_max_attempts`
- `otp_resend_cooldown_seconds`
- `otp_daily_limit_per_email`
- `forgot_password_enabled`
- `reset_password_ttl_minutes`
- `forgot_password_requests_per_hour`
- `email_verification_enabled`
- `ghost_accounts_enabled`
- `max_ghost_accounts_per_email` nullable
- `magic_link_enabled`
- `login_identifier_mode`
- timestamps

### `project_mail_settings`

- `id` UUID primary key
- `project_id` unique
- `mail_mode` enum: `platform`, `custom_smtp`
- `from_name`
- `from_email`
- `reply_to_email` nullable
- `support_email` nullable
- `smtp_host` nullable
- `smtp_port` nullable
- `smtp_username` nullable
- `smtp_password_encrypted` nullable
- `smtp_encryption` nullable
- `smtp_timeout` nullable
- `is_verified`
- `last_tested_at` nullable
- timestamps

Rules:

- encrypt SMTP password at rest
- never expose decrypted secrets in resources, tables, logs, or API payloads

### `project_email_templates`

- `id` UUID primary key
- `project_id`
- `type` enum
- `subject`
- `html_body`
- `text_body` nullable
- `is_enabled`
- timestamps

Template types for v1 of this expansion:

- `otp`
- `forgot_password`
- `reset_password_success`
- `welcome`
- `email_verification`
- `ghost_account_invite`

### `project_otps`

- `id` UUID primary key
- `project_id`
- `project_user_id` nullable
- `email`
- `purpose`
- `code_hash`
- `expires_at`
- `attempts`
- `max_attempts`
- `resend_count`
- `last_sent_at` nullable
- `consumed_at` nullable
- `meta` nullable JSON
- timestamps

Rules:

- store OTP hashed only
- purpose-specific verification
- resend cooldown
- max attempts
- expiry
- project-aware daily limits

### `project_password_resets`

- `id` UUID primary key
- `project_id`
- `project_user_id`
- `email`
- `token_hash`
- `expires_at`
- `used_at` nullable
- `requested_ip` nullable
- timestamps

### `refresh_tokens`

- `id` UUID primary key
- `project_id`
- `project_user_id`
- `token_hash`
- `expires_at`
- `revoked_at` nullable
- `last_used_at` nullable
- `replaced_by_token_id` nullable
- `user_agent` nullable
- `ip_address` nullable
- timestamps

Rules:

- store refresh tokens hashed only
- rotate on every refresh
- revoke on logout
- revoke token chain on suspicious reuse

### `api_request_logs`

- keep project-aware request logging
- migrate to UUID primary key
- expand payload later only if needed

### `auth_event_logs`

- `id` UUID primary key
- `project_id`
- `project_user_id` nullable
- `email` nullable
- `event_type`
- `endpoint` nullable
- `method` nullable
- `ip_address` nullable
- `success`
- `metadata` nullable JSON
- timestamps

## Domain Services To Add

- `ProjectResolver` or keep middleware-driven resolution with a reusable resolver service
- `ProjectAuthService`
- `ProjectRegistrationService`
- `ProjectLoginService`
- `ProjectTokenService`
- `RefreshTokenService`
- `ProjectOtpService`
- `ProjectPasswordResetService`
- `GhostAccountService`
- `ProjectMailService`
- `ProjectEmailTemplateRenderer`
- `AuthEventLogger`

Notes:

- Controllers should stay thin.
- Request validation should live in Form Requests.
- Cross-table writes should use transactions.
- Reusable auth rules should be implemented in service/action classes, not repeated in controllers.

## API Surface

### Required Endpoints

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `POST /api/v1/auth/send-otp`
- `POST /api/v1/auth/verify-otp`
- `POST /api/v1/auth/resend-otp`
- `POST /api/v1/auth/ghost-accounts`
- `POST /api/v1/auth/ghost-accounts/claim`

### Optional Endpoints For This Pass If Time Allows Cleanly

- `POST /api/v1/auth/verify-email`
- `POST /api/v1/auth/resend-verification`

### Response Rules

- consistent JSON envelope
- generic responses where user enumeration is possible
- resource classes for public payloads
- never expose SMTP secrets, OTPs, reset tokens, or refresh tokens in logs or debug payloads

## Mail Architecture

### Requirements

- support `platform` mail mode
- support `custom_smtp` per project
- support per-project sender identity
- support branded and templated email content
- support test-email flow from Filament
- queue emails where appropriate

### Proposed Design

- `project_mail_settings` holds runtime mail settings.
- `ProjectMailService` loads the active project mail configuration.
- When `mail_mode = platform`, it sends through the platform mailer with the project sender identity applied.
- When `mail_mode = custom_smtp`, it builds a project-specific mailer/transport at runtime from decrypted SMTP credentials.
- Template rendering happens before send via a safe placeholder interpolation service.
- Queued jobs should reload project settings by ID at execution time to avoid stale serialized secrets and to keep secrets out of payloads.

### Template Placeholders

- `{{ project_name }}`
- `{{ user_email }}`
- `{{ otp_code }}`
- `{{ reset_link }}`
- `{{ support_email }}`
- `{{ expires_in }}`
- `{{ app_name }}`

Rules:

- only allow approved placeholders
- perform escaped substitution by default
- only render known values, never evaluate arbitrary Blade or PHP

## OTP System Plan

- Hash OTP codes before storage.
- Generate purpose-specific OTP records.
- Enforce expiry, attempts, resend cooldown, and daily-per-email limits using project settings.
- Send OTP emails through the project mail service and email template renderer.
- Return generic API messages for send and verify flows.
- Log send, resend, success, failure, and lockout events in `auth_event_logs`.

## Password Reset Plan

- Keep password reset logic separate from Laravel owner password broker.
- Use `project_password_resets`, not the default `password_reset_tokens` flow.
  Request flow:

- resolve project
- check project setting
- generate hashed reset token or OTP-backed reset flow
- send branded email
- log request
  Reset flow:

- validate project scope and token validity
- update password
- revoke project access tokens and refresh tokens as needed
- mark reset token used
- log completion

## Ghost Account Plan

- Add ghost-account fields directly to `project_users`.
- Support creation by API and Filament.
- Allow a ghost user to be pre-provisioned with email and invitation metadata before full activation.
  Claim flow should:

- validate current project
- verify claim OTP or claim token
- optionally force password setup
- set `claimed_at`
- switch `is_ghost` to false
- preserve audit history
- prevent duplicate active-user collisions inside the same project

## Rate Limiting Plan

- Keep route-level rate limiting for broad endpoint protection.
- Add service-level throttles for email and OTP purpose limits where project settings differ per endpoint and per purpose.
  Key patterns:

- `project:{projectId}:endpoint:{endpoint}:ip:{ip}`
- `project:{projectId}:email:{email}:purpose:{purpose}`
- Return JSON `429` responses consistently.

## Logging And Observability Plan

### `api_request_logs`

- Continue logging all project-scoped API requests.
- Preserve log creation even for throttled or failed requests where practical.

### `auth_event_logs`

Track events such as:

- login success
- login failure
- registration success
- registration failure
- otp sent
- otp resend
- otp verified
- otp failed
- password reset requested
- password reset completed
- ghost account created
- ghost account claimed
- refresh token rotated
- logout

## Filament Dashboard Expansion

### Resources / Pages

- Projects
- Project Users
- Project Auth Settings
- Project Mail Settings
- Project Email Templates
- API Request Logs
- Auth Event Logs
- Ghost Accounts management
- Integration Docs page per project

### Important Filament Actions

- rotate API key
- optionally rotate API secret
- send test email
- create ghost account
- resend invitation / OTP

### Filament UX Rules

- keep Filament owner-only
- hide secrets in tables and infolists
- mask sensitive values unless the action explicitly reveals once
- scope resource queries carefully to the platform owner or future owner-team model

## Implementation Phases

### Phase 1: Refactor The Existing Foundation

- replace integer-based auth-provider IDs with UUIDs
- update factories, models, casts, and relationships
- make Sanctum token storage UUID-compatible for `project_users`
- expand `projects`
- expand `project_users`
- update current feature tests that assume integer IDs

### Phase 2: Add Project Configuration Domain

- create `project_auth_settings`
- create `project_mail_settings`
- create `project_email_templates`
- seed sensible defaults on project creation
- expose project configuration in Eloquent relationships and Filament

### Phase 3: Build Core Auth Services

- split the current single `AuthController` responsibilities into focused controllers/services
- add register, login, me, logout, refresh flows
- add consistent API resources and response formatters
- keep project resolution and project/token boundary middleware central

### Phase 4: OTP And Password Reset

- add OTP generation, resend, verification, and limits
- add project-scoped forgot/reset password flows
- queue branded emails
- add auth event logging

### Phase 5: Ghost Accounts

- add API creation and claim flows
- add Filament ghost-account management
- add invitation / claim delivery paths
- add lifecycle event logging

### Phase 6: Dynamic Mail

- add the runtime project mailer abstraction
- support platform mail and custom SMTP
- add test-email action
- ensure encrypted secret handling and safe response serialization

### Phase 7: Filament Provider Dashboard Completion

- expand project resource/navigation
- add settings resources/pages/forms/tables
- expand integration docs with all required request/response examples
- add rotate key and test-email actions

### Phase 8: Hardening And Test Coverage

- add Pest feature coverage for every required endpoint and security boundary
- add tests for rate limits, OTP expiry, refresh rotation, ghost-account claim, dynamic mail mode behavior, and log creation
- run Pint and the relevant test subsets continuously during implementation

## Test Plan

- project key resolution
- invalid / missing project key
- register
- login
- me
- logout
- refresh token rotation
- forgot password
- reset password
- OTP send
- OTP verify
- OTP expiry
- OTP resend cooldown
- same email across different projects
- project A token cannot access project B
- ghost account creation
- ghost account claim
- dynamic mail mode behavior
- project-specific rate limits
- API request log creation
- auth event log creation

## Important Refactors Required Before Implementation Starts

- Replace or update the existing v1 tests in `tests/Feature/Phase2DataModelTest.php` and `tests/Feature/ProjectAuthApiTest.php` because they currently assert integer IDs and the smaller v1 scope.
- Replace the current minimal `AuthController` with a more modular auth domain structure.
- Replace the current minimal project integration page examples with the richer auth-provider documentation page.
- Expand the current `Project` and `ProjectUser` factories to support ghost accounts, verification states, and settings-driven tests.

## Assumptions

- Filament stays platform-owner only.
- Project users stay API-only.
- Login identifier remains email-only in this pass, but the schema should stay extensible for username or phone later.
- Custom SMTP verification initially means a successful transport-level test email, not full DNS/SPF/DMARC validation.
- `owner_id` on projects can remain required unless the business needs orphaned/system-owned projects; if that requirement becomes important, we can keep it nullable without changing the overall auth architecture.
- We will favor clean service classes, Form Requests, enums, API Resources, and queued mail jobs over controller-heavy logic.

## Delivery Order For The Actual Build

1. Refactor IDs and core schema safely.
2. Add project configuration tables and default project bootstrapping.
3. Add modular auth services and refresh-token support.
4. Add OTP, forgot-password, and ghost-account flows.
5. Add dynamic mail and template rendering.
6. Expand Filament resources and integration docs.
7. Rewrite and expand Pest coverage.
8. Run Pint and final verification.
