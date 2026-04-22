# PBX Domain Mental Model Design

## Context
Current PBX/product model mixes business concepts and switch concepts in ways that create long-term inconsistency. The product needs one stable mental model where users are primary actors, extensions remain first-class internal route targets, devices can operate independently, phone numbers define external identity, and teams replace ring groups in product UX.

This design resets the model from first principles so future implementation can converge on one consistent language across backend, frontend, and telephony compilation.

## Goals
- Make **Users** the primary human actor in the product.
- Keep **Extensions** as first-class internal route targets for dialing and IVR.
- Allow **Devices** to exist independently of users for shared/desk-phone scenarios.
- Make **Phone Numbers** the source of incoming identity and outgoing caller ID selection.
- Replace product-facing **Ring Groups** with **Teams** while keeping any PBX ring-group concept internal.
- Keep product UX expressed in business language instead of PBX language.

## Non-Goals
- Rewriting the switch layer in this phase.
- Removing extensions from the system.
- Binding phone numbers directly to extensions as the primary identity model.
- Keeping direct product CRUD for ring groups.

## Final Domain Model

### Product entities
The product exposes exactly five primary entities:

1. **Users** — people, login access, permissions, optional personal extension
2. **Extensions** — internal dialable endpoints and route targets
3. **Devices** — desk phones, softphones, mobile endpoints, shared phones
4. **Phone Numbers** — external identity and inbound entry points
5. **Teams** — business grouping, permission grouping, and routing source

### Internal-only telephony projections
The system may still materialize PBX-specific artifacts, but they are not primary product entities:
- PBX ring groups generated from teams
- SIP/runtime registration state
- compiled telephony projections
- switch-specific account/materialization details

## Core Rules

### 1. Users
- A user is a person.
- A user may have **zero or one personal extension**.
- A user may have multiple devices.
- A user may have zero or more accessible phone numbers.
- A user may belong to zero or more teams.

### 2. Extensions
- An extension is an internal route target.
- Extensions exist in one shared namespace.
- An extension may be owned by exactly one of:
  - a user
  - a device
  - nobody (reserved/unassigned)
- An extension cannot belong to both a user and a device.
- User-owned extensions represent personal identity.
- Device-owned extensions represent shared/standalone endpoints.
- Unassigned extensions remain valid reserves for future use.

### 3. Devices
- A device is an endpoint, not a person.
- A device may exist without a user.
- A device may have zero or one assigned extension.
- Shared desk phones are modeled as device-owned endpoints.
- Device-owned extensions must support fully standalone call handling.
- User-owned extensions may support multiple registered devices at runtime.
- Device-owned shared extensions keep a single-device mental model.

### 4. Phone Numbers
- A phone number has two roles:
  - inbound entry point
  - outbound caller identity
- Phone numbers are not primarily bound to extensions.
- Phone numbers are granted to actors that may use them.

### 5. Teams
- A team is a business object.
- Teams group users and devices.
- Teams can receive phone-number grants.
- Teams replace ring groups in product UX.
- The backend may compile a hidden PBX ring-group projection from team state.
- Admins do not manage PBX ring groups directly in the normal product.

## Ownership and Cardinality

### User relationships
- User → 0..1 personal extension
- User → 0..n devices
- User → 0..n direct phone-number grants
- User → 0..n team memberships

### Device relationships
- Device → 0..1 extension
- Device → 0..n direct phone-number grants
- Device may optionally be linked to a user, but shared-device operation must not require a user

### Extension relationships
- Extension → owned by one of {user, device, none}
- User-owned extension → may have many registered devices at runtime
- Device-owned extension → shared/single-endpoint mental model

### Team relationships
- Team → 0..n users
- Team → 0..n devices
- Team → 0..n phone-number grants
- Team → optional hidden PBX ring-group projection

## Number Access Model
Phone-number access may be granted to:
- users
- teams
- devices

### Effective access resolution
For a user, effective accessible numbers are:
- direct user grants
- plus grants from team memberships
- deduped into one final list

For a device, effective accessible numbers are:
- direct device grants only

This keeps shared phones independent and avoids needing user indirection for device identity.

## Outgoing Call Behavior

### User-originated call
1. User initiates call from app or softphone.
2. System resolves effective number list:
   - direct user grants
   - plus team grants
3. If only one number is available, use it automatically.
4. If multiple numbers are available:
   - use the configured default automatically
   - allow user to override through a picker
5. Selected number becomes external caller ID.
6. Call still originates from the user’s personal extension and active runtime endpoint(s).

### Device-originated call
1. Shared/standalone device initiates call.
2. System resolves direct device grants.
3. Admin-configured default outgoing number is used.
4. No picker is shown on the device by default.
5. Future multi-line/key behavior is possible but outside current scope.

## Incoming Call Behavior

### Number entry
When a call reaches a phone number, the number routes to either:
- a direct extension or device shortcut
- a call flow

### Call flow destinations
A call flow may route to:
- extension
- team
- voicemail/menu/time logic/fallback

### Team destination behavior
When a product route targets a team:
1. Product stores route-to-team intent.
2. Backend resolves current team membership.
3. Backend compiles/updates hidden PBX ring-group projection.
4. Switch/runtime rings projected members.
5. Product never exposes PBX ring-group CRUD as the main model.

## Internal Dialing and Directory
- User-owned and device-owned extensions share one dialable namespace.
- Both must appear in the directory.
- UI should visibly mark ownership type so admins understand whether an extension is personal, shared, or unassigned.
- Extensions stay visible because internal dialing and IVR depend on them.

## UX Structure

### Sidebar
- Dashboard
- Phone Numbers
- Users
- Devices
- Extensions
- Teams
- Call Flows

### Users screen
- create/manage users
- assign one personal extension
- assign direct phone-number access
- assign team membership
- show effective caller-ID options
- show linked devices

### Devices screen
- create desk/softphone/mobile/shared device records
- assign extension
- assign direct phone-number access
- set default outgoing number
- support standalone shared-device operation without user login

### Extensions screen
- manage internal route targets
- show owner type: user, device, unassigned
- show registration mode: personal multi-device vs shared device

### Teams screen
- manage members (users and devices)
- manage phone-number access grants
- manage routing intent at team level
- sync hidden PBX projection automatically

### Phone Numbers screen
- manage inbound destination
- manage direct/team/device access grants
- manage outgoing identity defaults
- choose direct extension/device shortcut or call-flow routing

## Product Language Rules
Preferred product language:
- Users
- Devices
- Extensions
- Teams
- Phone Numbers
- Call Flows

Avoid exposing as primary UX language:
- ring group
- directory user
- switch projection
- SIP identity internals
- PBX implementation artifacts

## Hard Constraints
These rules must be enforced at model/service/API level:
- user may not have more than one personal extension
- extension ownership is mutually exclusive (`user_id` XOR `device_id`)
- user default outgoing number must belong to user’s effective accessible set
- device default outgoing number must belong to device’s directly granted set
- duplicate number grants collapse into one effective list
- deleting a team removes/rebuilds hidden PBX projection safely
- deleting an extension cleanly unbinds user/device relationships
- orphaned device-owned extension becomes unassigned reserve instead of being auto-deleted

## Failure Handling
- If hidden PBX projection sync fails, business record remains authoritative and is marked out-of-sync for repair/retry.
- If a user or device loses all accessible phone numbers, outgoing identity must fail clearly rather than silently picking an invalid fallback.
- If team membership changes, team routing projection must rebuild deterministically.
- If a shared device loses its device record, its extension becomes unassigned and remains reusable.

## Testing Requirements

### Domain tests
- user cannot hold second personal extension
- extension cannot belong to both user and device
- shared device works without user

### Number access tests
- user effective numbers = direct grants + team grants
- duplicates dedupe cleanly
- default outgoing number validation rejects invalid grants
- device default must belong to direct device grants

### Routing tests
- phone number routes directly to extension/device
- phone number routes to call flow
- team route compiles/updates hidden PBX projection
- deleting/updating team refreshes hidden projection safely

### UX/API tests
- normal admin APIs use product language, not PBX projection language
- extension directory shows ownership type clearly
- caller-ID picker appears only when user has multiple effective numbers
- shared device uses fixed outgoing identity without user picker

## Recommended Implementation Split
This design is too large for one implementation wave. Split into three execution phases:

### Phase 1 — Core ownership model
- enforce user personal-extension rule
- normalize extension owner semantics
- normalize device standalone behavior

### Phase 2 — Number access and caller ID model
- add user/team/device number grants
- add effective access resolution
- add default + optional picker behavior

### Phase 3 — Team projection and product UX simplification
- replace product ring-group UX with team-driven routing
- compile hidden PBX ring-group projections
- simplify navigation and product language

## Decision Summary
Final mental model:

**Extensions route calls, users use them, devices register them, phone numbers define identity, and teams coordinate routing through internal PBX projections.**
