# Nizam Roadmap and Next Implementation Wave

Date: 2026-04-12

## 1. What is now completed or strongly established

### Core architecture foundations
- **Domain-only organization identity**
  - organization context should resolve by domain only
- **User / Extension / Device mapping**
  - real business identity separated from telephony identity
- **Presence aggregation**
  - user, device, and call-state aware presence model
- **Schedule policy engine**
  - organization defaults with override capability
- **Routing graph compiler**
  - deterministic compiled routing artifacts and entrypoints
- **Supervisor reporting module**
  - call summary
  - missed returned calls
  - voicemails needing follow-up
- **Endpoint posture validated**
  - softphone/WebRTC/mobile-first direction is real and test-backed

### Product direction established
The platform is now clearly aimed at:
- a modern business phone system
- organization/domain-centric architecture
- graph-based routing with business presets
- modular capability surfaces
- local-first media posture
- strong reporting and supervisor action surfaces

## 2. What is structurally improved but should still be merged/validated carefully

### Built-in module bootstrap normalization
App-local modules now have a clearer config-driven registration path.

### Messaging hardening
Messaging moved from a weak scaffold to a more production-safe structure:
- provider-neutral routing
- persistent storage path
- safer preferred-provider behavior

### Voicemail/media integration stabilization
Voicemail/media path canonicalization and module-registry alignment improved significantly.

### Important scope note
AI/transcription work is intentionally excluded from acceptance for now.

## 3. Main gap remaining after this wave

The biggest remaining gap is **not** routing power.
It is **default business-phone completeness for a newly created organization/domain**.

FusionPBX and FSPBX both provide that through domain bootstrap and default dialplan feature packs.
Nizam still needs that same effect, implemented in a more modern way.

## 4. Next implementation wave

## Default Organization Bootstrap + Default Business Phone Feature Pack

### Goal
When a new organization/domain is created, it should become an immediately usable business phone system without relying on seeders or manual assembly.

### Required capabilities
1. **Automatic organization bootstrap**
   - create default business-hours schedule
   - create default holiday calendar
   - link organization default schedule/calendar
   - create a main entrypoint/default flow
   - create a default after-hours fallback target

2. **Default inbound office behavior**
   - main number behavior
   - business-hours branch
   - after-hours branch
   - direct extension dialing
   - no-answer / busy fallback

3. **Default feature-code / service-code pack**
   - voicemail main
   - send to voicemail
   - DND on/off/toggle
   - call forward on/off/toggle
   - follow-me entry points
   - call return
   - pickup/intercept
   - recording access / on-demand recording hooks
   - parking
   - company directory / dial-by-name
   - operator/service shortcuts

4. **Manifest rebuild completeness**
   - DID changes trigger manifest rebuild
   - IVR changes trigger manifest rebuild
   - RingGroup changes trigger manifest rebuild
   - TimeCondition changes trigger manifest rebuild
   - other route-affecting objects audited similarly

5. **Bootstrap validation**
   - warn if org has no active main entrypoint
   - warn if org lacks default schedule/calendar
   - warn if no inbound DID points to a live entrypoint
   - warn if compiled manifest is stale or missing

6. **Preset-based onboarding**
   - single main number preset
   - front desk/operator preset
   - small office preset
   - sales/support split preset

## 5. Recommended implementation order for the next wave

### Phase 1: Organization bootstrap service
- introduce a dedicated bootstrap service invoked on org creation
- provision default schedule and holiday calendar
- attach defaults to organization

### Phase 2: Default entrypoint provisioning
- create a starter main flow/preset
- create after-hours path
- create direct extension routing policy

### Phase 3: Feature-code/service-code pack
- add first-wave built-in service routes
- compile them into the generated organization manifest/dialplan path

### Phase 4: Manifest rebuild audit
- add missing observers/triggers for route-affecting PBX objects

### Phase 5: Bootstrap validation + admin UX
- validation surface for incomplete org setup
- admin screens/workflow for default office presets and main entrypoint configuration

## 6. Final directional summary

Nizam is now strong at:
- architecture
- compilation
- organization modeling
- reporting
- extensibility

The next leap is productizing those strengths into a **default business phone system experience**.

That next wave is the fastest route to making Nizam feel as immediately usable as FusionPBX/FSPBX while still preserving the stronger underlying architecture.
