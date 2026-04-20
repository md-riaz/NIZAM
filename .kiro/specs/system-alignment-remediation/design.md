# Design Document: system-alignment-remediation

## Overview

This remediation is split into low-risk phases so we can fix structural issues without destabilizing telephony behavior. The main strategy is to improve hot-path data access first, then align contracts and boundaries, then clean up naming and extensibility seams.

## Phases

### Phase 1: Schema and query performance
- Add composite indexes for organization-scoped call-session, queue-entry, agent, call-event, and CDR access patterns.
- Verify schema columns used by models and services actually exist.
- Prefer additive migrations over destructive renames in the first pass.

### Phase 2: Read-path cleanup
- Refactor MetricsService to use SQL aggregation instead of collection-heavy request-time fan-out.
- Add short-lived organization-scoped caching for wallboard and agent-state reads.
- Ensure CallTraceAnalyzer consumes preloaded relations and does not re-query unnecessarily.

### Phase 3: Boundary cleanup
- Move business decisions out of controllers where the current controller owns quota, publish, or membership behavior.
- Move WebRTC config assembly out of the Extension model into a service.
- Introduce query builders or read services for resource-heavy controller actions.

### Phase 4: Contract cleanup
- Introduce DTOs for Create and Update flow, queue, and extension use cases.
- Keep request classes as validation adapters and resources as output contracts.
- Reduce coupling between flow domain classes and Eloquent models where feasible.

### Phase 5: Naming and domain consistency
- Reconcile Flow versus CallFlow drift with a compatibility-first migration path.
- Clarify persisted event naming versus runtime event naming.
- Centralize flow node registration metadata for validation and compile behavior.

## Safety constraints
- Keep organization scoping explicit.
- Prefer additive changes and compatibility adapters before removals.
- Validate touched PHP files after each implementation phase.
