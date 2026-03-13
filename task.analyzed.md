# NIZAM Task Analysis

## Scope Reviewed
- `task.md`
- `task1.md`
- `task3.md`
- `task4.md`
- `tasks.master.md`

## Executive Summary
These files describe a coherent refactor path for turning NIZAM from a mixed PBX + admin UI app into an API-first communications control plane built around a workflow engine.

The combined direction is clear:
- remove backend UI concerns
- stabilize the current platform first
- introduce a versioned flow model
- execute calls as stateful workflows
- separate raw telephony events from workflow logic
- centralize policy domains such as schedules, holidays, teams, and number routing

This is not five different ideas. It is one architecture described from different angles.

---

## Core Architectural Direction

### 1. Product Reframing
NIZAM should stop behaving like a traditional PBX admin panel and become:

**Telephony infrastructure + workflow engine + policy engine + API**

The product is not FreeSWITCH.
The product is communication automation.

### 2. Strong Separation of Concerns
The files consistently push for strict boundaries:
- **Media runtime** handles SIP, RTP, bridging, signaling
- **Event normalization** converts noisy ESL signals into clean domain events
- **Workflow engine** advances call state through nodes and edges
- **Policy engines** answer reusable business questions like schedule state or team routing
- **Application API** exposes clean domain concepts to UI and integrations

### 3. UI Removal as a Strategic Move
The proposed backend should stop rendering HTML except required infrastructure endpoints.
Recommended removals:
- `/ui/...` routes
- Blade views
- UI-oriented controllers

Recommended retained web endpoints:
- `/freeswitch/xml-curl`
- `/provision/{mac}`

That means the backend becomes a control plane, not an admin website.

---

## What Each File Contributes

### `task.md`
Defines the refactor program at a high level:
- freeze current state with branch + tag
- remove UI responsibility
- version API under `/api/v1`
- move toward service boundaries and domain structure
- introduce the flow engine core
- normalize events
- add observability
- split UI into a future separate frontend

This is the roadmap document.

### `task1.md`
Defines the **database model** for the flow system:
- `flows`
- `flow_versions`
- `flow_nodes`
- `flow_edges`
- `call_sessions`
- `call_trace_events`
- `numbers`
- `schedules`
- `schedule_rules`
- `holidays`
- `teams`
- `team_members`
- `extensions`
- `voicemail_boxes`
- `webhooks`

This is the schema foundation.

### `task3.md`
Defines the **runtime execution model**:
- calls as state machines
- shared `CallContext`
- `NodeHandler` contract
- immediate vs async nodes
- pause/resume execution
- edge resolution
- trace logging
- scaling with workers and queues

This is the execution engine design.

### `task4.md`
Defines the critical **architectural warning**:
- do not mix raw FreeSWITCH events with workflow logic
- normalize telephony events into domain events first
- use a media control service instead of letting nodes fire direct FreeSWITCH commands everywhere

This is the anti-chaos rulebook.

### `tasks.master.md`
Defines the **final refined layered model** with policy engines:
- centralized schedules
- reusable holidays
- flows reacting to schedule states, not hand-built time logic
- separation of configuration domains
- user experience simplified by reusable policy objects

This is the mature target architecture.

---

## Consolidated Target Architecture

```text
UI / API Clients
    ↓
Application API
    ↓
Workflow Engine
    ↓
Policy Engines
  - Schedule Engine
  - Team Routing Engine
  - Number Routing Service
  - Permissions Service
    ↓
Event Bus / Queue
    ↑
Event Normalizer ← ESL Events ← FreeSWITCH
    ↓
Media Control Service → FreeSWITCH Commands
```

This is the strongest synthesis of all files.

---

## Main Design Principles Repeated Across the Files

### A. Version Everything That Affects Runtime
Flows must be immutable once published.
Running calls must bind to a specific `flow_version_id`.

That avoids runtime drift and mid-call behavior changes.

### B. Calls Are Stateful Workflows
A call is not just a dialplan execution.
It is a tracked workflow session with:
- current node
- flow version
- variables/context
- wait state
- trace history

### C. Policy Is Centralized
Schedules, holidays, teams, and number routing should not be duplicated inside flows.
Flows should ask policy engines for answers, then branch on results.

### D. Raw Telephony Must Be Normalized
The workflow engine should consume clean events like:
- `call.answered`
- `call.hangup`
- `menu.selection`
- `call.timeout`

Not raw ESL noise.

### E. Observability Is a First-Class Feature
`call_trace_events` is not optional.
It is core product value because it enables:
- debugging
- replay
- failure analysis
- future visual call history

---

## Recommended Build Order

Based on all files and the follow-up decisions, this is the cleanest implementation sequence.

### Phase 0: Stabilize
1. create branch `refactor/api-first-core`
2. tag current state `v0.1-pbx-platform`
3. confirm main remains deployable

### Phase 1: Strip UI From Backend
1. remove `/ui` routes
2. remove Blade-based UI controllers
3. remove Blade views
4. keep only infrastructure web endpoints
5. move auth fully to API if needed

### Phase 2: Introduce Structural Boundaries
1. version API under `/api/v1`
2. create `app/Domain/`
3. move business logic out of controllers into services
4. add repositories only where they actually reduce coupling

### Phase 3: Build the Call Session and Event Spine First
This is the actual execution backbone and should come before advanced flow features.

1. create runtime tables:
   - `call_sessions`
   - `call_events`
   - `call_trace_events`
2. store the minimum durable call state:
   - `call_uuid`
   - `tenant_id`
   - `number`
   - `flow_id`
   - `flow_version_id`
   - `state`
   - `started_at`
   - `ended_at`
3. implement ESL listener
4. implement event normalizer
5. publish normalized events to queue/event bus
6. build call event processor
7. define idempotent normalized event handling

Without this spine, the rest of the platform becomes difficult to reason about or debug.

### Phase 4: Number Routing
Before full workflows, build the entry point that maps numbers to flows.

1. create `numbers`
2. resolve inbound number to `flow_id`
3. on `xml_curl` request:
   - resolve number
   - create call session
   - bind active `flow_version_id`
   - start flow execution

### Phase 5: Add Flow Schema and Runtime Core
1. create `flows`
2. create `flow_versions`
3. create `flow_nodes`
4. create `flow_edges`
5. define `CallContext`
6. define `NodeHandler` interface
7. build `NodeHandlerFactory`
8. implement minimal starter nodes only:
   - start
   - schedule_check
   - menu
   - ring_team
   - voicemail
   - hangup
9. build edge resolution
10. build trace writer
11. enforce immutable published versions

### Phase 6: Build the Schedule Engine
1. create:
   - `holiday_calendars`
   - `holidays`
   - `schedules`
   - `schedule_rules`
   - `schedule_breaks`
   - `schedule_exceptions`
2. implement `ScheduleEngine.evaluate(schedule_id, datetime, timezone)`
3. return deterministic states:
   - `holiday`
   - `exception`
   - `break`
   - `open`
   - `closed`
4. integrate with `ScheduleCheckNode`
5. make timezone mandatory per schedule or tenant context

### Phase 7: Build Team Routing
1. create `teams`
2. create `team_members`
3. support initial strategies:
   - simultaneous
   - round_robin
   - priority
4. implement `RingTeamNode`
5. route telephony commands through media control service, not directly from arbitrary business logic

### Phase 8: Strengthen Observability and Debugging
1. expand `call_trace_events`
2. record every execution step and branch decision
3. record errors and fallback transitions
4. make call replay/debugging possible from trace history

### Phase 9: Add Webhooks and Automation
1. create `webhooks`
2. create `event_subscriptions` if needed
3. emit events such as:
   - `call.completed`
   - `voicemail.received`
4. keep webhook delivery outside core node execution path where possible

### Phase 10: API Surface Cleanup
Expose domain-first endpoints such as:
- `/api/v1/flows`
- `/api/v1/numbers`
- `/api/v1/schedules`
- `/api/v1/teams`
- `/api/v1/calls`
- `/api/v1/events`

### Phase 11: Future UI
After API and runtime stabilize, build separate frontend repo.
The UI should become a visual editor for `flow_nodes`, not the source of truth for business logic.

---

## Key Strengths in the Proposed Architecture

### 1. Safe Deployments
Flow versioning avoids breaking live calls when logic changes.

### 2. Cleaner Mental Model
Calls move through a graph. That is easier to reason about than large static dialplans.

### 3. Horizontal Scalability
If call state lives in durable storage and events are queued, multiple workers can process execution safely.

### 4. Easier Debugging
Trace events plus normalized domain events make failures explainable.

### 5. Better Product Evolution
Once nodes and policy engines exist, adding AI, CRM, webhook, SMS, or analytics features becomes additive instead of architectural surgery.

---

## Gaps / Things Not Yet Fully Defined
These files are strong directionally, but a few implementation details still need concrete decisions.

### 1. Persistence Strategy for Runtime State
Not fully defined:
- DB only
- Redis + DB
- queue semantics
- idempotency model for event consumption

Recommendation:
- DB as durable truth
- queue for async processing
- Redis as optional runtime cache / lock coordinator

This hybrid model is the most practical default.

### 2. Concurrency Control
You need a hard rule for preventing two workers from advancing the same call session simultaneously.

Problems:
- two events modifying the same call state
- retries or duplicate delivery processing the same call twice
- flow edits while old calls are still running

Recommendation:
- lock per `call_uuid` using Redis or DB row locking
- add optimistic locking or a version column on `call_sessions`
- keep every running call bound to immutable `flow_version_id`
- make event processors idempotent
- store dedupe key per event

### 3. Node Config Validation
`config_json` is flexible but dangerous without schemas.

Recommendation:
- node-type-specific config validators
- publish-time flow validation before activation
- fail validation before publish, not during live calls

This is now partially implemented with:
- `MenuNodeValidator`
- `RingTeamNodeValidator`
- `ScheduleCheckNodeValidator`
- `VoicemailNodeValidator`
- `FlowValidationService`

Current rules include:
- prompt required
- allowed digits defined
- timeout within valid range
- team id required
- schedule id required
- mailbox required

### 4. Flow Publish Lifecycle
Needs a formal lifecycle like:
- draft
- validated
- published
- archived

Published flows should be immutable.
Edits should always create a new version.

### 5. Error and Compensation Policy
The docs mention fallback to hangup or voicemail, but this should be formalized per node or per flow.

Recommendation:
- every node supports timeout and failure transitions where relevant
- default fallback policy should be explicit
- errors must be written to `call_trace_events`

Examples:
- menu timeout → voicemail
- ring timeout → next edge or voicemail
- node error → fail-safe hangup or voicemail

### 6. Multi-Tenancy Isolation
Mentioned, but not deeply specified.
Need explicit guarantees around:
- tenant scoping on all queries
- event enrichment with tenant id
- policy engine isolation
- number resolution isolation
- flow lookup isolation

### 7. Schedule Model Depth
The master doc introduces breaks and exceptions, but schema for exceptions needs to be locked properly.

This is now partially implemented in code with:
- `holiday_calendars`
- `holidays`
- `schedules`
- `schedule_rules`
- `schedule_breaks`
- `schedule_exceptions`

Current exception schema:
- `id`
- `schedule_id`
- `start_datetime`
- `end_datetime`
- `state`

This supports:
- single day exception
- date range exception
- partial day exception

Example:
- `2025-12-24 00:00 → 16:00 → open`
- `2025-12-24 16:00 → 23:59 → closed`

### 8. Timezone Handling
This needs to be explicit, not implied.

Recommendation:
- every schedule has a timezone
- if needed, tenant also has a default timezone
- schedule evaluation must never rely on server time

Otherwise multi-region behavior will break.

### 9. Node Execution Timeouts
Async nodes must never wait forever.

Recommendation:
- define timeout on every async-capable node
- define fallback transition for timeout

Examples:
- menu timeout → voicemail
- ring timeout → next strategy or voicemail

### 10. Idempotent Event Processing
Telephony systems duplicate events. This is normal.

Recommendation:
- unique constraint on event identity such as `event_id + call_uuid`
- if already processed, skip
- processors must be safe to retry

This is mandatory for production stability.

---

## Recommended Normalized Domain Events
Based on the combined docs, this event set would make sense.

### Call lifecycle
- `call.started`
- `call.ringing`
- `call.answered`
- `call.bridged`
- `call.hangup`
- `call.missed`
- `call.failed`
- `call.transferred`

### Input / interaction
- `menu.selection`
- `menu.timeout`
- `speech.captured`
- `recording.started`
- `recording.stopped`

### Workflow / policy
- `flow.started`
- `flow.paused`
- `flow.resumed`
- `flow.completed`
- `schedule.matched`
- `team.ring.timeout`
- `team.member.answered`

---

## Recommended Minimal First Release Scope
To avoid overbuilding, the smallest strong v1 would be:

### Core entities
- numbers
- flows
- flow_versions
- flow_nodes
- flow_edges
- call_sessions
- call_trace_events
- schedules
- teams

### Core nodes
- start
- schedule_check
- menu
- ring_team
- voicemail
- play_audio
- hangup

### Core platform services
- xml_curl entrypoint
- event normalizer
- media control service
- flow execution service
- schedule engine
- number routing service

That is enough to prove the architecture without getting lost in feature sprawl.

---

## Bottom Line
All task files point to the same conclusion:

**NIZAM should be rebuilt as a layered, API-first, policy-driven workflow platform on top of FreeSWITCH, not as a traditional PBX admin app.**

The most important implementation decisions are:
1. keep workflow logic separate from raw telephony events
2. bind every call to an immutable flow version
3. centralize schedules, holidays, teams, and routing as policy domains
4. record trace events for every meaningful execution step
5. keep UI completely outside the backend core

If you follow that, the architecture will stay sane as the product grows.
