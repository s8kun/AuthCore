# Plan 5: Optional Ghost Accounts, Self-Signup by Default

## Goal

- Keep `ghost account` flows available for projects that need invitations.
- Make `self-signup` the default behavior for all new projects.
- Ensure ghost flows are explicitly opt-in via project auth settings.

## Product Behavior (Target)

- New project default:
    - `ghost_accounts_enabled = false`
- When disabled:
    - `POST /api/v1/auth/ghost-accounts` returns validation error (feature disabled).
    - `POST /api/v1/auth/ghost-accounts/claim` returns validation error (feature disabled).
- When enabled:
    - Ghost create + claim flows behave exactly as today.

## Scope

- In scope:
    - Defaults in model/domain.
    - DB forward-fix migration for existing projects.
    - Enforcement in both ghost create and ghost claim service methods.
    - API/feature tests updates.
- Out of scope:
    - Removing ghost endpoints.
    - Reworking invitation UX.
    - Changing OTP core logic.

## Implementation Steps

1. Update project auth defaults

- File: `app/Models/ProjectAuthSetting.php`
- Change defaults payload:
    - `ghost_accounts_enabled` from `true` to `false`.

2. Enforce optionality in ghost claim path

- File: `app/Services/Auth/GhostAccountService.php`
- Keep existing check in `create(...)`.
- Add matching check in `claim(...)`:
    - load project settings
    - fail with `Ghost accounts are disabled for this project.` when disabled.

3. Add DB forward-fix migration

- Create migration to:
    - set `project_auth_settings.ghost_accounts_enabled` default to `false`
    - update existing rows from `true` to `false` only when business wants global switch (single statement in migration up)
- Down migration:
    - restore default to `true`
    - avoid force-restoring old row values unless explicitly required.

4. Keep endpoints but document feature toggle

- No route/controller removal.
- Ensure integration docs mention ghost endpoints are project-setting dependent.

5. Update tests

- File: `tests/Feature/ProjectAuthApiTest.php`
- For ghost flow test:
    - explicitly enable setting before create/claim assertions.
- Add test case:
    - ghost create/claim fails when `ghost_accounts_enabled = false`.

6. Format and verify

- Run:
    - `vendor/bin/pint --dirty --format agent`
    - `php artisan test --compact tests/Feature/ProjectAuthApiTest.php tests/Feature/Phase2DataModelTest.php`

## Acceptance Criteria

- New projects are self-signup-first without ghost account capability enabled.
- Ghost flow works only after per-project enablement.
- Claim path cannot bypass disabled setting.
- All focused tests pass.

## Risks

- Existing projects currently relying on ghost flow may break if migration flips all rows to `false`.

## Mitigation

- Use a rollout flag strategy:
    - Option A: only change default for new rows (safe rollout).
    - Option B: bulk switch existing rows and notify clients beforehand.

## Recommended Rollout

1. Release with default change + claim guard + tests.
2. Keep existing rows untouched initially.
3. Communicate toggle behavior.
4. Later, selectively disable ghost per project from admin settings.
