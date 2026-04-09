# Plan 6: Dynamic Project User Custom Fields

## Summary

- We want each `Project` to define its own `ProjectUser` schema without editing the database schema every time.
- Example:
    - project owner creates a field called `status`
    - selects type `enum`
    - enters options `pending`, `approved`, `cancelled`
    - marks it as `required`, `show_in_form`, `show_in_table`, or `unique`
- After saving the field definition, the field should apply to all `project_users` inside that project.
- This must work in:
    - Filament admin create/edit screens
    - Filament table listing
    - API payloads and API responses
    - validation and uniqueness enforcement

## Core Decision

- Do **not** create a new physical column in `project_users` every time a project owner adds a field.
- Do **not** run migrations dynamically from the UI.
- Build a **schema-builder layer** on top of `ProjectUser` using:
    - a table for field definitions
    - a table for field values

## Why Dynamic DB Columns Are the Wrong Choice

- Dynamic migrations from the UI are dangerous in a multi-project system.
- Every new field would alter the shared `project_users` table for all projects, even if only one project needs the field.
- Rolling back or renaming fields becomes very hard.
- `enum` changes would require schema changes.
- Production deploys become risky because product behavior starts mutating the database structure live.
- Authorization, caching, testing, and indexing become harder to reason about.

## Recommended Architecture

- Keep the current `project_users` schema intact.
- Add `custom_fields` as an optional extension layer for extra business/project-specific data.
- Do not remove, rename, or relocate current auth/system columns in this rollout.
- Add a new table for field definitions per project.
- Add a new table for field values per project user.
- Use Laravel services to:
    - build dynamic form fields
    - build dynamic table columns
    - validate dynamic request payloads
    - persist typed values safely
    - enforce uniqueness safely

## Current Fixed Columns Stay Unchanged

For this rollout, current `project_users` columns remain as they are.

This means the custom field system is:

- additive only
- optional
- safe for backward compatibility
- not a schema rewrite

## Reserved System Fields

These fields are reserved and should not be creatable as custom fields:

- `id`
- `project_id`
- `email`
- `password`
- `first_name`
- `last_name`
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
- `deleted_at`

If we need extra business fields such as `status`, `salary`, `department`, `employee_number`, or anything project-specific, they should go into `custom_fields` without touching the current fixed schema.

## Product Goal

- A project owner can define a custom schema for project users.
- The custom schema is project-scoped.
- Each project may have a different set of fields.
- Custom fields behave like real product fields:
    - render in forms
    - validate
    - save
    - appear in responses
    - optionally appear in tables
    - optionally be searchable/filterable/sortable
    - optionally be unique inside the project

## Target User Experience

### Project-side schema builder

- Inside a project settings area, the owner can create a new field definition with:
    - `label`
    - `key`
    - `type`
    - `required`
    - `nullable`
    - `default value`
    - `unique`
    - `show in admin form`
    - `show in API`
    - `show in table`
    - `searchable`
    - `filterable`
    - `sortable`
    - `sort order`
    - type-specific settings

### Example

- `label`: `Status`
- `key`: `status`
- `type`: `enum`
- `options`: `pending`, `approved`, `cancelled`
- `default`: `pending`
- `required`: `true`
- `unique`: `false`
- `show_in_table`: `true`

After saving:

- every `ProjectUser` form in that project shows a `status` field
- every new `ProjectUser` receives validation for `status`
- table listing can display `status`
- API can return `status`

## Database Design

### 1. `project_user_fields`

This table stores the schema definition for each project.

Suggested columns:

- `id` `char(36)`
- `project_id` `char(36)`
- `key` `varchar(64)`
- `label` `varchar(120)`
- `type` `varchar(50)`
- `description` `varchar(255)` nullable
- `placeholder` `varchar(255)` nullable
- `default_value` `json` nullable
- `options` `json` nullable
- `validation_rules` `json` nullable
- `ui_settings` `json` nullable
- `is_required` `tinyint(1)`
- `is_nullable` `tinyint(1)`
- `is_unique` `tinyint(1)`
- `is_searchable` `tinyint(1)`
- `is_filterable` `tinyint(1)`
- `is_sortable` `tinyint(1)`
- `show_in_admin_form` `tinyint(1)`
- `show_in_api` `tinyint(1)`
- `show_in_table` `tinyint(1)`
- `is_active` `tinyint(1)`
- `sort_order` `int unsigned`
- `created_at`
- `updated_at`
- `deleted_at` nullable

Required indexes:

- unique index on `(project_id, key)`
- index on `(project_id, is_active, sort_order)`
- index on `(project_id, type)`

### 2. `project_user_field_values`

This table stores the typed value for each custom field and each project user.

Suggested columns:

- `id` `char(36)`
- `project_id` `char(36)`
- `project_user_id` `char(36)`
- `project_user_field_id` `char(36)`
- `value_string` `varchar(255)` nullable
- `value_text` `longtext` nullable
- `value_integer` `bigint` nullable
- `value_decimal` `decimal(30,10)` nullable
- `value_boolean` `tinyint(1)` nullable
- `value_date` `date` nullable
- `value_datetime` `datetime` nullable
- `value_json` `json` nullable
- `value_hash` `char(64)` nullable
- `unique_scope_key` `char(36)` nullable
- `created_at`
- `updated_at`

Required indexes:

- unique index on `(project_user_id, project_user_field_id)`
- unique index on `(unique_scope_key, value_hash)`
- index on `(project_user_field_id, value_string)`
- index on `(project_user_field_id, value_integer)`
- index on `(project_user_field_id, value_decimal)`
- index on `(project_user_field_id, value_boolean)`
- index on `(project_user_field_id, value_date)`
- index on `(project_user_field_id, value_datetime)`
- index on `(project_id, project_user_id)`

### Why store values in typed columns instead of one JSON blob?

- `unique` becomes much easier and safer
- filtering and sorting are much easier
- numeric/date comparisons become correct
- large projects can be indexed properly
- DB queries stay predictable

## Type Catalog

The product should expose the practical data types that make sense for `ProjectUser` data. We should not blindly expose every raw SQL type to end users.

### Directly supported in v1

| Product Type | DB Storage | Notes |
| --- | --- | --- |
| `string` | `value_string` | General short text |
| `text` | `value_text` | Long text |
| `integer` | `value_integer` | Covers tiny/small/medium/big via settings |
| `decimal` | `value_decimal` | Recommended for money and precise numeric values |
| `boolean` | `value_boolean` | True / false |
| `date` | `value_date` | Date only |
| `datetime` | `value_datetime` | Full date-time |
| `enum` | `value_string` | Backed by allowed options list |
| `email` | `value_string` | Stored as normalized lowercase string |
| `url` | `value_string` | Stored as string with URL validation |
| `phone` | `value_string` | Stored as string |
| `uuid` | `value_string` | Stored as canonical UUID string |
| `json` | `value_json` | Structured payload |

### Supported through configuration mapping

| Database-like Type | Product Mapping |
| --- | --- |
| `varchar`, `char` | `string` |
| `tinyInteger`, `smallInteger`, `mediumInteger`, `bigInteger` | `integer` with min/max settings |
| `float`, `double` | use `decimal` instead |
| `timestamp` | `datetime` |
| `mediumText`, `longText` | `text` |

### Intentionally excluded from v1

- `binary`
- `blob`
- `geometry`
- `point`
- `polygon`
- `set`
- database-native generated columns

Reason:

- they do not fit normal user profile data
- they complicate UI, validation, serialization, and indexing
- they add cost without clear product value

## Constraint Catalog

The schema builder should support these constraints and field settings:

- `required`
- `nullable`
- `default`
- `unique`
- `min`
- `max`
- `min_length`
- `max_length`
- `regex`
- `precision`
- `scale`
- `allowed_options`
- `multiple` if we later add multi-select
- `searchable`
- `filterable`
- `sortable`
- `hidden`
- `read_only`
- `placeholder`
- `help_text`

## Type-Specific Rules

### `string`

- validation:
    - `string`
    - `min`
    - `max`
    - optional `regex`
- unique:
    - supported

### `text`

- validation:
    - `string`
    - optional `min`
    - optional `max`
- unique:
    - technically possible through `value_hash`
    - product recommendation: allow, but warn because it is rarely useful

### `integer`

- validation:
    - `integer`
    - optional `min`
    - optional `max`
- unique:
    - supported

### `decimal`

- validation:
    - `numeric`
    - `decimal:min,max`
    - custom precision/scale checks
- unique:
    - supported
- note:
    - use `decimal`, not `float` or `double`, to avoid precision surprises

### `boolean`

- validation:
    - `boolean`
- unique:
    - product recommendation: do not allow in UI
    - it is almost never meaningful to require only one `true` or one `false` in a project

### `date`

- validation:
    - `date`
    - optional `after`
    - optional `before`
- unique:
    - supported

### `datetime`

- validation:
    - `date`
    - optional date-window rules
- unique:
    - supported

### `enum`

- validation:
    - `required`
    - `in:...`
- unique:
    - supported
- extra rules:
    - options must be unique
    - option keys should be machine-safe

### `email`

- validation:
    - `email`
    - normalize to lowercase before save
- unique:
    - supported

### `url`

- validation:
    - `url`
- unique:
    - supported

### `phone`

- validation:
    - string
    - optional regex
- unique:
    - supported

### `uuid`

- validation:
    - `uuid`
- unique:
    - supported

### `json`

- validation:
    - `array` or valid JSON string depending on transport format
- unique:
    - technically possible through normalized JSON hash
    - recommended to disable in v1 UI because canonical comparison rules are more complex

## Uniqueness Strategy

This is the most important part of the design because `unique` is hard to guarantee correctly with dynamic fields.

### Problem

- If values are stored in one JSON column, DB-level uniqueness becomes weak.
- App-only uniqueness checks are race-condition prone.

### Recommended solution

- Store each field value in `project_user_field_values`.
- Build a canonical normalized representation of the value.
- Hash the normalized representation into `value_hash`.
- If the field definition has `is_unique = true`, set:
    - `unique_scope_key = project_user_field_id`
- If the field is not unique, set:
    - `unique_scope_key = null`
- Add DB unique index:
    - `(unique_scope_key, value_hash)`

### Why this works

- MySQL allows multiple `NULL` values in a unique index.
- Non-unique fields keep `unique_scope_key = null`, so duplicates remain allowed.
- Unique fields enforce uniqueness per field definition.
- Since a field definition belongs to one project, uniqueness is automatically project-scoped.

### Normalization rules before hashing

- `string`, `email`, `url`, `phone`, `uuid`, `enum`:
    - trim spaces
    - lowercase for `email`
- `integer`:
    - cast to canonical integer string
- `decimal`:
    - format to canonical scale string
- `boolean`:
    - `1` or `0`
- `date`:
    - `YYYY-MM-DD`
- `datetime`:
    - ISO-like canonical UTC or app-timezone representation, picked once and kept consistent
- `json`:
    - sorted keys, stable JSON encoding

## Reserved Keys

The schema builder must reject custom keys that conflict with existing fixed attributes or internal names.

Reserved keys should include at least:

- `id`
- `project_id`
- `email`
- `password`
- `is_active`
- `last_login_at`
- `email_verified_at`
- `is_ghost`
- `claimed_at`
- `invited_at`
- `ghost_source`
- `must_set_password`
- `must_verify_email`
- `created_at`
- `updated_at`
- `deleted_at`
- `custom_fields`

## Lifecycle Rules

### Creating a field definition

- Validate `key` format:
    - lowercase snake case only
- Reject duplicate keys in the same project.
- Reject invalid defaults.
- Reject invalid option sets for `enum`.
- Reject `unique = true` when the type is not allowed by product rules.

### Updating a field definition

- Allow label, description, visibility, and non-breaking rules to change.
- Block unsafe type changes after values already exist.
- Example unsafe changes:
    - `text` to `integer`
    - `decimal` to `enum`
    - `json` to `date`

### Deleting a field definition

- Use soft deletes for definitions in v1.
- Hide the field from forms and tables.
- Keep historical values unless a separate purge action is explicitly executed.

## Required Fields and Existing Users

When a new field is added as `required`, we must define what happens to already existing `project_users`.

Recommended rule:

- `required` applies to all future create and update operations
- existing rows are allowed to remain empty until they are edited, unless a default is provided

Safer alternative:

- if the field is marked `required`, require a `default_value`
- backfill existing users immediately

Recommended v1 choice:

- support optional backfill action
- do not silently mutate all existing users without explicit admin intent

## Filament Admin Architecture

## New management area

Recommended UX:

- add a project-scoped management page for `Project User Fields`
- keep it under the existing `Project` management flow instead of a global standalone resource

Reason:

- custom fields belong to a specific project
- managing them globally is confusing
- project-scoped UX matches the domain model

### Suggested implementation targets

- `app/Models/ProjectUserField.php`
- `app/Models/ProjectUserFieldValue.php`
- `app/Filament/Resources/Projects/Pages/ProjectUserSchema.php`
- `app/Filament/Resources/ProjectUsers/Schemas/ProjectUserCustomFields.php`
- `app/Filament/Resources/ProjectUsers/Tables/ProjectUserDynamicColumns.php`
- `app/Services/ProjectUserFields/BuildProjectUserFieldComponents.php`
- `app/Services/ProjectUserFields/ValidateProjectUserFieldPayload.php`
- `app/Services/ProjectUserFields/SaveProjectUserFieldValues.php`

## Dynamic Form Rendering

Current fixed form lives in:

- `app/Filament/Resources/ProjectUsers/Schemas/ProjectUserForm.php`

Plan:

- keep current fixed inputs as they are
- append a second section called `Custom Fields`
- load active project field definitions
- render components dynamically based on field type

### Component mapping

| Type | Filament Component |
| --- | --- |
| `string` | `TextInput` |
| `text` | `Textarea` |
| `integer` | `TextInput::numeric()` |
| `decimal` | `TextInput::numeric()` |
| `boolean` | `Toggle` |
| `date` | `DatePicker` |
| `datetime` | `DateTimePicker` |
| `enum` | `Select` |
| `email` | `TextInput::email()` |
| `url` | `TextInput::url()` |
| `phone` | `TextInput` |
| `uuid` | `TextInput` |
| `json` | `Textarea` or a structured editor later |

### Form state shape

- store dynamic state under:
    - `custom_fields`

Example payload:

```php
[
    'email' => 'person@example.com',
    'first_name' => 'A',
    'custom_fields' => [
        'status' => 'pending',
        'salary' => '1250.50',
        'employee_number' => 'E-100',
    ],
]
```

## Dynamic Table Rendering

Current table lives in:

- `app/Filament/Resources/ProjectUsers/Tables/ProjectUsersTable.php`

Plan:

- keep the current fixed columns
- append dynamic columns for fields with `show_in_table = true`
- each dynamic column resolves its state from the related value record

### Table rendering strategy

For v1:

- display dynamic columns
- allow sorting/filtering only on safe supported types

Recommended sortable/filterable types in v1:

- `string`
- `integer`
- `decimal`
- `boolean`
- `date`
- `datetime`
- `enum`

Deferred or limited in v1:

- `text`
- `json`

### Query strategy

- eager load field values for visible custom fields
- use subqueries or constrained joins only for active visible fields
- avoid loading all values for all fields when not needed

## API Contract

Current API resource lives in:

- `app/Http/Resources/ProjectUserResource.php`

Plan:

- preserve all current fixed response keys
- add a new `custom_fields` object

Example response:

```json
{
  "id": "uuid",
  "project_id": "uuid",
  "email": "user@example.com",
  "first_name": "User",
  "last_name": "Example",
  "custom_fields": {
    "status": "pending",
    "salary": "1250.50",
    "employee_number": "E-100"
  }
}
```

### API requests

Requests that create or update project users should accept:

```json
{
  "email": "user@example.com",
  "password": "password",
  "password_confirmation": "password",
  "custom_fields": {
    "status": "approved",
    "salary": "2000.00"
  }
}
```

### Validation strategy for API

- base request validates core fixed fields
- dynamic validator resolves the project from `X-Project-Key`
- load active custom field definitions for that project
- build additional Laravel validation rules dynamically
- merge dynamic validated data into a persistence DTO or service payload

## Search, Filter, and Sort Rules

### Search

- only enable search on fields explicitly marked `is_searchable`
- only support search on string-like types in v1:
    - `string`
    - `email`
    - `phone`
    - `uuid`
    - `enum`

### Filter

- `boolean`:
    - true / false
- `enum`:
    - option-based select filter
- `integer`, `decimal`, `date`, `datetime`:
    - range or comparison filters later
- `string`:
    - contains filter

### Sort

- only allow sort on fields marked `is_sortable`
- support sort on:
    - `string`
    - `integer`
    - `decimal`
    - `date`
    - `datetime`
    - `enum`

## Service Layer

The logic should not live directly inside Filament pages or request classes.

Recommended services:

- `BuildProjectUserFieldDefinitions`
- `BuildProjectUserValidationRules`
- `NormalizeProjectUserFieldValue`
- `SaveProjectUserFieldValues`
- `LoadProjectUserFieldValueMap`

Responsibilities:

- transform DB definitions into UI components
- transform DB definitions into validation rules
- normalize values per type
- write or update typed value rows
- build API-safe output maps

## Validation Rules per Definition

Each definition can generate Laravel rules dynamically.

Example:

- `status`
    - type: `enum`
    - required: true
    - options: `pending`, `approved`, `cancelled`

Generated rules:

```php
[
    'custom_fields.status' => ['required', 'string', Rule::in(['pending', 'approved', 'cancelled'])],
]
```

Another example:

- `salary`
    - type: `decimal`
    - required: false
    - precision: 12
    - scale: 2
    - min: 0

Generated rules:

```php
[
    'custom_fields.salary' => ['nullable', 'numeric', 'min:0'],
]
```

## Performance Rules

- never load all field definitions globally
- always scope definitions by current project
- eager load only active visible fields
- index field value columns for filterable/sortable types
- do not search JSON fields with generic full table scans in v1

## Security Rules

- definitions and values must always be project-scoped
- a project owner may only manage fields for their own project
- API requests may only validate against the current project schema
- do not expose hidden or admin-only fields in public API responses

## Backward Compatibility

- existing `ProjectUser` flows must continue to work with zero custom fields
- no breaking changes to current `project_users` columns in this rollout
- existing API consumers should continue to receive all current fixed keys
- `custom_fields` is additive and optional, not a replacement for current fields

## Migration and Rollout Plan

### Phase 1: Data layer

- create `project_user_fields`
- create `project_user_field_values`
- add models and relationships
- add definition/value services

### Phase 2: Admin field builder

- add project-scoped schema management page
- create field definitions from Filament
- validate definitions

### Phase 3: Admin `ProjectUser` forms

- render custom fields in create/edit forms
- persist custom field values

### Phase 4: API integration

- accept `custom_fields` payloads
- validate dynamically
- return `custom_fields` in API resources

### Phase 5: Table integration

- show dynamic columns
- add filter and sort support for safe types

### Phase 6: Hardening

- unique enforcement tests
- reserved key tests
- backfill workflow for required fields
- performance tuning

## Test Plan

### Migration tests

- tables are created correctly
- indexes exist where needed

### Definition validation tests

- rejects duplicate keys per project
- allows same key in different projects
- rejects reserved keys
- rejects invalid enum options
- rejects invalid default value

### Value persistence tests

- creates a value row for each submitted custom field
- updates existing value rows
- deletes or nulls value rows correctly when nullable values are cleared

### Unique tests

- unique custom field rejects duplicates inside the same project
- same value in different projects remains allowed
- unique hash strategy blocks race-condition duplicates at the DB layer

### Form tests

- dynamic fields render on create page
- dynamic fields render on edit page
- required fields show validation errors
- enum fields reject invalid options

### API tests

- register accepts valid `custom_fields`
- register rejects invalid `custom_fields`
- API resource returns `custom_fields`
- hidden fields are not returned publicly

### Table tests

- visible custom fields appear in table
- hidden custom fields do not appear
- supported sortable/filterable types behave correctly

## Main Risks

### Risk 1: Over-engineering with raw SQL types

- Exposing every literal SQL type would create unnecessary product complexity.

Mitigation:

- expose only meaningful profile-oriented types in v1
- internally map lower-level DB variants to product types

### Risk 2: Unique field race conditions

- app-only uniqueness checks can fail under concurrency

Mitigation:

- use DB-backed uniqueness through `unique_scope_key + value_hash`

### Risk 3: Table performance

- too many dynamic columns or filters can make listing slow

Mitigation:

- visible fields only
- indexed typed columns
- scope queries tightly

### Risk 4: Breaking type changes

- changing a field type after data exists can corrupt interpretation

Mitigation:

- block unsafe type changes
- allow only safe metadata edits after data exists

## Recommended Product Rules

- maximum active custom fields per project in v1:
    - `25`
- maximum visible table custom columns in v1:
    - `8`
- default supported unique types:
    - `string`
    - `integer`
    - `decimal`
    - `date`
    - `datetime`
    - `enum`
    - `email`
    - `phone`
    - `url`
    - `uuid`
- default unsupported unique types in v1:
    - `boolean`
    - `json`

## Acceptance Criteria

- A project can define its own custom `ProjectUser` schema.
- Different projects can have different custom fields.
- Current `project_users` columns remain untouched in this rollout.
- Custom fields work as an optional extension layer on top of the current schema.
- A custom field such as `status` enum can be created once and used across all users in that project.
- Custom fields appear in admin forms and can be saved correctly.
- Custom fields are returned in API responses under `custom_fields`.
- Unique custom fields are enforced correctly per project.
- Existing current auth and system behavior continues to work unchanged.

## Final Recommendation

- Build this as a **project-scoped schema builder** with:
    - `project_user_fields`
    - `project_user_field_values`
- Do not use dynamic schema migrations for every user-created field.
- Use typed value storage, not one big JSON-only blob, because this product explicitly needs:
    - `enum`
    - `decimal`
    - `unique`
    - filtering
    - sorting
    - API correctness

This is the cleanest architecture for the current app because it matches the existing `Project -> ProjectUser` model while keeping the auth-critical columns stable and giving each project its own customizable user schema.

It also matches the safer rollout direction:

- keep current columns as they are
- add `custom_fields` only for extra optional data
- avoid destructive schema changes
- avoid breaking current admin, API, and auth behavior
