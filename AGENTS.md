# AGENTS

Purpose: fast map for repository knowledge base. Keep this file short (~100 lines).
System of record lives in `docs/` and root architecture doc.

## Read order
1. `ARCHITECTURE.md` — system architecture, boundaries, domain model.
2. `docs/PRODUCT_SENSE.md` — product intent, outcomes, scope boundaries.
3. `docs/PLANS.md` — planning policy + links to execution plans.
4. `docs/SECURITY.md` and `docs/RELIABILITY.md` — non-functional constraints.
5. Domain detail in `docs/design-docs/*` and `docs/product-specs/*`.

## Source of truth map
- Architecture: `ARCHITECTURE.md`
- Design overview: `docs/DESIGN.md`
- Frontend conventions: `docs/FRONTEND.md`
- Product framing: `docs/PRODUCT_SENSE.md`
- Quality model: `docs/QUALITY_SCORE.md`
- Reliability model: `docs/RELIABILITY.md`
- Security model: `docs/SECURITY.md`

### Design docs
- Index: `docs/design-docs/index.md`
- Core beliefs: `docs/design-docs/core-beliefs.md`
- Additional design docs: add in `docs/design-docs/` and link from index.

### Product specs
- Index: `docs/product-specs/index.md`
- Example spec: `docs/product-specs/new-user-onboarding.md`
- Additional specs: add in `docs/product-specs/` and link from index.

### Execution plans
- Active plans: `docs/exec-plans/active/`
- Completed plans: `docs/exec-plans/completed/`
- Cross-cutting debt: `docs/exec-plans/tech-debt-tracker.md`

### Generated artifacts
- Generated docs: `docs/generated/`
- Current schema snapshot: `docs/generated/db-schema.md`
- Rule: generated files updated by automation/scripts when possible.

### References
- External references and links: `docs/references/`

## Operating rules
- Keep canonical detail out of `AGENTS.md`; link outward.
- Any new deep doc must be linked from relevant index.
- Prefer updating existing doc over creating overlapping docs.
- When docs conflict, trust newest explicit decision in execution plans.
- Keep headings stable to preserve deep links.

## Documentation update checklist
- Architecture change -> update `ARCHITECTURE.md` first.
- UX/system behavior change -> update design doc + product spec.
- Delivery plan change -> move/update files in `docs/exec-plans/`.
- Security/reliability posture change -> update corresponding root docs.
- Schema change -> refresh `docs/generated/db-schema.md`.

## Ownership suggestion
- Architecture/platform: maintain `ARCHITECTURE.md`, `docs/RELIABILITY.md`, `docs/SECURITY.md`.
- Product/design: maintain `docs/product-specs/*`, `docs/design-docs/*`, `docs/PRODUCT_SENSE.md`.
- Delivery lead: maintain `docs/PLANS.md`, `docs/exec-plans/*`.

## Quick pointers for agents
- Need high-level context: start `ARCHITECTURE.md` -> `docs/PRODUCT_SENSE.md`.
- Need implementation intent: open relevant file from `docs/design-docs/index.md`.
- Need rollout status: check `docs/exec-plans/active/` then `completed/`.
- Need risk posture: read `docs/SECURITY.md` and `docs/RELIABILITY.md`.
