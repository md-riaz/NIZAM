# Call Delivery Orchestration Todo

## README storage sizing and Docker host deployment plan
- [x] Review current README, Docker compose files, and deployment assumptions → Verify: storage estimate inputs and current-host deploy path are grounded in repo config
- [x] Update README with a disk footprint section that breaks down Laravel, FreeSWITCH, PostgreSQL, Redis, logs, recordings, and Docker volumes → Verify: README includes concrete sizing guidance for small, medium, and recording-heavy installs
- [x] Deploy the Docker stack on the current host and verify container status and health → Verify: `docker compose` stack starts or deployment blockers are documented accurately in the review notes
- [x] Repair the persisted Docker PostgreSQL state so the stack reaches a healthy application state → Verify: app can authenticate to Postgres, queue stops restarting, and health endpoint no longer reports DB/cache errors
- [x] Capture final deployment verification in the review section → Verify: task outcome, recovery path, and remaining caveats are documented

### README storage sizing and Docker host deployment review
- Added a new README section that estimates the platform footprint by major area: Laravel app image and storage, FreeSWITCH image and runtime data, PostgreSQL, Redis, Docker overhead, and recordings.
- Added a current-host Docker deployment section to README with the exact environment variables to set, build and start commands, verification commands, and notes about host port conflicts.
- Fixed a real frontend build blocker in `resources/css/communications-tokens.css` by removing an extra closing brace that caused `npm run build` and the Docker app image build to fail.
- Made FreeSWITCH published host ports configurable in `docker-compose.telephony.yml` using `FREESWITCH_SIP_PORT`, `FREESWITCH_EXTERNAL_SIP_PORT`, `FREESWITCH_WSS_PORT`, and `FREESWITCH_RTP_PORT_RANGE`, then updated README to document those overrides.
- On this host, the original FreeSWITCH published ports were blocked, so the stack was brought up using alternate host ports: SIP `25060`, external SIP `25080`, WSS `17443`, RTP `20000-20100`.
- Current deployment state on this host: `app`, `nginx`, `freeswitch`, `scheduler`, `postgres`, `redis`, `esl-listener`, `sip-mock`, `queue`, and `certbot` are up, and the health endpoint now reports `healthy`.
- Verified root cause of the remaining deployment blocker: the persisted Docker volume `nizam_postgres_data` contained an older PostgreSQL cluster with no `communications` role, so the app failed with `Role "communications" does not exist` and `password authentication failed for user "communications"`.
- Recovery performed: backed up the existing Postgres volume to `nizam_postgres_data_backup_2026-04-04_0545utc.tgz`, removed only `nizam_postgres_data`, let Docker initialize a fresh cluster with the configured `communications` database and user, then ran `php artisan migrate --force`.
- A second issue surfaced during seeding: `GraphFlowDemoSeeder` was passing a raw array into `FlowGraphService::updateFlowWithVersion()`, which now requires `App\Data\FlowData`. Fixed the seeder to call `FlowData::fromArray(...)`, reran `php artisan db:seed --force`, and seeding completed successfully.
- Final verification on this host: `docker compose ps` shows the stack stable, queue is no longer restarting, and `curl http://localhost:8231/api/v1/health` returns `{"status":"healthy"...}`.

## Full System Alignment Audit Plan
- [x] Map database schema hotspots across call, queue, flow, tenant, and webhook tables → Verify: critical migrations and indexes reviewed
- [x] Audit model relationships and tenant scoping for eager loading, N+1, and DB alignment → Verify: key Eloquent models and relationships reviewed
- [x] Audit service and controller boundaries for performance risks and layer alignment → Verify: major API/services examined against route behavior
- [x] Audit request/resource or DTO alignment, naming consistency, and extensibility seams → Verify: representative request/resource classes checked against controllers and models
- [x] Produce brutally honest findings with concrete fixes and risk ranking → Verify: final audit covers performance, alignment, domain boundaries, and naming consistency

### Alignment Audit Review
- Reviewed route definitions, high-traffic call and queue migrations, core models, representative controllers, requests, resources, domain flow classes, and performance-sensitive services.
- Confirmed there is no dedicated DTO layer in `app/`; requests are acting as input DTOs and resources are acting as output DTOs.
- Identified concrete scalability risks around queue metrics fan-out, call trace re-querying, weak composite indexes on call and queue tables, JSON-heavy payload storage, and inconsistent naming between `Flow`/`CallFlow` and `CallEventLog`/`call_events`.
- Confirmed several boundary leaks where controllers perform business decisions directly, models contain service-style behavior, and the domain flow layer depends directly on Eloquent `CallSession` instead of a persistence-agnostic contract.

## System Alignment Remediation Plan
- [x] Create and verify spec artifacts for full-system remediation → Verify: requirements, design, and tasks files exist under `.kiro/specs/system-alignment-remediation`
- [x] Implement phase 1 schema and index alignment → Verify: migrations and affected model assumptions updated
- [x] Implement phase 2 hot-path service and caching fixes → Verify: metrics and call analysis paths simplified and optimized
- [x] Implement phase 3 controller, service, model, and DTO boundary cleanup → Verify: selected write and read paths use clearer application-layer contracts
- [x] Implement phase 4 naming and extensibility cleanup with compatibility safety → Verify: major Flow and event naming drift reduced without breaking behavior
- [x] Run diagnostics and update review notes → Verify: touched files checked and review notes captured
- [x] Re-read remediation spec, existing todo state, and created phase files
- [x] Wire flow write path through `FlowData` and `FlowApplicationService`
- [x] Wire queue and extension write paths through DTOs and extracted services
- [x] Refactor `MetricsService` aggregation and add short-lived tenant-scoped caching
- [x] Refactor `CallTraceAnalyzer` to honor preloaded relations and avoid redundant trace queries
- [x] Run diagnostics on all touched PHP files and capture review notes
- [x] Refactor `aggregateMetrics()` to use SQL aggregation instead of loading full queue-entry collections
- [x] Centralize flow node alias resolution in the compile registry and remove validator drift
- [x] Run diagnostics on the next remediation slice and capture review notes
- [x] Normalize call-event naming across persisted events, broadcast events, webhook events, and ingestion rules
- [x] Re-scan and update remaining stale event-name references in tests and ancillary examples
- [x] Run diagnostics on the event-naming cleanup and capture review notes
- [x] Capture final event-name cleanup review notes and close this slice
- [x] Remove remaining dead `CallFlow` relationship drift from `Tenant`
- [x] Rename stale `CallFlow` API test to `Flow` naming
- [x] Run diagnostics and capture review notes for the Flow naming cleanup slice
- [x] Reduce `MetricsService::getWallboardData()` request-time query fan-out
- [x] Reuse pre-aggregated queue metrics where practical for wallboard summaries
- [x] Run diagnostics and capture review notes for the wallboard metrics cleanup slice
- [x] Extract wallboard reads into a dedicated read-model service
- [x] Rewire wallboard consumers to use the dedicated read-model service
- [x] Run diagnostics and capture review notes for the wallboard read-model slice
- [x] Add persisted wallboard projection tables and models
- [x] Implement projection refresh service for queue and agent wallboard state
- [x] Wire projection refreshes into queue, agent, and membership mutation paths
- [x] Switch wallboard reads to the persisted projections
- [x] Run diagnostics and capture review notes for the persisted wallboard projection slice

- [x] Verify current spec state and existing 4.2 implementation files
- [x] Confirm 4.3 behavior is already preserved for non-human routes and ring-group fallback semantics
- [x] Mark spec task 4.3 complete and close section 4 after updating task status
- [x] Continue with 5.1 analysis and implementation
- [x] Validate changed files and update this review section
- [x] Re-read 5.2 spec, design, and current orchestration event-processing code
- [x] Implement winner election on CHANNEL_ANSWER with minimal EventProcessor changes
- [x] Finalize winning bridge metadata on CHANNEL_BRIDGE without mutating non-winning attempts
- [x] Validate focused files and focused tests for 5.2
- [x] Mark spec task 5.2 complete and update review notes
- [x] Analyze 5.3 cleanup and reachability update touchpoints
- [x] Persist wake-window late-join binding metadata needed by EventProcessor
- [x] Implement CHANNEL_HANGUP_COMPLETE cleanup for orchestrated winner/session state
- [x] Implement sofia::register reachability refresh and eligible late-join attempt creation
- [x] Implement sofia::unregister reachability updates
- [x] Fix focused bridge test app key setup for 5.3 validation
- [x] Validate focused files and focused tests for 5.3
- [x] Mark spec task 5.3 and section 5 complete with updated review notes

## Review
- Section 4 is now closed after confirming non-human DID, time-condition, IVR, voicemail, bridge, flow, and ring-group fallback routes still bypass orchestrated human delivery.
- Task 5.1 is complete. EventProcessor now correlates channel events to orchestrated CallSessions and delivery attempts, persists bridge metadata, and records event rows through CallEventIngestionService with focused coverage passing.
- Task 5.2 is complete. EventProcessor now delegates eligible CHANNEL_ANSWER events to CallWinnerService and only finalizes winner bridge metadata when CHANNEL_BRIDGE matches the committed winning attempt. Focused EventProcessor event-log coverage passes.
- Task 5.3 is complete. EventProcessor now cleans up winning-leg hangups, updates reachability on sofia registration changes, and originates eligible late-join SIP attempts exactly once during the wake window. Focused EventProcessor bridge and event-log coverage passed after aligning the Bridge test app key setup with the existing EventProcessor test fixture.
- Task 6 is complete. Forwarded PSTN attempts now require explicit confirmation before winning and hangup finalization records confirmation failures explicitly. Ring group, queue, and direct agent routes now converge into the shared call delivery entrypoint, and the focused task 6 suite passed after aligning stale test expectations with the current orchestrated dialplan and sourcePath behavior.
- Task 7.1 is complete. Focused unit coverage now exercises ring-group human fallback, invalid runtime endpoint exclusion, degraded reachability fallback, deterministic wave ordering, and agent-bound SIP AOR resolution for orchestration candidate expansion.
- Task 7.1 validation passed with focused runs for `DeliveryTargetResolverTest`, `EndpointResolverTest`, `ReachabilityResolverTest`, and `DeliveryPlannerTest` with 21 passing tests and 119 assertions.
- Task 7.2 is complete. Race-focused coverage now verifies first-answer-wins persistence, loser terminalization, late-join registration suppression after winner commit, and answered-elsewhere notification deduplication.
- Task 7.3 is complete. Integration-style coverage now validates tenant-scoped mobile device lifecycle behavior, orchestration event processing for PSTN confirmation and late join, and answered-elsewhere behavior through the focused orchestration suites.
- Final focused task 7 validation passed for `CallWinnerServiceTest`, `AnsweredElsewhereServiceTest`, `EventProcessorBridgeTest`, `EventProcessorEventLogTest`, and `MobileDeviceApiTest` with 39 passing tests and 154 assertions.
- System alignment remediation implementation advanced across phases 1 to 4. Flow writes now go through `FlowData` and `FlowApplicationService`, queue and extension writes now use explicit DTO adapters, queue membership logic moved out of `QueueController`, and WebRTC config assembly moved out of `Extension` into `WebRtcConfigService`.
- `MetricsService` no longer does the worst request-time in-memory aggregation on the real-time and wallboard paths. Those paths now lean on SQL aggregates and short-lived tenant-scoped caching instead of queue-by-queue fan-out.
- `CallTraceAnalyzer` now respects preloaded `traceEvents`, `deliveryAttempts`, `pushNotificationLogs`, and `winningDeliveryAttempt` relations instead of blindly re-querying them. That closes one of the clearer N+1 and redundant-read leaks from the audit.
- One real schema-model drift was fixed during wiring: `Extension` now allows `outbound_caller_id_name` mass assignment, which the requests and resource already assumed existed.
- Diagnostics passed cleanly for all touched PHP files and the new alignment index migration. Remaining honest debt after that slice was that `aggregateMetrics()` still used collection-heavy aggregation, wallboard queue metrics still did several grouped queries instead of a dedicated pre-aggregated read model, and the broader Flow versus CallFlow naming cleanup had not been started.
- `aggregateMetrics()` has now been brought in line with the real-time metrics path. It no longer hydrates full `QueueEntry` collections just to count, average, and max them in PHP.
- Flow node alias handling is now centralized in `NodeSpecRegistry` instead of being split between compile-time specs and a hardcoded validator `match`. `business_hours` now resolves through the same source of truth as `schedule_check`, and `end` resolves through the same source of truth as `hangup`.
- Diagnostics passed cleanly for the follow-up metrics and flow-registry changes. Remaining honest debt now: wallboard queue metrics still rely on multiple grouped queries instead of a proper pre-aggregated read model, persisted versus runtime call-event naming is still inconsistent across broadcast, webhook, and ingestion paths, and the larger Flow versus CallFlow naming cleanup is still deferred.
- Event-name normalization is now reflected in the main webhook, broadcast, delivery-job, and audit tests. The active suite expectations now use canonical names like `call.created`, `call.bridged`, `device.registered`, and `device.unregistered` instead of the older ad hoc variants.
- Final cleanup verification is now complete for the event-name slice. A repo-wide exact sweep found no remaining stale PHP references to the pre-normalization names (`call.started`, `call.bridge`, `registration.registered`, `registration.unregistered`), and focused review confirmed the ancillary seed, factory, command-example, and audit-test files are already aligned. Focused diagnostics also remained clean on the relevant PHP files. Honest remaining debt: `call.missed` is still intentionally webhook-only rather than part of the canonical persisted event constant set.
- The remaining `Flow` versus `CallFlow` drift has now been reduced to zero in active PHP code. The dead `Tenant::callFlows()` relationship pointing at a non-existent `CallFlow` class was removed, and the stale API test was renamed from `CallFlowApiTest` to `FlowApiTest` with the file moved to `tests/Feature/Api/FlowApiTest.php`. Focused grep confirmed no remaining `callFlows()` or `CallFlowApiTest` references in PHP, and diagnostics passed on the touched files.
- The wallboard metrics path is now lighter on request-time aggregation. `MetricsService::getWallboardData()` now prefers the current hourly `queue_metrics` rows for queue KPI fields and only falls back to live `queue_entries` aggregation for queues that do not yet have a pre-aggregated metric row. Waiting counts, agent occupancy, agent-state summary, and the agent roster remain live. Diagnostics passed on the touched files. Honest remaining debt: the wallboard still issues separate live queries for waiting counts, membership occupancy, and the agent roster, so there is still room for a dedicated read model if this endpoint becomes a top-tier hotspot.
- That remaining wallboard debt is now reduced. The wallboard query path has been extracted into `WallboardReadService`, and `QueueMetricsController::wallboard()` now depends on that dedicated read service directly instead of routing wallboard reads through the broader `MetricsService`. `MetricsService::getWallboardData()` remains as a thin delegation layer so existing internal callers and tests keep the same contract. Diagnostics passed cleanly for `WallboardReadService`, `MetricsService`, and `QueueMetricsController`. Honest remaining debt: this is a dedicated read service, not a persisted materialized read model, so waiting counts, membership occupancy, and the agent roster are still live reads by design.
- The wallboard path is now properly projection-backed. Added persisted `wallboard_queue_projections` and `wallboard_agent_projections` storage, a `WallboardProjectionService` that reads from those projections, and observer or service hooks that refresh projections on queue-entry changes, agent changes, queue changes, membership changes, and extension identity changes that affect the wallboard roster. `QueueMetricsController::wallboard()` and `MetricsService::getWallboardData()` now read from the persisted projection service instead of the old live-query wallboard service. Diagnostics passed cleanly for the new migration, projection models, projection service, observers, provider registration, and touched controller or service files. Honest remaining debt: some direct pivot mutations outside `QueueMembershipService` can still bypass eager refresh timing, but `ensureTenantCoverage()` now backfills missing projection rows on read so the wallboard contract stays intact without relying on ad hoc live aggregation.
- Final sync pass completed on the remediation bookkeeping itself. Re-verified the alignment index migration, persisted wallboard projection migration, provider observer registration, projection refresh hooks, DTO and application-service extraction, canonical node alias registry, canonical call-event constants, and trace-analysis query reduction. The implementation was already materially complete; the remaining gap was stale checklist state in this file and the spec task file, which is now being synchronized to the real code state instead of leaving the remediation looking half-done on paper.

- [x] Analyze task 6 orchestration gaps for PSTN confirmation, ring groups, and queues
- [x] Implement PSTN confirmation failure outcome capture for forwarded attempts
- [x] Add queue dialplan handoff into shared delivery orchestration path
- [x] Add or update focused tests for PSTN confirmation handling and queue orchestration handoff
- [x] Validate changed files and update review notes for task 6
- [x] Re-read task 7.1 spec, design, and existing orchestration unit tests
- [x] Add focused unit coverage for DeliveryTargetResolver, EndpointResolver, ReachabilityResolver, and DeliveryPlanner
- [x] Fix agent-bound SIP AOR resolution for EndpointResolver task 7.1 validation failure
- [x] Run focused validation for task 7.1 touched tests and update review notes
- [x] Re-read task 7.2 spec, design, and existing winner or late-join tests
- [x] Add race-focused coverage for first-answer-wins, loser cancellation, and wake-window late join behavior
- [x] Run focused validation for task 7.2 touched tests and update review notes
- [x] Add remaining task 7.3 integration coverage for mobile-device delete and answered-elsewhere behavior
- [x] Fix MobileDeviceApiTest fixture app key setup for focused 7.3 validation
- [x] Run focused validation for task 7.3 touched tests and update review notes
