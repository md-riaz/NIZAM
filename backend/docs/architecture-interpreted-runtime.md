# Architecture Snapshot: Interpreted Runtime (v0)

This document captures the current interpreted runtime architecture before migration to compiled dialplan.

## Inbound Call Flow

### 1. FreeswitchXmlController::handle()
Location: `app/Http/Controllers/FreeswitchXmlController.php`

When FreeSWITCH xml_curl hits the `/freeswitch/xml` endpoint:

1. Extracts `Caller-Destination-Number`, `Caller-Caller-ID-Number`, domain
2. Calls `handleDialplan($domain, $destinationNumber, $callerIdNumber)`
3. Uses `DialplanCompiler` to generate XML response
4. For `destination_type = flow`: currently returns `<action application="answer"/>` + `<action application="park"/>`
5. Creates call session via `CallSessionService::getOrCreateInboundSession()`
6. Writes initial trace event via `TraceWriter`
7. Starts flow runtime via `FlowRuntimeStarter::startInboundFlow()`

### 2. FlowRuntimeStarter::startInboundFlow()
Location: `app/Services/Flow/FlowRuntimeStarter.php`

1. Loads the active published `FlowVersion` for the DID
2. Builds initial `CallContext` with session, flow version, variables
3. Invokes `FlowExecutionService::execute()` starting from the start node

### 3. FlowExecutionService::execute()
Location: `app/Services/Flow/FlowExecutionService.php`

Core execution loop:

1. Resolves current node from `CallContext`
2. Uses `NodeHandlerFactory` to get the appropriate node handler
3. Calls `handler->handle($context)` which returns a `NodeResult`
4. `NodeResult` contains:
   - `action`: what media command to run (playback, collect_digits, bridge, etc.)
   - `nextNodeId`: which node to execute next (or null for waiting)
   - `variables`: any state updates
5. Persists session state (`current_node_id`, `state`, `variables`)
6. Issues media commands via `MediaControlService`
7. If node waits for events (menu, ring_team), sets session to `waiting` state

### 4. Node Handlers
Location: `app/Services/Flow/Nodes/*NodeHandler.php`

Each node type implements `NodeHandler` contract:

- `StartNodeHandler`: initializes context, routes to first real node
- `ScheduleCheckNodeHandler`: calls `ScheduleEngine::evaluate()` at runtime, branches on open/closed/break
- `MenuNodeHandler`: issues `play_and_get_digits`, sets session to `waiting` for `menu.selection` event
- `RingTeamNodeHandler`: uses `TeamRoutingService` to resolve members, issues `uuid_transfer` with `bgapi`
- `VoicemailNodeHandler`: issues voicemail application command
- `HangupNodeHandler`: issues `uuid_kill`

### 5. ScheduleEngine::evaluate()
Location: `app/Services/Schedule/ScheduleEngine.php`

Runtime schedule evaluation:

1. Loads `Schedule`, `ScheduleRule`, `ScheduleBreak`, `ScheduleException`
2. Loads linked `HolidayCalendar` and `Holiday` records
3. Applies precedence: holiday → exception → break → open/closed
4. Returns current schedule state at moment of evaluation
5. **This is evaluated live on every call hitting a schedule_check node**

### 6. Event-Driven Resume
Location: `app/Jobs/ProcessCallEventJob.php` → `app/Services/Call/CallEventProcessor.php`

When FreeSWITCH events arrive (DTMF, answer, hangup):

1. `EslListenerCommand` normalizes event and pushes to `CallEventIngestionService`
2. `ProcessCallEventJob` dispatches with `call_uuid` lock
3. `CallEventProcessor`:
   - Acquires lock via `CallLockService::withLock($callUuid)`
   - Refreshes session state
   - Checks idempotency via `processed_events` array
   - Invokes `FlowRuntimeStarter::resumeFromEvent()`
4. Runtime resumes from waiting node with event data (e.g., `menu_digit = 1`)

### 7. MediaControlService
Location: `app/Services/Media/MediaControlService.php`

Issues real FreeSWITCH commands via `FreeSwitchCommandService`:

- `playback()` → `uuid_broadcast` with `aleg`
- `collectDigits()` → `play_and_get_digits`
- `transferToTeam()` → `uuid_transfer` with `background: true`
- `hangup()` → `uuid_kill`

All commands traced to `call_trace_events`.

## Key Characteristics

| Aspect | Current Behavior |
|--------|------------------|
| Flow execution | Interpreted in PHP at call time |
| Schedule evaluation | Live DB queries at call time |
| Media commands | Issued from app via ESL |
| Event handling | App resumes flow from queued events |
| Dialplan | Minimal; mostly answer + park for flows |
| State persistence | `call_sessions` table + `variables` JSON |
| Concurrency | Pessimistic lock per `call_uuid` + optimistic `lock_version` |

## Migration Notes

This architecture will be replaced by:
- Ahead-of-time compilation to XML dialplan fragments
- Schedule logic compiled to FreeSWITCH time conditions
- Node execution via modular dialplan extensions
- Lua helpers only for procedural logic (team selection, etc.)
- App becomes compile plane + observability sink, not hot-path runtime

Tagged: `v0-interpreted-runtime`
