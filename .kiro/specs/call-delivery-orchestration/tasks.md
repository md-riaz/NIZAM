# Tasks: call-delivery-orchestration

- [x] 1. Create runtime endpoint binding persistence and APIs
  - [x] 1.1 Add endpoint binding data model, migration, and relationships for organization-scoped runtime human delivery endpoints separate from DeviceProfile
  - [x] 1.2 Add organization-scoped mobile device API routes, controller actions, requests, and resources for register, update, delete, refresh-token, heartbeat, and capabilities
  - [x] 1.3 Add token rotation, endpoint enablement, and runtime capability validation rules with coverage for organization isolation

- [x] 2. Add delivery attempt persistence on top of CallSession
  - [x] 2.1 Add CallDeliveryAttempt model, migration, statuses, indexes, and CallSession relationships
  - [x] 2.2 Add optional push notification log and registration snapshot persistence needed for audit and troubleshooting
  - [x] 2.3 Expose delivery attempt and winner metadata through call session analysis surfaces where appropriate

- [x] 3. Build the shared call delivery orchestration pipeline
  - [x] 3.1 Implement DeliveryTargetResolver for extension, ring group, queue, DID, time condition, and people-targeting flow resolution
  - [x] 3.2 Implement EndpointResolver to expand logical targets into canonical endpoint candidates using endpoint bindings
  - [x] 3.3 Implement ReachabilityResolver with Redis-backed reachability cache and ESL-backed fallback registration visibility
  - [x] 3.4 Implement DeliveryPlanner for immediate SIP, parallel push, delayed PSTN, and wake-window late join policy
  - [x] 3.5 Implement CallOfferExecutor for ESL-originated SIP and PSTN offers plus push dispatch hooks
  - [x] 3.6 Implement CallWinnerService and AnsweredElsewhereService with race-safe first-answer-wins behavior using CallSession.lock_version

- [x] 4. Convert human-target routing to orchestrator handoff
  - [x] 4.1 Update DialplanCompiler human-target routes to emit shared delivery metadata handoff instead of embedding runtime reachability policy in direct bridge actions
  - [x] 4.2 Add a shared delivery entrypoint that parks the caller leg, creates or loads CallSession, and invokes orchestration
  - [x] 4.3 Preserve existing non-human routing behavior for voicemail, IVR, and other destinations outside the orchestrator scope

- [x] 5. Integrate orchestration with FreeSWITCH event processing
  - [x] 5.1 Extend EventProcessor handling to attach channel events to delivery attempts and active orchestrated CallSessions
  - [x] 5.2 Trigger winner election on CHANNEL_ANSWER and winning bridge finalization on CHANNEL_BRIDGE
  - [x] 5.3 Handle CHANNEL_HANGUP_COMPLETE cleanup plus sofia::register late-join and sofia::unregister reachability updates

- [x] 6. Add forwarded PSTN safety and shared queue or ring group behavior
  - [x] 6.1 Require answer confirmation before forwarded PSTN attempts can win and capture confirmation failure outcomes
  - [x] 6.2 Route ring group delivery through the same endpoint orchestration path as direct extension delivery
  - [x] 6.3 Route queue and agent delivery through the same endpoint orchestration path as direct extension delivery

- [x] 7. Test and validate the orchestration invariants
  - [x] 7.1 Add unit tests for target resolution, endpoint expansion, reachability decisions, and delivery planning
  - [x] 7.2 Add race-focused tests for first-answer-wins, loser cancellation, and wake-window late join behavior
  - [x] 7.3 Add integration tests for dialplan handoff, event-driven late join, mobile device APIs, PSTN confirmation, and answered-elsewhere behavior
