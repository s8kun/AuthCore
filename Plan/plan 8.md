# Plan 8: Filament Docs Navigation And Project Integration UX Redesign

## 1. Prompt Translation

This plan turns your request into a concrete redesign brief.

What you want:

- a new docs-focused section in the Filament sidebar below `Platform`
- project docs that explain the product clearly instead of only dumping data
- examples that help a developer integrate quickly
- a redesign of the current `Project Integration Details` page because the sections and UI/UX feel weak
- a planning document first, in `Plan/plan 8.md`
- the plan grounded in current Filament docs and a skill search, not guesses

## 2. Current State In The Codebase

The current admin shell is functional, but the experience is still very close to default Filament.

Current implementation facts:

- the admin panel still uses the stock `Dashboard` page
- the dashboard still shows the default `AccountWidget` and `FilamentInfoWidget`
- the main sidebar has `Observability`, `Identity`, and `Platform`
- `Platform` currently exposes only `Projects`
- there is no docs-focused top-level navigation group
- project record sub-navigation already exists and currently includes:
  - Edit Project
  - Mail Settings
  - Auth Settings
  - Email Templates
  - Project User Schema
  - Integration

Current `Project Integration Details` sections:

- Connection Details
- Project Snapshot
- Project User Contract
- Sample Requests And Responses
- Recent Request Activity

## 3. UI/UX Analysis

### 3.1 Why the dashboard feels generic

The screenshot shows a clean panel, but it does not yet feel like an auth platform control plane.

Problems:

- the dashboard uses Filament defaults instead of product-specific widgets
- the content area has too much empty space after two generic cards
- the main screen does not explain the product, current project state, or next actions
- the visual system is mostly default dark Filament with amber accents, not a deliberate admin brand

### 3.2 Why the current docs page feels heavy

The current page is technically useful, but not easy to scan.

Problems:

- it begins with configuration facts instead of a quick-start path
- `Project Snapshot` and `Recent Request Activity` are operational content, not developer docs content
- all sections have nearly the same visual weight
- long tables and code blocks appear before the user gets a simple integration story
- examples are present, but explanation and sequencing are weak
- the page feels like one long reference document instead of a guided developer surface

### 3.3 Information architecture problem

Right now, project documentation is hidden inside a single project page and labeled as `Integration`.

Why this is not ideal:

- `Integration` sounds technical but not friendly
- there is no dedicated docs destination in the main navigation
- the user cannot immediately tell where "learn how to integrate this project" lives
- docs, configuration, and observability are mixed together too closely

## 4. Context7 Notes For Filament 4

Context7 was used against Filament 4 docs and confirmed the following patterns:

- Filament supports record sub-navigation through resource page classes.
- Filament supports custom panel navigation groups.
- Filament supports custom panel navigation items.

What this means for this project:

- project-specific docs should stay inside the `Project` record context
- a global docs entry point can still be added to the main sidebar
- the best structure is a mix of:
  - panel-level docs entry points
  - record-level project docs pages

Adapted example for record sub-navigation:

```php
use Filament\Resources\Pages\Page;

public static function getRecordSubNavigation(Page $page): array
{
    return $page->generateNavigationItems([
        EditProject::class,
        ProjectAuthSettings::class,
        ProjectUserSchema::class,
        ProjectDeveloperDocs::class,
        ProjectApiReference::class,
    ]);
}
```

Adapted example for panel navigation groups:

```php
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

->navigationGroups([
    NavigationGroup::make()
        ->label('Observability')
        ->icon(Heroicon::OutlinedChartBar),
    NavigationGroup::make()
        ->label('Identity')
        ->icon(Heroicon::OutlinedUsers),
    NavigationGroup::make()
        ->label('Platform')
        ->icon(Heroicon::OutlinedRectangleStack),
    NavigationGroup::make()
        ->label('Docs')
        ->icon(Heroicon::OutlinedBookOpen),
])
```

Adapted example for a custom docs navigation item:

```php
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;

->navigationItems([
    NavigationItem::make('Project Docs')
        ->group('Docs')
        ->icon(Heroicon::OutlinedBookOpen)
        ->url(fn (): string => ProjectDocsIndex::getUrl()),
])
```

Reference links:

- https://filamentphp.com/docs/4.x/resources/overview
- https://filamentphp.com/docs/4.x/navigation

## 5. Skill Findings

### 5.1 Best built-in skills for this work

The strongest skills already available in this environment are:

- `ui-ux-pro-max`
  - best fit for section hierarchy, dashboard design, navigation clarity, content density, and visual rhythm
- `laravel-best-practices`
  - best fit for Laravel and Filament-safe implementation details
- `tailwindcss-development`
  - best fit once the Blade/Tailwind markup is redesigned
- `context7`
  - best fit for current Filament documentation lookup

### 5.2 Skills registry search

The skills search did not surface a strong Filament-specific UI skill.

Most relevant results found:

- `martinholovsky/claude-skills-generator@ui-ux-expert` with 504 installs
- `paperclipai/paperclip@design-guide` with 106 installs
- `bobmatnyc/claude-mpm-skills@shadcn-ui` with 332 installs, but this is not the right fit for a Filament panel

Recommendation:

- do not install an external skill yet
- use the built-in local skills first because they are more relevant to this codebase
- if later needed, `ui-ux-expert` is the only external result worth a second look, but its install count is still modest

## 6. What Should Change In The Product Structure

### 6.1 Main sidebar

Add a new navigation group below `Platform`:

- `Docs`

Recommended entries inside `Docs`:

- `Project Docs`
- `API Concepts`
- `Integration Checklist`

This solves two problems at once:

- the sidebar starts to reflect the product's developer-docs value
- there is now a clear place to learn, not only configure

### 6.2 Project sub-navigation

The current record-level label `Integration` should be renamed.

Better labels:

- `Developer Docs`
- `API Reference`

Preferred record-level order:

- Edit Project
- Auth Settings
- Mail Settings
- Email Templates
- User Schema
- Developer Docs
- API Reference

## 7. Recommended Redesign Direction

The current page should stop trying to be an operational summary, a schema browser, and an API reference all at once.

The cleanest redesign is:

- split the current `Project Integration Details` experience into two pages

Page 1:

- `Developer Docs`
- onboarding-oriented
- explanation first
- examples first
- quick-start focused

Page 2:

- `API Reference`
- request/response focused
- full endpoint catalog
- feature-aware notes

If the team wants lower scope, the fallback is:

- keep one page
- but redesign it using tabs or a strong in-page section navigator

## 8. New Section Architecture

### 8.1 Developer Docs page

This page should be designed like product docs, not like an admin report.

Recommended sections:

#### A. Quick Start

Show only the things needed to make the first request work:

- Base API URL
- `X-Project-Key`
- Bearer token pattern
- access token TTL
- primary integration note

This section should include:

- copyable values
- a 3-step checklist
- one clear success path

#### B. First Successful Flow

Show the shortest useful flow:

- Login or Register
- receive token
- call `/me`

Use example tabs:

- `curl`
- `JavaScript fetch`
- `Laravel HTTP client`

This is the highest-value improvement because it gives the developer a fast win.

#### C. How This Project Behaves

Show feature toggles as readable product cards:

- Email Verification
- OTP
- Forgot Password
- Ghost Accounts
- Refresh Tokens

Each card should say:

- enabled or disabled
- what changes when it is enabled
- which endpoint flow is affected

#### D. Project User Contract

Explain the difference between:

- built-in auth fields
- `custom_fields`

This should be explanation-first, with one canonical example payload below it.

#### E. Custom Fields Summary

Keep the field table, but make it easier to scan:

- label and key together
- smaller number of badges
- example value visible
- clear visibility language
- link back to `User Schema`

#### F. Common Errors

Add a short troubleshooting block:

- missing `X-Project-Key`
- invalid project key
- expired token
- disabled feature
- validation error in `custom_fields`

### 8.2 API Reference page

This page should contain the deeper endpoint catalog.

Recommended sections:

- Register
- Login
- Me
- Logout
- Refresh
- Forgot Password
- Reset Password
- Send OTP
- Resend OTP
- Verify OTP
- Create Ghost Account
- Claim Ghost Account

Each endpoint block should show:

- purpose
- request example
- response example
- feature-state note
- likely failure cases

## 9. What Should Be Removed Or Moved

The current page contains useful content that belongs elsewhere.

Move out of the main docs page:

- `Project Snapshot`
- `Recent Request Activity`

Better destinations:

- `Project Snapshot`
  - compact project overview page or project edit header summary
- `Recent Request Activity`
  - observability page or side panel

Reason:

- these sections interrupt the mental flow of a developer trying to integrate
- docs pages should prioritize "how to use the API" before "how the platform is operating"

## 10. Visual Direction

The redesign should respect the existing Filament shell, but it should stop looking like a raw default install.

Recommended visual direction:

- keep the dark admin shell
- use amber as the primary brand accent because the panel already uses it
- add a secondary cool accent for instructional surfaces and code context
- make the docs hero feel intentional and product-specific
- reduce visual sameness between cards
- make code blocks feel like first-class components, not just dark rectangles

Practical UI/UX rules for this page:

- one strong hero at the top
- stronger hierarchy between major sections
- fewer large tables above the fold
- clear badges for enabled and disabled states
- shorter line lengths for explanation text
- better spacing rhythm between explanation and code
- in-page anchors or tabs on desktop
- stacked, touch-friendly layout on smaller screens

## 11. Dashboard Recommendation

Even though your main complaint is about docs and integration details, the screenshot also shows a dashboard problem.

Recommended dashboard change:

- replace default widgets with product widgets

Better widgets:

- Projects count
- Active project users
- Requests in the last 24 hours
- Auth events in the last 24 hours
- Feature adoption summary
- "Continue setup" links into project docs and settings

Why this matters:

- the dashboard is currently the first impression of the product
- if it stays generic, the panel will still feel unfinished even after the docs page improves

## 12. File-Level Plan

### Phase 1: Navigation and labeling

Likely files:

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Filament/Resources/Projects/ProjectResource.php`

Possible additions:

- `app/Filament/Pages/ProjectDocsIndex.php`
- `resources/views/filament/pages/project-docs-index.blade.php`

### Phase 2: Developer docs page

Likely files:

- `app/Filament/Resources/Projects/Pages/ProjectIntegrationDetails.php`
- `resources/views/filament/resources/projects/pages/project-integration-details.blade.php`

Possible rename:

- rename the page label from `Integration` to `Developer Docs`

### Phase 3: API reference split

Possible additions:

- `app/Filament/Resources/Projects/Pages/ProjectApiReference.php`
- `resources/views/filament/resources/projects/pages/project-api-reference.blade.php`

### Phase 4: Dashboard refresh

Likely files:

- `app/Providers/Filament/AdminPanelProvider.php`
- custom dashboard page or custom widgets under `app/Filament`

## 13. Recommended Build Order

1. Add the new `Docs` group below `Platform`.
2. Create a docs entry page so the navigation feels intentional.
3. Rename `Integration` to `Developer Docs`.
4. Rewrite the current page around quick start and explanation.
5. Move operational sections out of the docs surface.
6. Split deep endpoint reference into its own page if scope allows.
7. Replace the generic dashboard widgets.

## 14. Acceptance Criteria

The redesign is successful when:

- the sidebar includes a docs-oriented section below `Platform`
- project docs feel like guidance, not raw admin output
- the first screen of project docs helps a developer make a real request quickly
- `Project Snapshot` and `Recent Request Activity` are no longer dominant docs sections
- examples are paired with explanation
- the page feels structured and branded on desktop
- the page still works well on smaller screens
- the panel looks more like an auth product and less like a default Filament install

## 15. Final Recommendation

Do not only restyle the current `Project Integration Details` page.

The better move is:

- add a `Docs` group below `Platform`
- rename the current docs surface to `Developer Docs`
- split onboarding docs and API reference
- remove operational content from the docs flow

That approach fixes both the section problem and the UI/UX problem at the same time.
