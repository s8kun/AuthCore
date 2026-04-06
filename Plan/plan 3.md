# Plan 3: Remaining Product Gaps

## Quick Verdict

- Yes, your understanding is correct.
- The project already supports `custom_smtp` in backend/domain logic.
- But there is no real Filament management surface to configure project mail settings.

## What Is Already Implemented

- `project_mail_settings` table exists with SMTP fields.
- `ProjectMailSetting` model exists with encrypted SMTP password casting.
- `ProjectMailMode` enum includes `platform` and `custom_smtp`.
- `ProjectMailService` can build runtime SMTP mailer for custom mode.
- Unit test exists for custom SMTP mailer build path.

## Missing In Filament (Main Gap)

- No dedicated form/page/section to edit `project_mail_settings` per project.
- No field to choose `mail_mode` (`platform` vs `custom_smtp`) from UI.
- No optional SMTP fields UX with conditional visibility based on selected mode.
- No secure SMTP password input flow (set/update without exposing stored secret).
- No test-email action in Filament to verify SMTP credentials.
- No visual status block for `is_verified` and `last_tested_at`.
- No explicit validation rules in Filament for custom mode requirements.

## Expected UX Behavior

- SMTP fields should be optional when `mail_mode = platform`.
- SMTP fields should become required when `mail_mode = custom_smtp`.
- `smtp_password_encrypted` should never be displayed back in clear text.
- If password input is left empty during edit, keep existing encrypted password.
- Add a clear, explicit action to reset/replace SMTP password.

## Suggested Implementation Scope

- Add a new Filament page (or section under project edit) for "Mail Settings".
- Bind fields to `Project->mailSettings` relation.
- Add conditional validation and field visibility by `mail_mode`.
- Add "Send Test Email" action using `ProjectMailService::sendTestEmail()`.
- Show verification state and last tested timestamp in the page.

## Testing Gaps

- Missing feature tests for Filament mail settings management.
- Missing tests for conditional validation (`platform` vs `custom_smtp`).
- Missing tests for preserving existing SMTP password when not re-entered.
- Missing tests for test-email action success/failure handling.

## Additional Gaps Found (Beyond SMTP)

### Filament Resource Coverage Is Incomplete

- `ProjectMailSettings`, `ProjectAuthSettings`, and `ProjectEmailTemplates` folders exist but are effectively empty in `Pages/`, `Schemas/`, and `Tables/`.
- Backend/domain logic exists for these features, but owner-facing management flows are missing in the panel.

### Project Sub-Navigation Is Too Limited

- Project record sub-navigation currently exposes only:
- Edit project
- Integration details
- Missing direct project-scoped entry points for:
- Mail Settings
- Auth Settings
- Email Templates

### `api_secret` Is Not Operational Yet

- `api_secret` is generated and stored, but currently not used in request authentication flows.
- This is acceptable if intentionally reserved, but should be treated as a backlog item to avoid product confusion.

## Additional Suggested Implementation Scope

- Build complete Filament resources/pages for:
- Project mail settings management
- Project auth settings management
- Project email template management
- Extend project record sub-navigation to include these pages.
- Add clear UI labels describing `api_secret` as reserved (or implement a real server-to-server flow if ready).

## Additional Testing Gaps

- Missing feature tests for Filament management of auth settings and email templates.
- Missing coverage that project sub-navigation exposes all required project-configuration pages.
- Missing tests asserting owner isolation for all new settings/resources (owner A cannot manage owner B project settings).
