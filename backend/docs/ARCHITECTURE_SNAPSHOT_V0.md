# Architecture Snapshot (v0: Interpreted Runtime)

This document captures the state of the NIZAM runtime architecture as of the `v0-interpreted-runtime` tag, prior to the migration towards a compiled, local-runtime, modular dialplan model.

## 1. FreeswitchXmlController (Session Creation & Runtime Start)
Currently, `FreeswitchXmlController@handleDialplan` receives `xml_curl` requests from FreeSWITCH.
When a call comes in:
1. It resolves the gateway and DID.
2. If the DID destination is a `flow`, it fetches the active `FlowVersion`.
3. It calls `CallSessionService::getOrCreateInboundSession()` to persist call state.
4. It logs `call.started` via `CallEventIngestionService`.
5. Finally, it invokes `FlowRuntimeStarter::start($session, $flowVersion)` inline to begin executing the flow logic.
6. Then it falls through to `DialplanCompiler` which just generates `<action application="answer"/><action application="park"/>`.

## 2. FlowRuntimeStarter invoking FlowExecutionService
The `FlowRuntimeStarter` maps the graph representation into runtime objects using `FlowDefinitionMapper`.
It sets the current node to the `start` node, updates the `CallSession`, and then calls `FlowExecutionService::execute($session, $startNodeId)`.

## 3. FlowExecutionService Loop
`FlowExecutionService` operates as a PHP while-loop over the call session:
1. It resolves the `NodeHandler` for the current node.
2. Calls `$handler->handle($context)`.
3. If the handler returns `wait` (e.g. waiting for a DTMF digit), the loop stops, and the session state becomes `waiting`.
4. If it returns `completed`, the session is marked completed.
5. If it returns a `transition`, it resolves the next node via `EdgeResolver`, updates the `current_node_id` in the database, and loops again.
This entire loop executes synchronously in PHP while the call is parked in FreeSWITCH.

## 4. ScheduleEngine at Runtime
In the `ScheduleCheckNodeHandler`, the `ScheduleEngine` is injected and called at the moment of execution.
It queries the `schedules`, `schedule_rules`, `schedule_breaks`, and `schedule_exceptions` from the database, evaluates the current time against the policy, and returns a state (`open`, `closed`, `holiday`, `break`).
This means the hot call path depends on database queries to evaluate time conditions.

## 5. MediaControlService
When a node needs to perform a media action (like `MenuNodeHandler` playing a prompt or `RingTeamNodeHandler` bridging a call), it calls `MediaControlService`.
The `MediaControlService` then uses `FreeSwitchCommandService` which talks directly to FreeSWITCH via ESL (`uuid_broadcast`, `uuid_transfer`, `uuid_kill`).
The app controls the media dynamically while keeping the call parked (or transferring it out of the park state).

---

This architecture represents the "interpreted" approach where NIZAM acts as the live brain driving every step of the call via external ESL commands and database lookups.
