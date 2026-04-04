# Tasks: system-alignment-remediation

- [x] 1. Fix schema and index alignment
  - [x] 1.1 Audit and patch migration drift for call sessions, call events, flow versions, and CDR columns
  - [x] 1.2 Add missing composite indexes for call sessions, queue entries, agents, call events, and CDR search paths
  - [x] 1.3 Verify DB-to-model alignment on touched tables

- [x] 2. Fix hot-path performance services
  - [x] 2.1 Refactor MetricsService to use database aggregation instead of collection-heavy fan-out
  - [x] 2.2 Add tenant-scoped caching for wallboard and agent-state reads
  - [x] 2.3 Refactor CallTraceAnalyzer and call-session read paths to avoid redundant queries

- [x] 3. Fix controller-service-model boundaries
  - [x] 3.1 Move WebRTC config construction out of Extension into a dedicated service
  - [x] 3.2 Move non-transport business logic out of selected controllers into services or query objects
  - [x] 3.3 Standardize eager-loading or read-model entrypoints for resource-heavy responses

- [x] 4. Introduce DTO alignment for non-trivial writes
  - [x] 4.1 Add DTOs for flow create/update
  - [x] 4.2 Add DTOs for queue create/update and extension create/update
  - [x] 4.3 Align requests and services to those DTOs

- [x] 5. Improve naming and extensibility consistency
  - [x] 5.1 Reconcile Flow versus CallFlow naming drift with compatibility-safe updates
  - [x] 5.2 Clarify persisted call-event naming versus runtime event naming where practical
  - [x] 5.3 Centralize flow node registration metadata for validation and compile alignment

- [x] 6. Validate and review
  - [x] 6.1 Run diagnostics on all touched files
  - [x] 6.2 Update task review notes with what was fixed and what remains intentionally deferred
