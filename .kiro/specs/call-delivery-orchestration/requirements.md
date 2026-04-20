# Requirements Document: call-delivery-orchestration

## Overview

NIZAM needs one canonical runtime delivery pipeline for all human-target inbound routes. The system must stop treating direct extension, ring group, queue agent, forwarded-number, and people-targeting flow branches as separate delivery implementations and instead resolve them through a shared orchestration path that controls reachability, push wake-up, late registration, first-answer-wins behavior, and loser cleanup consistently.

## Requirements

### Requirement 1: Canonical human-target orchestration entrypoint

User story: As a platform maintainer, I want all human-target inbound routes to enter one shared delivery orchestration path so that call delivery behavior stays consistent across extensions, ring groups, queues, and flows.

#### Acceptance Criteria

1. WHEN an inbound call targets a direct extension THEN the system SHALL hand the call to the shared delivery orchestration entrypoint instead of compiling direct runtime reachability entirely into a static `bridge user/<extension>@<domain>` action.
2. WHEN an inbound call targets a ring group THEN the system SHALL hand the call to the same shared delivery orchestration entrypoint used by direct extensions.
3. WHEN an inbound call targets queue or agent delivery THEN the system SHALL hand the call to the same shared delivery orchestration entrypoint used by direct extensions.
4. WHEN a DID, time condition, or flow branch ultimately targets people THEN the system SHALL hand the resolved human-target route to the same shared delivery orchestration entrypoint.
5. IF a route resolves to a non-human destination such as voicemail or IVR THEN the system SHALL bypass human-target orchestration and preserve the existing non-human routing behavior.
5a. IF a ring-group route includes a fallback destination outside the human-target orchestration scope THEN the system SHALL preserve the existing fallback evaluation semantics tied to `${originate_disposition}`.
6. WHEN dialplan compilation prepares a human-target route THEN the compiler SHALL pass route metadata including target type, target identifier, and call session correlation data rather than acting as the sole runtime reachability engine.
7. WHEN the shared delivery orchestration entrypoint is invoked for a caller leg THEN it SHALL park or otherwise hold the caller leg in a controlled state, create or load the correlated `CallSession`, persist the delivery target metadata onto session state, and invoke orchestration exactly once for that active inbound leg.
8. IF FreeSWITCH re-enters the shared delivery orchestration entrypoint for the same active caller leg or call UUID THEN the system SHALL behave idempotently and SHALL NOT duplicate active delivery attempts or restart orchestration after a winner is already committed.

### Requirement 2: Runtime endpoint binding model

User story: As a platform maintainer, I want a first-class runtime endpoint model so that mobile delivery, desk-phone delivery, and forwarded-number delivery can be managed without overloading provisioning data.

#### Acceptance Criteria

1. THE system SHALL provide a runtime endpoint binding model separate from `DeviceProfile`.
2. THE endpoint binding model SHALL support organization-scoped association to extensions, agents, or both where appropriate.
3. THE endpoint binding model SHALL support runtime delivery attributes including endpoint type, device identity, push tokens, platform, app version, enabled state, push capability state, late-join allowance, forward number, and PSTN confirmation policy.
4. IF an endpoint is disabled THEN the orchestration pipeline SHALL exclude it from candidate delivery attempts.
5. IF an endpoint is marked push-capable THEN the system SHALL store the token material required to send wake notifications.
6. THE system SHALL NOT use `DeviceProfile` as the source of truth for runtime push reachability or mobile binding state.

### Requirement 3: Shared delivery resolution and planning pipeline

User story: As a platform maintainer, I want route resolution, endpoint expansion, reachability checks, and offer planning separated into shared services so that policy is centralized and testable.

#### Acceptance Criteria

1. THE system SHALL resolve route targets into a canonical target set before evaluating endpoint reachability.
2. THE system SHALL expand canonical targets into endpoint candidates using the runtime endpoint binding model.
3. THE system SHALL evaluate reachability using live registration information, short-lived cached state, and endpoint capabilities.
4. WHEN an endpoint has an online SIP registration THEN the planner SHALL place it in the immediate SIP offer wave.
5. WHEN an endpoint is push-capable but not currently registered THEN the planner SHALL place it in the push wake wave without delaying immediate online SIP ringing.
6. WHEN an endpoint has a forwarded PSTN destination THEN the planner SHALL include it only under the configured PSTN policy and confirmation rules.
7. THE system SHALL use the same planning logic regardless of whether the candidate originated from extension delivery, ring group membership, queue membership, or flow resolution.

### Requirement 4: Call-session-centered orchestration state

User story: As a platform maintainer, I want delivery orchestration state to hang off the existing call session aggregate so that delivery races and call lifecycle events can be reconciled safely.

#### Acceptance Criteria

1. THE system SHALL use `CallSession` as the aggregate root for runtime delivery orchestration.
2. THE system SHALL persist per-endpoint offer records for a call session as delivery attempts.
3. EACH delivery attempt SHALL record the endpoint binding, attempt type, status, associated FreeSWITCH leg UUID when present, start time, answer time when present, end time when present, and failure reason when present.
4. THE system SHALL support traceability from call session to delivery attempts for debugging and audit.
5. THE system SHALL use `CallSession.lock_version` or equivalent optimistic concurrency control to support race-safe winner selection.

### Requirement 5: Immediate online SIP ringing

User story: As a caller, I want currently online SIP devices to ring immediately so that reachable users are not delayed by mobile wake-up behavior.

#### Acceptance Criteria

1. WHEN a target has one or more currently registered SIP contacts THEN the system SHALL initiate SIP ringing for those contacts immediately.
2. WHEN immediate SIP ringing is possible THEN the system SHALL NOT wait for push wake-up before starting those SIP offers.
3. WHEN multiple online SIP endpoints exist for the same human target THEN the system SHALL support offering them in the same shared orchestration path.
4. IF an online SIP attempt cannot be created THEN the system SHALL mark the attempt with a failure status and continue evaluating other eligible branches.

### Requirement 6: Parallel mobile push wake-up and late join

User story: As a mobile user, I want my dormant app to be woken in parallel and allowed to join shortly after registration so that I can still receive inbound calls while the app is sleeping.

#### Acceptance Criteria

1. WHEN a target has dormant push-capable mobile endpoints THEN the system SHALL send wake push notifications in parallel with immediate SIP ringing.
2. WHEN a push-capable mobile device registers within the configured wake window for an active call THEN the system SHALL allow a late SIP leg to be added for that device.
3. IF a push-capable device registers after the wake window expires THEN the system SHALL NOT add a late SIP leg for that call.
4. IF a winner has already been committed for the call THEN the system SHALL NOT add new late-join legs.
5. THE system SHALL record push-send outcomes and late-join decisions for observability.

### Requirement 7: First confirmed answer wins exactly once

User story: As a platform maintainer, I want a deterministic first-answer-wins rule so that mixed endpoint races resolve safely across SIP, mobile, and PSTN branches.

#### Acceptance Criteria

1. WHEN multiple branches answer near-simultaneously THEN the system SHALL elect exactly one winner for the call session.
2. THE system SHALL commit the winner using race-safe state transition logic tied to the call session aggregate.
3. AFTER a winner is committed THEN all other active branches SHALL be marked as losing, cancelled, failed, or timed out deterministically.
4. AFTER a winner is committed THEN the system SHALL bridge only the winning branch to the caller leg.
5. THE system SHALL preserve durable winner metadata sufficient to prevent duplicate winner commits from later events.

### Requirement 8: Answered-elsewhere and loser cleanup

User story: As a callee using multiple devices, I want losing branches and mobile wake-ups to be cancelled consistently so that my devices do not keep ringing after the call is already answered.

#### Acceptance Criteria

1. AFTER a winner is committed THEN the system SHALL cancel all losing active SIP and PSTN branches.
2. AFTER a winner is committed THEN the system SHALL send answered-elsewhere or cancel notifications to non-winning mobile devices when supported.
3. IF a push wake-up is still in progress after another branch wins THEN the system SHALL mark the push branch as non-winning and suppress further late-join offers.
4. THE system SHALL record loser cleanup outcomes for observability and troubleshooting.

### Requirement 9: PSTN forward safety

User story: As a platform maintainer, I want forwarded PSTN branches to be safe against voicemail takeover so that external forwarding does not incorrectly win calls.

#### Acceptance Criteria

1. WHEN a forwarded PSTN endpoint is offered THEN the system SHALL support answer confirmation before allowing that branch to win.
2. IF a forwarded PSTN branch answers but confirmation is not received THEN the system SHALL NOT elect that branch as the winner.
3. THE system SHALL allow PSTN waves to be delayed behind online SIP or push waves based on configured delivery policy.
4. THE system SHALL record PSTN confirmation failures or voicemail-like non-confirmed answers as non-winning outcomes.

### Requirement 10: Event-driven orchestration updates

User story: As a platform maintainer, I want the existing FreeSWITCH event ingestion path to drive orchestration state so that delivery decisions stay synchronized with live call activity.

#### Acceptance Criteria

1. WHEN `CHANNEL_CREATE` is received for an orchestrated call THEN the system SHALL create or attach the corresponding call session context.
2. WHEN `CHANNEL_ANSWER` is received for a delivery attempt THEN the system SHALL evaluate that attempt for winner election.
3. WHEN `CHANNEL_BRIDGE` is received for the winning branch THEN the system SHALL mark the call session as bridged and record the winning bridge state.
4. WHEN `CHANNEL_HANGUP_COMPLETE` is received THEN the system SHALL finalize related delivery attempts and cleanup state.
5. WHEN `sofia::register` is received THEN the system SHALL update reachability state and evaluate eligible late-join behavior for active calls within wake window.
6. WHEN `sofia::unregister` is received THEN the system SHALL update reachability state so future delivery planning reflects the current registration picture.

### Requirement 11: Reachability cache

User story: As a platform maintainer, I want a short-lived reachability cache so that delivery planning can make fast online/offline decisions without treating dialplan as the only source of truth.

#### Acceptance Criteria

1. THE system SHALL maintain a short-lived reachability cache keyed by organization and user or endpoint identity.
2. WHEN registration events are received THEN the system SHALL refresh or invalidate the reachability cache accordingly.
3. WHEN reachability planning runs and cache data is unavailable or stale THEN the system SHALL fall back to a live registration visibility path.
4. THE reachability cache SHALL inform planning decisions but SHALL NOT replace durable orchestration state in `CallSession` and delivery attempts.

### Requirement 12: Organization-scoped mobile device management APIs

User story: As a organization application, I want authenticated APIs for runtime mobile device binding and token rotation so that mobile endpoints can participate in call delivery safely.

#### Acceptance Criteria

1. THE system SHALL provide authenticated organization-scoped endpoints for mobile device registration, update, deletion, token refresh, heartbeat, and capability updates.
2. THE mobile device registration endpoint SHALL accept runtime fields including extension reference, device UUID, platform, push token, VoIP push token when applicable, app version, push-enabled state, SIP background support, and late-join preference.
3. WHEN a device refreshes a token THEN the system SHALL rotate the stored token without requiring a new logical device record when the device identity is unchanged.
4. WHEN a device heartbeat or capability update is received THEN the system SHALL update runtime endpoint state without mutating provisioning-only `DeviceProfile` data.
5. ALL mobile device API operations SHALL be organization-scoped and protected by existing authenticated API middleware.

### Requirement 13: No duplicated push decision logic

User story: As a platform maintainer, I want push and reachability policy to live in one place so that behavior does not drift across route handlers.

#### Acceptance Criteria

1. THE system SHALL centralize push-wake eligibility logic in the shared orchestration pipeline.
2. THE system SHALL NOT duplicate push decision logic independently inside extension routing, ring-group compilation, queue delivery, and flow handlers.
3. THE system SHALL NOT hardcode mobile push policy directly into `compileRingGroupActions()`.
4. THE system SHALL NOT create separate first-answer-wins implementations for extension delivery, ring groups, queue delivery, and forwarded-number handling.
