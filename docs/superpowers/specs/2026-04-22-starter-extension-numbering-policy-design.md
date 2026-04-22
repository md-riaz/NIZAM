# Starter Extension and Numbering Policy Design

## Context

New organizations currently provision baseline defaults such as schedules and starter call flow, but they do not receive a usable starter extension. This leaves brand-new accounts without an immediately actionable endpoint. At the same time, the extension domain model and UI still expose FreeSWITCH-oriented `directory_*` terminology in business-facing surfaces, which leaks telephony internals into product language.

Goal: when a new organization is created, it should automatically receive exactly one starter extension, allocated from an enforced organization numbering plan. The numbering plan must live in organization settings rather than code constants. Business-facing extension naming should move away from FreeSWITCH directory terms, while telephony-specific translation remains enclosed in the FreeSWITCH integration layer.

## Goals

- Automatically create exactly one starter extension for each newly provisioned organization.
- Store extension numbering policy in organization settings.
- Enforce organization extension ranges for both auto-provisioning and manual create/edit flows.
- Replace business-facing `directory_*` naming with business/domain names.
- Keep FreeSWITCH-specific field mapping internal to telephony-facing code.

## Non-Goals

- Building a multi-step organization onboarding wizard.
- Exposing editable numbering policy UI in this first change unless already required by existing settings surfaces.
- Designing complete numbering enforcement for every object type right now.
- Refactoring unrelated telephony provisioning logic.

## Recommended Approach

### 1. Add organization numbering policy defaults

Add numbering policy to organization settings with an initial extension range:

- `settings.numbering.extension.start = 101`
- `settings.numbering.extension.end = 500`

The structure should be extensible so future ranges can be added without redesigning settings shape, for example ring groups, queues, and IVR/service numbers.

Provision these defaults as part of organization bootstrap so every organization has a valid numbering policy before starter extension provisioning runs.

### 2. Add starter extension provisioning during organization creation

Extend organization provisioning so that after default settings exist, a starter extension service runs inside the same provisioning transaction.

Responsibilities of the starter extension provisioning service:

- Read the organization numbering policy.
- Find the first free extension inside the configured extension range.
- Create exactly one extension.
- Reuse the same creation path and invariants as normal extension creation wherever practical.

Starter extension defaults:

- Extension number: first available within `101-500`.
- Name: starter business-facing default such as `Main User`.
- Strong generated SIP/auth password.
- Voicemail enabled with generated or bootstrap-safe default PIN.
- Active by default.

If no free extension exists in the allowed range, organization provisioning must fail and roll back rather than leaving a half-configured organization.

### 3. Enforce extension ranges in backend business rules

Range enforcement belongs in backend validation/business logic, not frontend-only checks.

Extension create and edit flows must reject values outside the configured organization extension range with a clear validation message such as:

`Extension must be between 101 and 500 for this organization.`

This enforcement must apply to:

- automatic starter extension provisioning
- manual extension creation
- manual extension edits
- any future internal extension creation paths

Avoid duplicating range logic in multiple controllers. Centralize it in a reusable validation/service layer so all entry points behave consistently.

### 4. Rename extension business-facing fields away from `directory_*`

Current extension business/domain fields use FreeSWITCH-oriented names such as:

- `directory_first_name`
- `directory_last_name`

These should become business-facing names, for example:

- `first_name`
- `last_name`

A derived `display_name` can remain computed rather than stored if current product behavior already supports composition from first and last name.

This rename should propagate through:

- database schema / model surface
- request validation
- API resources
- frontend forms
- seeders and factories
- tests

Telephony-facing code that still needs FreeSWITCH directory semantics should perform translation inside the telephony layer only. Business logic should no longer talk in `directory_*` terms.

### 5. Keep FreeSWITCH mapping enclosed

Any FreeSWITCH XML, provisioning, or adapter logic that still expects directory-oriented fields should map from business-facing extension fields at the boundary.

Boundary rule:

- Application/business layers use business names.
- FreeSWITCH integration layer translates to FreeSWITCH-native representation.

This keeps PBX internals from surfacing in UI, API, and business services while preserving telephony compatibility.

## Data Flow

### Organization creation

1. Organization controller creates organization.
2. Bootstrap service provisions default settings, including numbering policy.
3. Entrypoint/bootstrap provisioning invokes starter extension provisioning.
4. Starter extension provisioning selects first free extension in configured range.
5. Extension is created.
6. Remaining starter organization assets continue provisioning.
7. Transaction commits only if all provisioning succeeds.

### Extension create/edit

1. Request enters create or update flow.
2. Validation/service resolves organization numbering policy.
3. Submitted extension number is checked against allowed range.
4. Out-of-range values are rejected.
5. Valid values continue through normal persistence and telephony sync flow.

## Migration Strategy

Because `directory_*` fields are already in use, this change needs an explicit migration path.

Recommended migration approach:

1. Add new business-facing columns or perform a careful rename migration.
2. Backfill existing extension data from old fields to new names.
3. Update application code to read/write only new business-facing names.
4. Update telephony integration layer to translate new names into any FreeSWITCH directory-specific output.
5. Remove old field usage from business-facing code.

If zero-downtime deployment constraints matter in this environment, prefer staged compatibility migration over immediate destructive rename. If deployments are single-step and tightly controlled, a direct rename may be acceptable.

## Validation and Error Handling

- If numbering policy is missing, bootstrap must seed defaults before extension provisioning.
- If numbering policy is invalid, provisioning must fail clearly.
- If the extension range is exhausted, organization creation must fail and roll back.
- If extension creation fails for any reason, organization provisioning must roll back.
- Manual create/edit errors must surface range information clearly to the user.

## UI / Product Language Changes

Extension form language should be modern SaaS PBX language.

Examples:

- `Directory First Name` -> `First Name`
- `Directory Last Name` -> `Last Name`
- related descriptions should describe user/caller identity, not FreeSWITCH internals

This language cleanup is part of the same design because the rename is not only cosmetic; it reflects a domain boundary decision.

## Testing Strategy

### Backend tests

- New organization receives exactly one starter extension.
- Starter extension uses first free number in `101-500`.
- Provisioning skips occupied numbers and picks next available number.
- Organization creation fails cleanly when range is exhausted.
- Manual extension create outside allowed range fails.
- Manual extension edit outside allowed range fails.
- Existing extension records survive business-field rename/backfill.

### Frontend tests

- Extension create/edit forms display business-facing labels.
- Existing extension data hydrates correctly after rename.
- Validation messages display enforced range information cleanly if surfaced by API.

### Integration checks

- Telephony generation/mapping still emits compatible FreeSWITCH-facing values after rename.
- Starter extension appears in org extension list immediately after org creation.

## Files Likely Affected

Backend:

- `backend/app/Services/Organization/OrganizationBootstrapService.php`
- organization provisioning service responsible for starter assets
- extension validation / service logic
- extension controller paths if validation remains there today
- extension model / resources / requests
- database migration(s)
- seeders and factories
- FreeSWITCH mapping / telephony generation code that currently consumes `directory_*`

Frontend:

- `frontend/src/pages/admin/ExtensionFormPage.tsx`
- extension-related types and API consumers
- any extension list/detail UIs that still expose directory terminology

## Tradeoffs

### Why settings-backed numbering policy

Pros:

- avoids hardcoded numbering assumptions
- supports future object ranges
- keeps provisioning deterministic and configurable

Cons:

- slightly more bootstrap complexity
- requires shared helper/service for consistent rule enforcement

### Why business rename now

Pros:

- fixes product-language leak at root
- creates clean boundary between product domain and telephony engine
- avoids adding more features on top of legacy naming

Cons:

- broader migration scope than UI relabel only
- requires careful update across backend, frontend, tests, and telephony mapping

## Recommendation

Implement the starter extension as part of organization provisioning using an organization-scoped numbering policy stored in settings, with initial enforced extension range `101-500`. At the same time, rename extension business-facing fields away from `directory_*` and isolate FreeSWITCH-specific terminology to telephony integration boundaries only.

This gives new organizations a usable starting point immediately, establishes a proper configurable numbering plan, and improves the product boundary so PBX internals stop leaking into application business logic.
