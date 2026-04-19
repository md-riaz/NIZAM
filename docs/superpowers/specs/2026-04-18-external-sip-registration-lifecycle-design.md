# External SIP Registration Lifecycle Design

**Goal:** Define a reliable smart-restart lifecycle for external SIP gateway registrations so registration-affecting changes stop and restart the running FreeSWITCH gateway process, while non-registration changes update configuration without unnecessary churn.

**Scope:** Backend lifecycle behavior for external SIP gateway registration management, including file provisioning, FreeSWITCH command ordering, change classification, and operational feedback. UI copy may continue using "Provider" as the product label, but the technical domain object remains `Gateway`.

## Context

The current system already provisions external gateway XML and issues FreeSWITCH lifecycle commands through `GatewayProvisioningService` and `GatewayObserver`. Today, gateway updates are too coarse: updates flow through one sync path, and restart behavior is not explicitly modeled around which fields actually affect live SIP registration.

The system also no longer persists a database mirror of gateway registration sessions. The `gateway_registrations` table was removed, so live registration state is sourced from FreeSWITCH through ESL queries. That means lifecycle management must focus on:

1. desired config on disk
2. correct FreeSWITCH process lifecycle commands
3. observable operational outcomes

## Domain Language

- **Gateway** = technical backend object representing SIP gateway / external registration config
- **Provider** = product-facing UI label for admin workflows
- **Registration lifecycle** = start, restart, stop, or rescan behavior for a configured external SIP gateway

This design intentionally keeps `Gateway` as the backend/runtime concept because the object remains a SIP registration/trunk configuration, not a broader vendor/business abstraction.

## Requirements

### 1. Smart restart behavior

The system must restart only when registration-affecting fields change.

Registration-affecting fields are:
- `host`
- `port`
- `username`
- `password`
- `realm`
- `proxy`
- `register_proxy`
- `transport`
- `profile`
- `register`
- `is_active`

Changes outside this strict set must not trigger a registration restart.

### 2. Full restart on registration-affecting changes

When any registration-affecting field changes, the running FreeSWITCH gateway must not rely on passive config reload alone. The system must perform a restart lifecycle so new credentials and transport/auth settings actually apply.

For restart-class changes, lifecycle must include:
- config write/update
- `reloadxml`
- `killgw`
- `rescan`
- `startgw` when final state requires active registration

### 3. Non-registration config updates

When only non-registration fields change, the system must update generated XML and refresh FreeSWITCH configuration without stopping and restarting the running gateway registration.

Examples include:
- codec preferences
- DTMF settings
- SRTP settings
- other non-registration XML params
- product-facing labels that do not affect runtime gateway identity

### 4. Stop behavior

When a gateway becomes inactive, registration is disabled, or the gateway is deleted, the system must stop the running gateway registration and prevent it from being restarted.

### 5. Stable runtime identity

Gateway runtime identity must remain stable and technical. The lifecycle system must continue using a stable identifier like `v_<uuid>`, not a mutable product-facing name.

### 6. Operational visibility

Lifecycle actions must be observable in logs and service results so admins and operators can tell whether a save caused:
- restart
- start
- stop
- rescan only
- no start because required credentials were incomplete

## Proposed Architecture

### A. `GatewayLifecyclePlanner`

Add a new service responsible for deciding the lifecycle action from model state transitions.

**Inputs:**
- current `Gateway`
- original persisted values for update flows
- event type: create, update, delete

**Outputs:**
- lifecycle action enum/value such as:
  - `start`
  - `restart`
  - `stop`
  - `rescan_only`
  - `remove_only`
  - `noop`

**Responsibility:**
- classify changes using the strict registration-affecting field set
- decide old-profile vs new-profile handling for profile moves
- decide whether registration can start based on final desired state and credential completeness

### B. `GatewayProvisioningService`

Keep this as orchestration layer, but narrow its responsibilities.

**Responsibilities:**
- render gateway XML
- write or delete gateway XML files
- ask planner for lifecycle action
- call FreeSWITCH executor in correct order
- return/log action summary

It should stop embedding implicit lifecycle decisions directly in generic sync logic.

### C. `FreeSwitchGatewayLifecycleExecutor`

Introduce a dedicated executor for FreeSWITCH command sequencing.

**Responsibilities:**
- run `reloadxml`
- run `killgw`
- run `rescan`
- run `startgw`
- handle old-profile/new-profile cases
- centralize logging and command result capture

This keeps command ordering isolated and testable.

### D. `GatewayObserver`

Keep observer thin.

**Responsibilities:**
- on create/update/delete, delegate to provisioning service
- do not contain lifecycle classification logic

## Lifecycle Rules

### Create

#### Create active registering gateway
Condition:
- `is_active = true`
- `register = true`
- required credentials present

Actions:
1. write XML file
2. `reloadxml`
3. `sofia profile <profile> rescan`
4. `sofia profile <profile> startgw <gateway>`

#### Create active non-registering gateway
Condition:
- active gateway exists but final state does not require registration startup

Actions:
1. write XML file
2. `reloadxml`
3. `sofia profile <profile> rescan`
4. no `startgw`

#### Create inactive gateway
Actions:
- ensure runtime XML is absent or not activated
- do not start gateway

### Update

#### Registration-affecting fields changed
Examples:
- new password
- host/transport/realm change
- register toggled
- active state toggled
- profile moved

Default actions:
1. write updated XML in final target location
2. `reloadxml`
3. `sofia profile <affected profile> killgw <gateway>`
4. `sofia profile <affected profile> rescan`
5. `sofia profile <final profile> startgw <gateway>` if final state requires registration

#### Non-registration fields changed
Examples:
- codecs
- DTMF mode
- SRTP mode
- similar non-registration XML params

Actions:
1. rewrite XML
2. `reloadxml`
3. `sofia profile <profile> rescan`
4. no `killgw`
5. no `startgw`

#### Transition to inactive or register=false
Actions:
1. `reloadxml`
2. `sofia profile <profile> killgw <gateway>`
3. remove XML or rewrite into non-registering final form depending on desired state
4. `sofia profile <profile> rescan`
5. do not start

### Delete

Actions:
1. `sofia profile <profile> killgw <gateway>`
2. delete XML
3. `reloadxml`
4. `sofia profile <profile> rescan`

## Special Cases

### Profile change

If `profile` changes:
- kill gateway on old profile
- write XML under new profile context/location
- reload XML
- rescan affected profile(s)
- start only on new profile when final state requires registration

The planner must preserve both old and new profile values so executor can sequence this correctly.

### Incomplete credentials

If `register = true` but required credentials are incomplete:
- do not call `startgw`
- keep desired file state consistent with system policy
- log and surface an actionable lifecycle outcome such as `registration_not_started_missing_credentials`

### Runtime identity stability

The system must not derive FreeSWITCH runtime gateway identity from mutable UI names. Continue using stable identifier format like `v_<uuid>`.

## Command Ordering Principles

### Restart path
For registration-affecting changes, restart sequence must ensure old in-memory gateway state is terminated and new config is available before startup.

Target order:
1. write desired XML state
2. `reloadxml`
3. `killgw`
4. `rescan`
5. `startgw` if final state requires active registration

### Rescan-only path
For non-registration changes:
1. write desired XML state
2. `reloadxml`
3. `rescan`
4. no kill/start

### Stop path
For disable/delete style changes:
1. `killgw`
2. remove or deactivate file state as needed
3. `reloadxml`
4. `rescan`

Implementation may normalize exact stop ordering slightly if FreeSWITCH command behavior requires it, but restart flows must preserve the guarantee that changed credentials/settings do not rely on passive reload alone.

## Operational Feedback

Provisioning/lifecycle operations should return a summary payload suitable for logs, diagnostics, and future admin surfacing. Example shape:

- `action`: `start|restart|stop|rescan_only|noop`
- `gateway_id`
- `profile`
- `old_profile`
- `started`: boolean
- `stopped`: boolean
- `reason`: e.g. `registration_fields_changed`, `non_registration_fields_changed`, `deleted`, `missing_credentials`
- command result details for troubleshooting

Logs should always include:
- gateway id
- action
- affected profile(s)
- command failure details if any command fails

## Testing Strategy

### Unit tests for planner
Cover:
- create active registering gateway => `start`
- update password => `restart`
- update host => `restart`
- update codec only => `rescan_only`
- set `register` false => `stop`
- set inactive => `stop`
- delete => `stop/remove`
- move profile => restart with old/new profile context
- register true with missing credentials => no start outcome

### Unit tests for executor
Cover exact command mapping and order for:
- start
- restart
- rescan only
- stop
- profile move

### Unit tests for provisioning service
Cover integration of planner + file operations + executor:
- XML created/updated/removed correctly
- restart path calls executor correctly
- rescan-only path does not kill/start
- stop path removes runtime state

## Non-Goals

This design does not introduce:
- a new `Provider` backend model
- a persistent database mirror of live registrations
- asynchronous lifecycle queueing/retries in this pass
- full UI redesign for SIP lifecycle management

## Recommended Implementation Direction

Implement the smallest safe version first:
1. add `GatewayLifecyclePlanner`
2. add executor or equivalent command-isolation layer
3. refactor `GatewayProvisioningService` to use planner + executor
4. expand unit coverage for lifecycle transitions
5. optionally expose lifecycle outcome summaries in admin save responses later

## Expected Outcome

After implementation:
- password/realm/host/profile changes reliably restart the external SIP gateway registration
- non-registration updates no longer flap healthy registrations unnecessarily
- deletes/deactivation stop runtime state cleanly
- lifecycle behavior becomes explicit, testable, and operator-visible
