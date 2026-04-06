# Laravel 13 Auth Server Plan with Filament

## Summary

- Build the app as a Laravel 13 auth server with one platform owner account model (users) and many isolated client projects.
- Use filament/filament as the main control plane for the platform owner only. Filament manages projects, shows integration details, and exposes logs/usage views.
- Use laravel/sanctum for project-user bearer tokens. Keep project auth API separate from Filament and scope every API request by project API key.
- Keep project isolation row-based in one database: the same email may exist in multiple different projects, but every auth operation is resolved inside exactly one
  project.

## Packages

- filament/filament: owner admin panel, project CRUD, integration pages, request log views.
- laravel/sanctum: hashed API tokens for project_users.
- No extra multitenancy package in v1.
- No generated API-doc package in v1; docs live inside Filament project pages with sample requests.

## Implementation Changes

### Phase 1: Platform Control Plane

- Install Filament and create one admin panel for the platform owner at a fixed path such as /admin.
- Use the existing users table for owner authentication into Filament.
- Create Filament resources/pages for:
- Projects
- API request logs
- Project integration details
- The project integration page must show:
- Base API URL
- X-Project-Key header
- Example Authorization: Bearer <token> usage
- Sample register, login, me, and logout requests/responses

### Phase 2: Data Model

- Keep Laravel default users for platform owners.
- Create projects with UUID id, owner_id, name, unique api_key, rate_limit, timestamps.
- Create project_users with UUID id, project_id, email, password, role, timestamps, and unique (project_id, email).
- Create api_request_logs with UUID id, project_id, endpoint, method, ip_address, created_at.
- Replace the proposed custom tokens table with Sanctum’s personal_access_tokens.
- Use HasUuids on Project and ProjectUser.
- Use HasApiTokens on ProjectUser.
- Add relationships so Project -> projectUsers, Project -> apiRequestLogs, and ProjectUser -> project.

### Phase 3: Project Auth API

- Add /api/v1/auth routes for:
- POST /register
- POST /login
- GET /me
- POST /logout
- Require X-Project-Key on all project auth endpoints.
- Add middleware that resolves the current project from X-Project-Key and rejects unknown keys.
- Scope all auth queries by resolved project_id; never authenticate by email alone.
- On login/register, issue Sanctum tokens for the resolved ProjectUser and return the plain token once with expiry metadata.
- On protected routes, require both the project key and a valid Sanctum bearer token, then verify the token owner belongs to the resolved project.

### Phase 4: Security, Throttling, and Observability

- Define a named Laravel rate limiter that uses each project’s rate_limit.
- Key throttling by project + IP + endpoint.
- Log project-scoped API requests into api_request_logs.
- Configure a global Sanctum token expiration value and expose it in auth responses.
- Schedule sanctum:prune-expired for expired token cleanup.
- Keep owner UI and project auth API fully separate: Filament is for the platform owner; project users are API-only in v1.

## Public Interfaces

- Owner interface: Filament admin panel for project management.
- Project auth interface:
- X-Project-Key: {api_key}
- Authorization: Bearer {token} for authenticated endpoints
- Resource responses should be JSON API-style or standard Laravel API Resources consistently across all endpoints.
- Project creation flow in Filament must generate and display the project API key immediately.

## Test Plan

- Pest feature tests for Filament owner access control and project CRUD.
- Feature tests for project key resolution and invalid/missing key handling.
- Feature tests for register, login, me, and logout.
- Feature tests proving the same email can exist in different projects.
- Feature tests proving a token from project A cannot access project B.
- Feature tests for per-project rate limiting and 429 responses.
- Feature tests for token expiry and logout token revocation.
- Feature tests for API request log creation on project-scoped endpoints.

## Assumptions

- Filament is owner-panel only in v1.
- Integration docs are delivered as Filament pages with examples, not Scribe/OpenAPI in v1.
- Project separation is single-database, row-scoped, not database-per-project.
- role on project_users remains a simple string in v1.
- Password reset, email verification, MFA, social auth, project-admin panel access, and SDK generation are out of scope for the first implementation.
