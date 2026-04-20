# Requirements Document: system-alignment-remediation

## Overview

The platform must remediate the alignment and performance issues identified in the full-system audit without breaking organization isolation or existing telephony flows. The remediation covers schema indexing, model and migration alignment, service and controller boundaries, DTO contracts, caching of hot read paths, and naming consistency across layers.

## Requirements

### Requirement 1: Hot-path database performance
1. The system SHALL add the missing indexes required by hot organization-scoped call, queue, and agent queries.
2. The system SHALL align high-volume table indexes with actual service and controller query patterns.
3. The system SHALL preserve existing data semantics while improving query selectivity.

### Requirement 2: Schema-model consistency
1. The system SHALL eliminate model-to-migration drift for call sessions, call events, flow versions, and CDR fields.
2. The system SHALL reconcile Flow versus CallFlow naming so database, models, and services use one canonical concept.
3. The system SHALL ensure resources and services only depend on fields that actually exist in persistence.

### Requirement 3: Controller-service-model alignment
1. Controllers SHALL act as transport adapters and delegate business decisions to services or dedicated read models.
2. Models SHALL not own cross-service integration behavior that belongs in application services.
3. The system SHALL introduce stable query or read-model entrypoints for resource-heavy API responses.

### Requirement 4: DTO and request-resource alignment
1. The system SHALL introduce explicit DTOs for non-trivial write paths beginning with flows, queues, and extensions.
2. Request validation SHALL map cleanly to those DTOs.
3. Resources SHALL remain output contracts and SHALL only expose data prepared by the application layer.

### Requirement 5: Hot-path caching and aggregation
1. Organization wallboard and agent-state endpoints SHALL use caching or pre-aggregated reads instead of repeated request-time fan-out.
2. Call analysis endpoints SHALL avoid redundant database reads when data is already preloaded.
3. Historical queue metrics SHALL continue to use durable aggregates rather than request-time recomputation.

### Requirement 6: Naming and extensibility consistency
1. The system SHALL reduce ambiguous naming around flow, call-event, state, status, and type concepts where practical.
2. The flow domain SHALL move toward a single registration source for node validation, compile specs, and runtime behavior.
3. The domain layer SHALL depend on persistence-agnostic contracts or DTOs where practical for hot business logic.
