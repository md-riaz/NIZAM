# NIZAM Implementation Checklist

This is the practical build plan for turning NIZAM into an API-first communications control plane on top of FreeSWITCH.

It is ordered to reduce rework.
Do not jump ahead to UI.
Do not build advanced nodes before the event spine is stable.

---

## Phase 0: Stabilize the Current System

### Goals
- protect deployable state
- create safe refactor boundary

### Tasks
- [ ] Create branch: `refactor/api-first-core`
- [ ] Tag current state: `v0.1-pbx-platform`
- [ ] Confirm current production boot path still works
- [ ] Capture baseline docs for existing call flow entrypoints
- [ ] Capture current API routes and web routes before removal

### Suggested files
- Git only
- `docs/ARCHITECTURE_BASELINE.md`
- `docs/ROUTES_BASELINE.md`

---

## Phase 1: Remove Backend UI Responsibility

### Goals
- backend stops behaving like a rendered admin app
- keep only infra-required web endpoints

### Tasks
- [ ] Remove or disable `/ui/*` routes from `routes/web.php`
- [ ] Keep `/freeswitch/xml-curl`
- [ ] Keep `/provision/{mac}`
- [ ] Remove Blade-oriented controllers under `app/Http/Controllers/Web/`
- [ ] Remove Blade views under:
  - `resources/views/ui`
  - `resources/views/dashboard`
  - `resources/views/extensions`
- [ ] Move any needed auth behavior to API auth
- [ ] Audit middleware dependencies that assumed Blade/session flows

### Suggested files
- `routes/web.php`
- `routes/api.php`
- `app/Http/Controllers/Web/*`
- `resources/views/*`

---

## Phase 2: Introduce Structural Boundaries

### Goals
- stop leaking business logic through controllers
- create domain-oriented application structure

### Tasks
- [ ] Prefix API routes with `/api/v1`
- [ ] Create domain folders:
  - `app/Domain/Call`
  - `app/Domain/Flow`
  - `app/Domain/Routing`
  - `app/Domain/Schedule`
  - `app/Domain/Team`
  - `app/Domain/Media`
  - `app/Domain/Provisioning`
- [ ] Create service layer folders:
  - `app/Services/Call`
  - `app/Services/Flow`
  - `app/Services/Routing`
  - `app/Services/Media`
- [ ] Move controller logic into services
- [ ] Introduce repositories only where query complexity justifies them

### Suggested files
- `routes/api.php`
- `app/Domain/**`
- `app/Services/**`

---

## Phase 3: Build the Call Session and Event Spine First

### Goals
- define the runtime backbone for every call
- make the platform observable before adding complexity

### Database
- [ ] Create migration: `call_sessions`
- [ ] Create migration: `call_events`
- [ ] Create migration: `call_trace_events`

### Required `call_sessions` fields
- [ ] `id`
- [ ] `call_uuid`
- [ ] `tenant_id`
- [ ] `number_id` nullable
- [ ] `flow_id` nullable
- [ ] `flow_version_id` nullable
- [ ] `current_node_id` nullable
- [ ] `state`
- [ ] `variables_json` nullable
- [ ] `started_at`
- [ ] `ended_at` nullable
- [ ] `version` for optimistic locking
- [ ] timestamps

### Required `call_events` fields
- [ ] `id`
- [ ] `event_id`
- [ ] `call_session_id` nullable until matched
- [ ] `call_uuid`
- [ ] `tenant_id` nullable
- [ ] `event_type`
- [ ] `source`
- [ ] `payload_json`
- [ ] `received_at`
- [ ] unique key on `event_id + call_uuid`

### Required `call_trace_events` fields
- [ ] `id`
- [ ] `call_session_id`
- [ ] `call_uuid`
- [ ] `node_id` nullable
- [ ] `node_type` nullable
- [ ] `action`
- [ ] `payload_json` nullable
- [ ] `created_at`

### Models
- [ ] `app/Models/CallSession.php`
- [ ] `app/Models/CallEvent.php`
- [ ] `app/Models/CallTraceEvent.php`

### Services
- [ ] `app/Services/Call/CallSessionService.php`
- [ ] `app/Services/Call/CallEventService.php`
- [ ] `app/Services/Call/TraceWriter.php`

### Event ingestion pipeline
- [ ] Build ESL listener service
- [ ] Build event normalizer
- [ ] Convert raw FreeSWITCH events into domain events
- [ ] Push normalized events into queue or Redis-backed processing pipeline
- [ ] Build `CallEventProcessor`
- [ ] Make processors idempotent

### Suggested files
- `app/Console/Commands/*` or worker entrypoint for ESL listener
- `app/Domain/Call/Events/*`
- `app/Services/Call/CallEventProcessor.php`
- `app/Jobs/ProcessCallEventJob.php`

### Minimum normalized events
- [ ] `call.started`
- [ ] `call.ringing`
- [ ] `call.answered`
- [ ] `call.bridged`
- [ ] `call.hangup`
- [ ] `call.failed`
- [ ] `menu.selection`
- [ ] `menu.timeout`

---

## Phase 4: Number Routing and Gateway Registration

### Goals
- give inbound calls a real entry point
- support DID ownership through gateway registration
- bind numbers to call flows cleanly

### Why gateway registration belongs here
DID routing is incomplete without knowing which external trunk or gateway owns the number and where inbound traffic is arriving from.
You need this before number resolution becomes trustworthy.

### Database
- [ ] Create migration: `gateways`
- [ ] Create migration: `gateway_registrations`
- [ ] Create migration: `numbers`

### Required `gateways` fields
- [ ] `id`
- [ ] `tenant_id`
- [ ] `name`
- [ ] `type` such as `sip_trunk`, `carrier`, `internal`
- [ ] `direction` such as `inbound`, `outbound`, `both`
- [ ] `status`
- [ ] `config_json`
- [ ] timestamps

### Required `gateway_registrations` fields
- [ ] `id`
- [ ] `gateway_id`
- [ ] `registration_identifier`
- [ ] `username`
- [ ] `realm` nullable
- [ ] `proxy` nullable
- [ ] `transport` nullable
- [ ] `status`
- [ ] `last_registered_at` nullable
- [ ] `last_failed_at` nullable
- [ ] `meta_json` nullable
- [ ] timestamps

### Required `numbers` fields
- [ ] `id`
- [ ] `tenant_id`
- [ ] `gateway_id` nullable
- [ ] `gateway_registration_id` nullable
- [ ] `phone_number`
- [ ] `normalized_number`
- [ ] `flow_id`
- [ ] `is_active`
- [ ] timestamps

### Models
- [ ] `app/Models/Gateway.php`
- [ ] `app/Models/GatewayRegistration.php`
- [ ] `app/Models/Number.php`

### Services
- [ ] `app/Services/Routing/NumberRoutingService.php`
- [ ] `app/Services/Routing/GatewayResolutionService.php`
- [ ] `app/Services/Media/GatewayRegistrationService.php`

### Core behaviors
- [ ] register and manage carrier or trunk metadata
- [ ] associate one or more DIDs with a gateway or registration
- [ ] normalize incoming destination numbers before lookup
- [ ] resolve inbound call to number record
- [ ] resolve number record to active flow
- [ ] create call session at first ingress point
- [ ] bind call to immutable `flow_version_id`

### xml_curl entrypoint flow
- [ ] receive inbound call request from FreeSWITCH
- [ ] resolve source gateway or trunk context if available
- [ ] normalize destination number
- [ ] find matching number
- [ ] create or hydrate `call_session`
- [ ] bind `flow_id` and active `flow_version_id`
- [ ] emit `call.started`
- [ ] invoke flow execution service

### Suggested files
- `app/Http/Controllers/FreeSwitch/XmlCurlController.php`
- `app/Services/Routing/NumberRoutingService.php`
- `app/Services/Media/GatewayRegistrationService.php`
- `app/Domain/Routing/*`

---

## Phase 5: Build the Minimum Flow Engine

### Goals
- create the first stable workflow runtime
- support only the nodes needed to prove architecture

### Database
- [x] Create migration: `flows`
- [x] Create migration: `flow_versions`
- [x] Create migration: `flow_nodes`
- [x] Create migration: `flow_edges`

### Required `flows` fields
- [ ] `id`
- [ ] `tenant_id`
- [ ] `name`
- [ ] `description` nullable
- [ ] `active_version_id` nullable
- [ ] timestamps

### Required `flow_versions` fields
- [ ] `id`
- [ ] `flow_id`
- [ ] `version_number`
- [ ] `definition_checksum` nullable
- [ ] `status` such as `draft`, `validated`, `published`, `archived`
- [ ] `is_published`
- [ ] `definition_json` nullable
- [ ] timestamps

### Required `flow_nodes` fields
- [ ] `id`
- [ ] `flow_version_id`
- [ ] `type`
- [ ] `name`
- [ ] `config_json`
- [ ] `position_x` nullable
- [ ] `position_y` nullable
- [ ] timestamps

### Required `flow_edges` fields
- [ ] `id`
- [ ] `flow_version_id`
- [ ] `source_node_id`
- [ ] `target_node_id`
- [ ] `condition`
- [ ] timestamps

### Runtime objects
- [ ] `app/Domain/Flow/CallContext.php`
- [ ] `app/Domain/Flow/NodeResult.php`
- [ ] `app/Domain/Flow/Contracts/NodeHandler.php`
- [ ] `app/Services/Flow/FlowExecutionService.php`
- [ ] `app/Services/Flow/NodeHandlerFactory.php`
- [ ] `app/Services/Flow/EdgeResolver.php`

### First node set only
- [ ] `StartNodeHandler`
- [ ] `ScheduleCheckNodeHandler`
- [ ] `MenuNodeHandler`
- [ ] `RingTeamNodeHandler`
- [ ] `VoicemailNodeHandler`
- [ ] `HangupNodeHandler`

### Required behaviors
- [ ] load call session
- [ ] load bound flow version
- [ ] load current node
- [ ] execute node handler
- [ ] record trace event
- [ ] resolve edge by transition
- [ ] update current node
- [ ] pause when waiting on async event
- [ ] resume when matching normalized event arrives

---

## Phase 6: Lock Flow Version Immutability and Publish Rules

### Goals
- make runtime safe during edits
- reject broken flows before they go live

### Tasks
- [ ] Enforce immutable published versions
- [ ] Edits create new version only
- [ ] Add flow publish lifecycle:
  - `draft`
  - `validated`
  - `published`
  - `archived`
- [ ] Validate all nodes before publish
- [ ] Validate graph integrity before publish
- [ ] Prevent publish if no start node exists
- [ ] Prevent publish if transitions point to missing nodes

### Services
- [ ] `app/Services/Flow/FlowPublishService.php`
- [ ] `app/Services/Flow/FlowValidationService.php`

---

## Phase 7: Build the Schedule Engine Properly
Status: in progress

### Goals
- centralize time policy
- stop flows from embedding fragile date logic

### Database
- [ ] Create migration: `holiday_calendars`
- [ ] Create migration: `holidays`
- [ ] Create migration: `schedules`
- [ ] Create migration: `schedule_rules`
- [ ] Create migration: `schedule_breaks`
- [ ] Create migration: `schedule_exceptions`

### Required `schedules` fields
- [ ] `id`
- [ ] `tenant_id`
- [ ] `holiday_calendar_id` nullable
- [ ] `name`
- [ ] `timezone`
- [ ] timestamps

### Required `schedule_rules` fields
- [ ] `id`
- [ ] `schedule_id`
- [ ] `day_of_week`
- [ ] `start_time`
- [ ] `end_time`

### Required `schedule_breaks` fields
- [ ] `id`
- [ ] `schedule_id`
- [ ] `day_of_week`
- [ ] `start_time`
- [ ] `end_time`

### Required `schedule_exceptions` fields
- [ ] `id`
- [ ] `schedule_id`
- [ ] `start_datetime`
- [ ] `end_datetime`
- [ ] `state`

### Services
- [ ] `app/Services/Schedule/ScheduleEngine.php`
- [ ] `app/Services/Schedule/ScheduleEvaluationService.php`

### Deterministic evaluation order
- [ ] Holiday
- [ ] Special exception
- [ ] Break
- [ ] Weekly hours
- [ ] Closed

### Required behaviors
- [ ] evaluate using schedule timezone, never server time
- [ ] return one of:
  - `holiday`
  - `exception`
  - `break`
  - `open`
  - `closed`
- [ ] integrate result into `ScheduleCheckNodeHandler`

---

## Phase 8: Build Team Routing

### Goals
- support reusable groups of endpoints
- keep routing policy outside raw node spaghetti

### Database
- [ ] Create migration: `teams`
- [ ] Create migration: `team_members`

### Required `teams` fields
- [ ] `id`
- [ ] `tenant_id`
- [ ] `name`
- [ ] `strategy`
- [ ] `timeout`
- [ ] timestamps

### Required `team_members` fields
- [ ] `id`
- [ ] `team_id`
- [ ] `endpoint_type`
- [ ] `endpoint_id`
- [ ] `priority` nullable
- [ ] timestamps

### Services
- [ ] `app/Services/Team/TeamRoutingService.php`
- [ ] integrate with `RingTeamNodeHandler`

### Initial strategies
- [ ] simultaneous
- [ ] round_robin
- [ ] priority

---

## Phase 9: Add Media Control Service and Keep Boundaries Clean

### Goals
- stop business logic from issuing random FreeSWITCH commands everywhere
- centralize telephony control

### Services
- [x] `app/Services/Media/MediaControlService.php`
- [x] `app/Services/Media/FreeSwitchCommandService.php`

### Commands to support first
- [ ] playback
- [ ] bridge
- [ ] transfer
- [ ] hangup
- [ ] ring group originate or equivalent

### Rule
- [ ] node handlers request media actions through media service only
- [ ] no ad hoc FreeSWITCH command calls scattered through unrelated services

---

## Phase 10: Add Validation, Timeouts, and Failure Rules

### Goals
- prevent broken flow configs from ever reaching runtime
- ensure async nodes do not hang forever

### Validators
- [x] `MenuNodeValidator`
- [x] `RingTeamNodeValidator`
- [x] `ScheduleCheckNodeValidator`
- [x] `VoicemailNodeValidator`

### Example validation rules
- [ ] menu prompt required
- [ ] allowed digits defined
- [ ] timeout range valid
- [ ] team id required for ring_team
- [ ] schedule id required for schedule_check

### Runtime protections
- [ ] every async node supports timeout
- [ ] every async node defines fallback transition
- [ ] errors are traced
- [ ] default fallback policy is explicit

### Examples
- [ ] menu timeout → voicemail
- [ ] ring timeout → fallback edge
- [ ] node error → fail-safe voicemail or hangup

---

## Phase 11: Concurrency and Idempotency Hardening

### Goals
- survive duplicate telephony events and worker races

### Tasks
- [ ] lock by `call_uuid` during event processing
- [ ] use optimistic locking via `call_sessions.version`
- [ ] dedupe events using unique key on `event_id + call_uuid`
- [ ] make processors retry-safe
- [ ] ensure resumed execution is idempotent

### Suggested files
- `app/Services/Call/CallLockService.php`
- `app/Services/Call/CallEventDedupService.php`

---

## Phase 12: Expand Observability and Debugging

### Goals
- make every call explainable
- support future replay UI

### Tasks
- [ ] trace every node execution
- [ ] trace every branch decision
- [ ] trace media actions requested
- [ ] trace errors and fallback transitions
- [ ] trace final outcome

### Nice future outputs
- [ ] call replay timeline
- [ ] per-node duration metrics
- [ ] failure heatmaps by node type

---

## Phase 13: Webhooks and Automation

### Goals
- expose platform events to external systems
- keep automation additive, not invasive

### Database
- [ ] Create migration: `webhooks`
- [ ] Create migration: `event_subscriptions` if needed

### Tasks
- [ ] emit platform events to subscribed webhooks
- [ ] sign outbound webhook requests
- [ ] retry failed deliveries safely

### Good first events
- [ ] `call.completed`
- [ ] `call.missed`
- [ ] `voicemail.received`

---

## Phase 14: API Surface Cleanup

### Goals
- expose domain concepts, not old PBX naming leaks

### Endpoints to add or standardize
- [ ] `GET /api/v1/flows`
- [ ] `POST /api/v1/flows`
- [ ] `GET /api/v1/numbers`
- [ ] `POST /api/v1/numbers`
- [ ] `GET /api/v1/gateways`
- [ ] `POST /api/v1/gateways`
- [ ] `GET /api/v1/schedules`
- [ ] `POST /api/v1/schedules`
- [ ] `GET /api/v1/teams`
- [ ] `POST /api/v1/teams`
- [ ] `GET /api/v1/calls/{id}`
- [ ] `GET /api/v1/events`

---

## Phase 15: Build UI Last

### Goals
- keep frontend as consumer, not source of truth

### Tasks
- [ ] Create separate frontend repo
- [ ] Build flow editor on top of API
- [ ] Use graph UI to edit `flow_nodes` and `flow_edges`
- [ ] Build schedule editor on top of schedule APIs
- [ ] Build call trace viewer from trace APIs

---

## Minimum Vertical Slice to Prove the Architecture

Build this before anything flashy.

- [ ] inbound call reaches xml_curl
- [ ] number resolves through gateway-aware number routing
- [ ] call session is created
- [ ] active flow version is bound
- [ ] flow starts at `start`
- [ ] `schedule_check` runs
- [ ] `menu` waits for selection
- [ ] normalized `menu.selection` event resumes flow
- [ ] `ring_team` executes through media control service
- [ ] answer or timeout resumes flow
- [ ] trace shows the full path

If this slice works end to end, the architecture is real.
If this slice is messy, stop and fix it before adding features.

---

## Non-Negotiable Rules

- [ ] published flow versions are immutable
- [ ] workflow engine never consumes raw FreeSWITCH events directly
- [ ] telephony commands go through media control service
- [ ] schedule evaluation always uses explicit timezone
- [ ] duplicate events must be safe
- [ ] every important execution step is traced
- [ ] UI is not allowed to become the architecture

---

## Blunt Priority Order

If time is tight, do this exact order:
1. call spine
2. event normalization
3. gateway registration + number routing
4. minimal flow engine
5. schedule engine
6. ring team
7. trace hardening
8. concurrency hardening
9. webhooks
10. UI
