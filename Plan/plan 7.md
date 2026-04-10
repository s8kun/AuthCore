# Plan 7: Project User Contract Rewrite

## 1. Goal

This plan is the documentation and rewrite map for the `project_users` contract after the change made on `2026_04_05` in `database/migrations/2026_04_05_194410_create_project_users_table.php`.

The important change is:

- `project_users` no longer creates `first_name`
- `project_users` no longer creates `last_name`
- `project_users` no longer creates `phone`
- `project_users` no longer creates `role`

From this point, the project should be documented as:

- `project_users` = auth and account-state table
- `project_user_fields` + `project_user_field_values` = project-specific profile and business data layer

Assumption used in this plan:

- `first_name`, `last_name`, and `phone` should stop being treated as built-in database columns and should move to the dynamic custom-field layer when a project needs them.

## 2. Project Snapshot

This application is a multi-project auth platform built on Laravel + Filament.

Important project facts:

- Platform owners create and manage `projects`
- Each `project` has its own API key, auth settings, mail settings, email templates, logs, and project users
- Public auth endpoints live under `/api/v1/auth`
- Project resolution is done from `X-Project-Key`
- `project_users` are tenant-scoped identities, not platform admin users
- Access tokens are issued through Sanctum
- Refresh tokens, OTPs, password resets, auth event logs, and API request logs are all project-scoped
- Dynamic user schema already exists through `project_user_fields` and `project_user_field_values`
- Filament already has pages for project edit, auth settings, mail settings, email templates, project user schema, and integration details

## 3. Current Source Of Truth

### 3.1 Base `project_users` columns

These are the fields that should now be treated as the real built-in project-user contract:

- `id`
- `project_id`
- `email`
- `password`
- `email_verified_at`
- `last_login_at`
- `is_active`
- `is_ghost`
- `claimed_at`
- `invited_at`
- `ghost_source`
- `must_set_password`
- `must_verify_email`
- `created_at`
- `updated_at`

### 3.2 Dynamic project-user schema

These tables are now the extension layer for project-specific user data:

- `project_user_fields`
- `project_user_field_values`

This layer already supports:

- per-project field definitions
- API visibility rules
- admin form visibility rules
- table visibility rules
- uniqueness rules
- typed storage
- default values
- validation metadata

### 3.3 API surface that matters

The current project-scoped auth API is centered on:

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `POST /api/v1/auth/send-otp`
- `POST /api/v1/auth/resend-otp`
- `POST /api/v1/auth/verify-otp`
- `POST /api/v1/auth/ghost-accounts`
- `POST /api/v1/auth/ghost-accounts/claim`

## 4. What Should Be Documented, And What Should Be Skipped

Keep in scope:

- project architecture
- project-user base schema
- custom-field schema
- API request and response contracts
- ghost-account flow
- auth settings that change behavior
- Filament project management pages related to project users
- tests that protect this contract

Skip from the docs:

- `cache`, `jobs`, `sessions`, and default Laravel infrastructure tables
- vendor internals
- framework package details that do not change how this app behaves
- low-value implementation detail that is not part of the project-user contract

## 5. What Changed On `2026_04_05`

The migration change removed profile-style fields from the base auth table.

Old assumption:

- `project_users` stored auth fields and profile fields together

New assumption:

- `project_users` stores only identity, auth, and account-state fields
- project-specific user profile data belongs to the dynamic custom-field layer

This is a strong architectural shift, not a small column rename.

## 6. Analysis: Current Drift After The Migration Change

The database schema and the runtime contract are currently out of sync.

| Area | Current problem | Why it matters |
| --- | --- | --- |
| Base model | `ProjectUser` still treats `first_name`, `last_name`, and `phone` as fillable | Runtime code still behaves as if removed columns exist |
| API resource | `ProjectUserResource` still returns removed fields | Public API docs and real JSON contract are wrong |
| Register validation | `RegisterProjectUserRequest` still validates removed top-level fields | Request contract no longer matches schema direction |
| Ghost validation | ghost account requests still validate removed top-level fields | Ghost flow still depends on fields that were removed from the base table |
| Auth services | registration and ghost services still fill removed attributes | Persistence logic targets an outdated model shape |
| Filament form | Project user create/edit form still shows old built-in fields | Admin UI is misleading |
| Filament table | Project user table still displays old columns | Admin listing reflects the old contract |
| Schema builder | reserved-key and helper text still say the removed fields are built-in | Those keys cannot be recreated as dynamic fields |
| Integration docs | examples still show `first_name`, `last_name`, and `phone` as built-in request and response fields | Project docs are outdated |
| Factory and tests | factory defaults and feature tests still expect removed columns | Tests validate the old schema instead of the new one |

## 7. Critical Migration Risk

There is one hidden database risk that should not be ignored:

- editing the original create migration changes only fresh installs
- existing databases that already ran the old `project_users` migration will not automatically drop `first_name`, `last_name`, or `phone`
- there is already a follow-up migration for `role` and `device_name`, but there is no follow-up migration for `first_name`, `last_name`, or `phone`

Meaning:

- fresh databases can match the new contract
- previously migrated databases can still carry the old columns

Recommended handling:

- if this project has ever been migrated outside local fresh databases, add a forward-fix migration for `first_name`, `last_name`, and `phone` instead of relying only on the edited historical migration

## 8. What Is Already In The Right Direction

The rewrite is not starting from zero.

Already aligned with the new direction:

- dynamic project user fields already exist end-to-end
- project user field values already support typed storage
- registration already supports `custom_fields`
- integration details page is already being expanded to show the project-user contract and endpoint examples
- Filament tests already started moving toward custom-field-aware integration docs

This means the main task is contract cleanup and consistency, not a full new feature build.

## 9. Rewrite Targets Connected To The Migration

### 9.1 Database and model truth

- `database/migrations/2026_04_05_194410_create_project_users_table.php`
- `database/migrations/2026_04_06_130729_drop_role_and_device_name_columns.php`
- `app/Models/ProjectUser.php`
- `app/Models/ProjectUserField.php`
- `database/factories/ProjectUserFactory.php`

### 9.2 API contract layer

- `app/Http/Requests/Api/V1/RegisterProjectUserRequest.php`
- `app/Http/Requests/Api/V1/Auth/StoreGhostAccountRequest.php`
- `app/Http/Requests/Api/V1/Auth/ClaimGhostAccountRequest.php`
- `app/Http/Resources/ProjectUserResource.php`
- `app/Http/Resources/ProjectAuthResource.php`

### 9.3 Auth and account services

- `app/Services/Auth/ProjectAuthService.php`
- `app/Services/Auth/GhostAccountService.php`
- `app/Services/ProjectUserFields/SaveProjectUserFieldValues.php`

`SaveProjectUserFieldValues` is already part of the new design and becomes the main coordination point if ghost-account flows also move to `custom_fields`.

### 9.4 Filament admin and internal docs

- `app/Filament/Resources/ProjectUsers/Schemas/ProjectUserForm.php`
- `app/Filament/Resources/ProjectUsers/Tables/ProjectUsersTable.php`
- `app/Filament/Resources/Projects/Pages/ProjectUserSchema.php`
- `app/Filament/Resources/Projects/Pages/ProjectIntegrationDetails.php`
- `resources/views/filament/resources/projects/pages/project-integration-details.blade.php`

### 9.5 Tests

- `tests/Feature/Phase2DataModelTest.php`
- `tests/Feature/ProjectAuthApiTest.php`
- `tests/Feature/FilamentAdminPanelTest.php`

Any test that asserts the JSON shape of `ProjectUserResource` or the default `ProjectUser` factory shape should be considered connected to this migration.

## 10. Recommended Target Behavior

This is the clean target state the rewrite should aim for.

### 10.1 Base project-user contract

Built-in request and response fields should be limited to auth and account-state data.

Keep built-in:

- `email`
- `password`
- `password_confirmation`
- account-state flags and timestamps returned by the API

Remove from built-in contract:

- `first_name`
- `last_name`
- `phone`
- `role`

### 10.2 Custom-field contract

Project-specific identity details should live in `custom_fields`.

Recommended examples:

- `first_name`
- `last_name`
- `phone`
- `department`
- `status`
- `employee_number`
- `external_id`

### 10.3 Ghost-account flow

This flow now needs an explicit rule.

Recommended direction:

- allow ghost account create and claim flows to use `custom_fields` too

Reason:

- after removing base profile columns, ghost flows no longer have a correct storage location for profile-style data unless they also use the dynamic field system

If you do not want that behavior, then:

- remove all profile-field language from ghost docs
- keep ghost accounts limited to email, password state, and account flags only

### 10.4 Reserved field keys

`ProjectUserField::RESERVED_KEYS` should match the real built-in contract.

Recommended update:

- keep real system keys reserved
- stop reserving `first_name`, `last_name`, and `phone` if they are meant to become dynamic fields

## 11. Proposed Documentation Structure

This is the clean section order for the final readable docs.

### 11.1 Project Overview

Explain:

- what the platform does
- the difference between platform users and project users
- how a project scopes auth behavior

### 11.2 Core Data Model

Explain:

- `projects`
- `project_users`
- `project_auth_settings`
- `project_mail_settings`
- `project_email_templates`
- `project_user_fields`
- `project_user_field_values`
- auth logs and API logs only at a high level

### 11.3 Base Project User Contract

Explain only the fields that still exist in `project_users`.

This section should clearly state that:

- project-user base fields are auth and account-state fields
- project-specific profile data is not stored directly on `project_users`

### 11.4 Custom Fields Contract

Explain:

- how custom fields are defined
- what `show_in_api`, `show_in_admin_form`, and `show_in_table` mean
- how required, unique, and default values work
- example payload for `custom_fields`

### 11.5 API Endpoint Reference

Separate by flow:

- registration and login
- current-user endpoints
- refresh and logout
- password reset
- OTP
- ghost accounts

### 11.6 Response Shape

Document:

- token response shape
- user response shape
- `custom_fields` response behavior
- which fields are always returned
- which fields depend on project schema visibility

### 11.7 Project Settings That Change Behavior

Document only the settings that directly affect runtime behavior:

- email verification
- OTP
- forgot password
- ghost accounts
- token TTL
- refresh token TTL
- rate limit

### 11.8 Filament Admin Flow

Document the project-owner workflow for:

- project settings
- project user schema
- integration details
- project user management

### 11.9 Verification And Tests

Document the tests that protect the contract and should be updated with every schema change.

## 12. Implementation Order

Recommended order for the actual rewrite:

1. Lock the new source of truth for `project_users`
2. Fix model, factory, and reserved-key drift
3. Rewrite request validation and resource serialization
4. Rewrite auth services and ghost-account flow
5. Rewrite Filament forms, tables, and helper text
6. Rewrite integration docs and examples
7. Rewrite tests to match the new contract
8. Run focused test coverage for schema, API, and Filament behavior

## 13. Verification Plan

Minimum test pass after the rewrite:

- `php artisan test --compact tests/Feature/Phase2DataModelTest.php`
- `php artisan test --compact tests/Feature/ProjectAuthApiTest.php`
- `php artisan test --compact tests/Feature/FilamentAdminPanelTest.php`
- `php artisan test --compact tests/Feature/ProjectUserCustomFieldsApiTest.php`

What these should prove:

- schema expectations match the real table
- register and ghost flows match the new request contract
- API resources no longer expose removed base fields unless they come from `custom_fields`
- Filament project-user UI and integration docs match the new architecture

## 14. Final Summary

The project is already moving toward a better design:

- auth/system fields in `project_users`
- project-specific data in dynamic custom fields

The main remaining work is to rewrite every connected contract so the code, tests, Filament UI, and docs all describe the same system.

The biggest point to keep consistent in every section is:

- `first_name`, `last_name`, and `phone` are no longer safe to document as built-in `project_users` columns

That single rule should guide the full rewrite.
