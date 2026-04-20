# Frontend API usability recovery plan

## Baseline (excluding flows, ivrs, time-conditions)

- Backend operations in scope: **167**
- Frontend-consumed operations: **45**
- Remaining operations: **122**
- Current coverage: **26.95%**
- Explicitly skipped now: all `flows*`, all `ivrs*`, all `time-conditions*`, including flow publish

## Delivery rule (requested)

- Everything except the excluded scopes must be consumable from frontend workflows.
- "Consumable" means: list/detail/create-edit/delete (where supported) + action endpoints + accessible entry route.

## Wave 0 — Foundation (2-3 days)

- [x] Create endpoint matrix file from route inventory grouped by domain + operation.
- [x] Add shared query/mutation helpers with consistent invalidation + toast/error mapping.
- [x] Add reusable list/form/detail scaffolds and confirmation patterns.
- [x] Expand navigation model to support new modules without route sprawl.

## Wave 1 — Close existing page gaps (4-6 days)

- [x] Complete missing actions on implemented modules:
  - users delete
  - extensions delete
  - ring-groups delete
  - organization provision/delete coverage
- [x] Upgrade dashboard to consume `GET admin/dashboard`.
- [x] Add full UI for:
  - admin/ssl
  - admin/sip-profiles
  - admin/blocked-destinations
  - auth/tokens

## Wave 2 — Organization control-plane CRUD (6-8 days)

- [ ] Add full modules + menu entries for:
  - queues (+ members + metrics endpoints)
  - agents (+ state change)
  - teams
  - bridges
  - device-profiles
  - holiday-calendars
  - schedules
  - webhooks (+ delivery attempts/stats)
  - call-routing-policies (+ evaluate)

## Wave 3 — Call operations + observability (5-7 days)

- [ ] Add call operations UI:
  - calls list/show/analyze
  - originate/hold/transfer/hangup/recording/status actions
- [ ] Add call-events tooling:
  - list, replay, redispatch, trace, stream viewer
- [ ] Complete analytics/ops endpoints:
  - cdr analytics + export + detail
  - recordings list/detail/download/delete
  - codec-metrics
  - organization stats / wallboard / usage summary-collect-reconcile
  - audit log detail

## Wave 4 — Usability hardening + release gate (3-4 days)

- [ ] Verify every in-scope API has an entry path (menu or page link).
- [ ] Keyboard-only and screen-reader pass for newly added modules.
- [ ] Add E2E smoke coverage for top workflows (auth, organization switch, CRUD, call ops, logs).
- [ ] Produce endpoint coverage report target: **>=95% of in-scope operations**.

## Acceptance criteria

- [ ] No in-scope endpoint remains orphaned from frontend workflow.
- [ ] Every new page is reachable from consistent IA (menu or contextual links).
- [ ] Role/organization guards match backend authorization behavior.
- [ ] Error states, loading states, and empty states implemented for each module.
- [ ] Release note includes excluded scopes (flows/ivrs/time-conditions) clearly.

## Review

- Baseline completed.
- Execution should start from Wave 0, then Wave 1 in the same branch family.
