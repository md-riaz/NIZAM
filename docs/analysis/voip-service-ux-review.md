# UX Review: Maintaining VoIP Service as a Client Admin and as a Platform Admin

## Scope

A repo-based UX review of NIZAM as a hosted-PBX SaaS, focused on the day-to-day
maintenance jobs that client admins and platform admins actually perform:

1. Call recording — policy hierarchy, control, access, and consumption
2. Extension ↔ user ↔ device mapping — whether the data model matches the
   real-life mental model
3. WSS / WebRTC enablement — platform-wide vs per-client
4. Outbound caller ID selection — for the built-in web phone and for plain
   SIP-credential endpoints

Tracing those four pulled in five adjacent areas that turned out to matter just
as much for keeping a tenant running:

5. Call visibility and troubleshooting
6. Self-service and contact-center surfaces
7. Day-1 tenant onboarding
8. The permission model and multi-tenant safety
9. Cross-cutting UI issues

Conclusions come from the source tree, not a runtime audit. Every finding cites
file evidence so it can be checked and turned into work.

The recurring pattern is worth stating up front: **NIZAM's backend is
consistently ahead of its UI.** Most findings below are not "the platform can't
do this" — they are "the platform does this, and nobody can see, discover, or
verify it from a screen." A smaller but more serious set are genuine
model/routing gaps that must be fixed before any UI work on top of them makes
sense.

---

## Executive summary

| Area | State | Biggest single problem |
| --- | --- | --- |
| Recording hierarchy | Solid resolver, invisible behavior | Effective policy is never shown anywhere; queues, teams, and agents have no policy of their own |
| Recording consumption | Complete API, **zero UI** | No recordings page, no playback in call history |
| Extension/user/device | Model fights reality | User **XOR** device, and the device-side workaround is silently destroyed on any later extension save; multi-device reality lives in an `EndpointBinding` table with no UI |
| WSS enablement | Global-only | No per-organization gate; cannot pilot WebRTC for one client; a single save can restart the shared profile ~11 times |
| Outbound caller ID | Works on one path only | Registered SIP phones have **no outbound route at all**; caller ID selection exists only via the API originate path |
| Org configuration | Raw JSON textarea | Real behavior keys (e.g. caller-ID privacy) are edited as freeform JSON |
| Call visibility | Call log without phone numbers | No From/To/direction columns; no live-calls view; KPI tiles capped at 15 rows |
| Onboarding | Readiness model computed, discarded | `provisioning_health.next_actions` never reaches the UI; no trunk page at all |
| Self-service | Nothing exists | Agents land in the admin console; the one per-user features endpoint has no caller |
| Permissions | Default-open | Zero grants = all permissions; no policy scopes below organization; two live cross-tenant exposures |

The two items that should be fixed independently of any UX roadmap are in §8.7:
`SipProfileController` has no authorization on a global object, and
`AdminGatewayController::index` returns every tenant's gateways.

---

## 1. Call recording

### 1.1 What exists today

A clean, testable three-scope policy resolver:

- Values `inherit | off | all | incoming | outgoing`
  (`backend/app/Services/Recording/RecordingPolicy.php`)
- Scopes: Organization → DID → Extension, each with a `recording_policy`
  column, round-tripping through requests, resources, models, and admin forms
  (commit `a3e93ce`)
- Resolution is first-non-`inherit`-wins, walking
  `extension → did → organization` when the answering leg is an extension, and
  `did → organization` otherwise
  (`backend/app/Services/Recording/RecordingPolicyResolver.php:70-93`)
- The resolver already returns a `resolution_chain`, a `winning_scope`, and a
  human `reason` (`RecordingPolicyResolver.php:22-68`)
- Recording starts at the answer boundary via `uuid_record`
  (`backend/app/Services/Recording/AnsweredRecordingStarter.php:38`), with
  on-demand start/stop exposed at `POST calls/recording`
  (`backend/routes/api.php:204`,
  `backend/app/Http/Controllers/Api/CallController.php:184-201`)
- A full recordings API: filtered index, show, download, destroy
  (`backend/app/Http/Controllers/Api/RecordingController.php`)
- Retention pruning via `nizam:prune-recordings`
  (`backend/app/Console/Commands/PruneExpiredRecordingsCommand.php`)

That is a better foundation than the UI suggests.

### 1.2 The effective policy is never shown to anyone

An admin editing an extension sees a select box containing `Inherit`
(`frontend/src/pages/admin/ExtensionFormPage.tsx:647-663`). Nothing tells them
what `Inherit` currently resolves to, which scope wins, or whether this
extension's calls are being recorded right now. The same is true on the DID form
and the organization form.

This is the highest-value, lowest-cost fix in the whole review, because the
backend already computes the answer and throws it away.

**Recommendation.** Expose the resolver as a read-only endpoint
(`GET …/extensions/{id}/recording-policy/effective`) and render it inline under
the select as a resolved chain:

> Effective: **Recording all calls** — inherited from DID `+1 555 0100`
> (organization default is Off)

Show the same block on the DID and organization forms. Reuse
`resolution_chain` / `reason` verbatim; no new logic required.

### 1.3 `Inherit` is offered at the organization level, where it means "off"

Organization accepts `inherit` (`StoreOrganizationRequest.php:44`,
`UpdateOrganizationRequest.php:46`) and the form offers it
(`frontend/src/pages/admin/OrganizationFormPage.tsx:41-47`). But the
organization is the top scope: when every scope says `inherit`, the resolver
falls through to `off` with the reason `no recording policy resolved`
(`RecordingPolicyResolver.php:60-68`).

So a platform admin can set a client's recording policy to `Inherit`, save
successfully, and get silent `off` — with no indication that "inherit" had
nothing to inherit from.

**Recommendation.** Either drop `Inherit` from the organization-scope options
(offer `Off / All / Incoming / Outgoing`), or introduce a real platform-level
default so `Inherit` resolves to something nameable. If the latter, label it
explicitly: `Inherit platform default (currently Off)`.

### 1.4 There is no recording policy on a queue, team, or agent

An earlier revision of this document claimed that extension policy is ignored on
queue- and team-answered calls. **That was wrong**, and the correction is worth
stating precisely because it changes what needs building.

`usesExtensionPrecedence()` does gate on `answered_target_type === 'extension'`
(`RecordingPolicyResolver.php:95-99`), but the caller sets that value from the
winning leg, not from how the call was routed: `answeredTargetType()` returns
`'extension'` whenever the winning attempt's endpoint binding has an
`extension_id`, and `recordingContextForAnswer()` populates `extension_policy`
from that binding's extension
(`backend/app/Services/EventProcessor.php:629-658`). So an ordinary queue or
team call answered by an extension-bound endpoint **does** get extension
precedence. The original finding traced the resolver without tracing its caller.

Two narrower things are real:

1. **Coverage depends on the winning binding having an extension.** A queue leg
   won by a `pstn_forward` binding, or an agent endpoint with no `extension_id`,
   falls through to `data_get(..., 'answered_target_type', 'unknown')`
   (`EventProcessor.php:653-657`) and therefore resolves on `did → organization`
   only. Voicemail is explicitly `'voicemail'` (`:648-650`).
2. **Queue, Team/RingGroup, and Agent carry no `recording_policy` of their own.**
   "Record everything that comes through the support queue" and "record this
   agent regardless of which line they answer" are not expressible at any scope.
   That is the actual gap, and it is a modeling gap rather than a bug.

**Recommendation.** Add `recording_policy` to `Queue`, `Team`/`RingGroup`, and
`Agent` and insert them into the candidate chain
(`agent → queue/team → extension → did → organization`). Separately, give the
non-extension answer paths an explicit scope rather than letting them land on
`unknown`.

### 1.5 Lower scopes can loosen a compliance-mandated policy

Resolution is strictly first-non-`inherit`-wins with no notion of enforcement
direction (`RecordingPolicyResolver.php:29-58`). A tenant admin — or anyone with
`extensions.update` — can set an extension to `off` and defeat an
organization-level `all` that exists for legal or regulatory reasons. There is
no lock.

**Recommendation.** Add an `is_enforced` (or `locked`) flag along
`recording_policy` at the organization scope (and platform scope if introduced).
When enforced, lower scopes may only narrow within the mandate, never disable;
the child form should render the field disabled with
`Locked by organization policy`.

### 1.6 No per-call consent or pause/resume, and stop/start reuses one path

`AnsweredRecordingStarter` supports `start` and `stop`
(`AnsweredRecordingStarter.php:38,75,90`) — but not pause/resume, which is the
control PCI and consent workflows actually need ("stop capturing while I take
the card number, then resume the same file").

Worse, `startForCall()` derives the path from organization + call UUID
(`AnsweredRecordingStarter.php:73-75`), so a stop-then-start cycle within one
call targets **the same file path**, risking truncation of the earlier segment.

Also absent: any consent announcement or beep on recorded calls, and any way to
say "record external calls only" — with `all`, internal extension-to-extension
calls get recorded too.

**Recommendation.** Add `uuid_record … pause` / `resume` support, use
segment-suffixed paths (`…-seg2.wav`) when a call is recorded more than once,
add an `announce_recording` toggle at organization scope, and add
`external_only` as a policy modifier.

### 1.7 There is no recordings UI at all

The API is complete. The frontend has no recordings route, no recordings page,
and no playback: `frontend/src/app.tsx` contains no recording route,
`CallHistoryPage.tsx` and `InteractionDetailPage.tsx` never reference a
recording or an audio element, and the nav has no entry
(`frontend/src/layouts/SuperadminLayout.tsx:69-116`).

For a hosted PBX this is the feature clients ask about first. Today they cannot
reach their own recordings through the product.

**Recommendation.** Ship a Recordings page backed by the existing filtered index
(date range, caller, destination, `call_uuid`), and put an inline player plus a
download button on each call-history row that has a recording. The backend needs
no changes beyond joining recording presence into the CDR/interaction resource.

### 1.8 Recording access is flat, org-wide, and default-open

`recordings.view`, `recordings.download`, `recordings.delete`
(`backend/app/Console/Commands/SyncPermissionsCommand.php:55-57`) are checked
only against the caller's organization
(`backend/app/Policies/RecordingPolicy.php:21-40`), and the index query filters
by `organization_id` alone
(`RecordingController.php:26-28`).

So the "allow hierarchy" for recordings has exactly two states per tenant: hears
everything, or hears nothing. There is no "own calls only" for an agent and no
"my team only" for a supervisor — which is the normal requirement, and in many
jurisdictions a legal one.

It is more permissive than that in practice. `User::hasPermission()` is
**default-open**: any user with zero assigned permission rows is granted every
permission (`backend/app/Models/User.php:126-138`, and the docblock says so
explicitly). New users default to `role = 'agent'`
(`backend/app/Http/Controllers/Api/UserController.php:64`) with no permissions
attached — so a freshly created agent can list, play, download, and **delete**
every recording in the tenant until an admin remembers to grant them a narrower
set. Permissions here subtract rather than add, which is the opposite of what
the UI implies.

**Recommendation.** Three things, in order:

1. Make `hasPermission()` default-closed for non-admin roles, with role presets
   seeding a sensible starting grant. This is the single highest-risk item in the
   review.
2. Add a scope dimension to recording access (`own | team | organization`)
   resolved from team membership, and filter the index query by it.
3. Emit an audit event on **listen** and **download**, not just delete —
   recording access is the classic "who listened to my call" question, and
   answering it after the fact is currently impossible.

### 1.9 Retention is enforced but invisible and unsettable

`recording_retention_days` is fillable on the model
(`backend/app/Models/Organization.php:55`) and drives real deletion
(`PruneExpiredRecordingsCommand.php:31-33`), but it is **absent from
`OrganizationResource`** (`backend/app/Http/Resources/OrganizationResource.php:21-40`)
and appears nowhere in the frontend (no `retention` match anywhere under
`frontend/src`).

It is nullable, so today nothing is pruned by default — meaning storage grows
without bound, and the one control that would fix it cannot be seen or set
through the product.

**Recommendation.** Add it to the resource and to the organization form, worded
for consequence — `Recordings older than N days are permanently deleted` — with
the existing `--dry-run` count surfaced as a preview before save.

---

## 2. Extension ↔ user ↔ device mapping

### 2.1 The mental model clients actually hold

For a client admin, the unit of thought is a **person**: "Ayesha in Sales has
extension 1001; she uses a desk phone at her desk, the web phone on her laptop,
and the mobile app; her calls should show the Sales main number." Devices are
attributes of a person's line — not alternatives to it.

### 2.2 The supported model is user XOR device, and the one workaround self-destructs

`Extension.user_id` and `Extension.device_profile_id` are mutually exclusive by
validation — *"Extension cannot belong to both a user and a device"*
(`StoreExtensionRequest.php:137-138`, `UpdateExtensionRequest.php:140-142`) — and
the form enforces it, clearing the other field on switch
(`frontend/src/pages/admin/ExtensionFormPage.tsx:69-83,356-371`). `owner_type` is
not a column but an accessor derived from those two
(`backend/app/Models/Extension.php:157-161`), so it cannot be set directly.

There **is** a second path the first revision of this document missed: the Devices
page has its own "Assigned Extension" selector writing `DeviceProfile.extension_id`
(`frontend/src/pages/admin/DeviceProfileFormPage.tsx:204-239`,
`StoreDeviceProfileRequest.php:21`), and provisioning genuinely consumes it —
`ProvisioningService` reads `$profile->extension` for `{{EXTENSION}}` and
`{{PASSWORD}}` (`backend/app/Services/ProvisioningService.php:17,37-45`). So you
can give Ayesha's user-owned extension a desk phone from the device side.

**But that link is silently destroyed by any later extension save**, which is a
worse problem than the XOR itself. `ExtensionController::syncOwnedDevice()` nulls
`extension_id` on every device pointing at the extension whenever
`device_profile_id` is absent from the payload
(`backend/app/Http/Controllers/Api/ExtensionController.php:232-250`), and it runs
on both store and update (`:79,:133`). The admin form always sends `null` for a
user-owned extension (`ExtensionFormPage.tsx:126-130`).

So: assign the desk phone from the Devices page, then edit that extension for any
unrelated reason — voicemail PIN, caller-ID allow-list, toggling `is_active` — and
the phone quietly stops provisioning, with no warning and nothing in the UI
indicating a link ever existed. Treat this as a data-loss bug, not just a
modeling awkwardness.

Meanwhile the correct multi-device model already exists and is well built:

Meanwhile the correct multi-device model already exists and is well built:
`EndpointBinding` supports `desk_phone`, `mobile_app`, `softphone`,
`pstn_forward`, and `agent_endpoint`, with push capability, platform, registration
timestamps, and runtime validation
(`backend/app/Models/EndpointBinding.php:11-45,120-146`). The platform even
advertises up to 5 simultaneous devices per extension
(`backend/app/Services/Admin/CapabilityService.php:43-48`).

**Recommendation.** Make the hierarchy explicit and one-directional:

```text
User (person)
  └── Extension (their line — or a shared/common-area line with no user)
        └── EndpointBinding[]  (desk phone, web phone, mobile app, PSTN forward)
              └── DeviceProfile (provisioning material for a physical phone)
```

Keep `owner_type` only to distinguish **person-owned** from **shared/common-area**
extensions (break room phone, lobby phone) — that is a real and useful
distinction. Devices stop being owners and become bindings.

### 2.3 Two competing extension↔device links, reconciled destructively in one direction

Both directions exist:

- `Extension.device_profile_id` → device (`backend/app/Models/Extension.php:28`)
- `DeviceProfile.extension_id` → extension
  (`backend/app/Models/DeviceProfile.php:30,65-68`)
- Plus `DeviceProfile::ownedExtensions()` reading back through
  `device_profile_id` (`DeviceProfile.php:71-74`)

Both are independently writable (`StoreDeviceProfileRequest.php:21`,
`StoreExtensionRequest.php:41-56`). Reconciliation does exist — an earlier
revision of this document said it did not — but it runs only extension → device
and it **clears** the reverse link rather than preserving it
(`ExtensionController::syncOwnedDevice()`, `:232-250`, see §2.2). The device side
never reconciles back. So the two can disagree until an extension save resolves
the disagreement by discarding the device's claim.

**Recommendation.** Pick device → extension as the single source of truth (a
physical phone is provisioned *for* a line), make the reverse accessor derived and
read-only, and add a migration that reconciles existing rows. In the interim, the
narrow fix is to make `syncOwnedDevice()` distinguish "field omitted" from
"explicitly cleared" so an unrelated extension edit stops unlinking hardware.

### 2.4 The Users page knows nothing about telephony

`UsersPage.tsx` and `UserFormPage.tsx` contain **no** reference to extensions at
all — no column, no assign action, no indication a person even has a line.
Yet the API exposes `primary_extension_id` and `extension_ids`
(`frontend/src/types/models.ts:419-431`) and the model backs them
(`backend/app/Models/User.php:89-97`).

The only way to link a person to a line is to go to Extensions, find the right
number, and set `owner_type = user` — i.e. number-first navigation for a
person-first task.

**Recommendation.** Add an Extension column to the users table and an
"Extensions" section to the user form, and make the primary onboarding path
person-first: *Add person → assign extension → add their devices*. Both
directions should work; the person-first one should be the default.

### 2.5 A raw UUID is the "Assigned Extension" column

`DeviceProfilesPage.tsx:89` renders `{deviceProfile.extension_id || '-'}` — a
bare UUID in the column a human reads to answer "which line is this phone on."

**Recommendation.** Render extension number + owner name, linked to the
extension detail page.

### 2.6 No device inventory or per-device registration truth

`ExtensionDetailPage.tsx` shows a single SIP credential block and one
WebRTC-support badge (`ExtensionDetailPage.tsx:127-200`). There is no list of the
extension's devices, no per-device registration state, no last-seen, no
transport, no push capability — even though `EndpointBinding.last_registered_at`,
`last_seen_at`, `DeviceRegistrationSnapshot`, and multi-registration support all
exist.

The extensions list is similarly single-valued: status is keyed by extension
number and collapses to `Registered`/`Unregistered` with one IP column
(`ExtensionsPage.tsx:52-61,196-205`), which cannot express "desk phone online,
mobile app offline."

`EndpointBinding` has API routes, but only under a `mobile-devices` name
(`backend/routes/api.php:167-171`), and **no frontend file references them at
all**.

**Recommendation.** Add a "Devices & apps" panel to the extension detail page,
one row per binding: type, registration state, last seen, transport, push
status, plus per-device actions (reprovision, flush registration — the latter
already exists at `SipStatusController.php:195`). In the extensions list, show
"2 of 3 devices online" instead of a single boolean.

### 2.7 Terminology fights the mental model

Three different names describe overlapping concepts: nav "Devices" means
provisioning profiles (`SuperadminLayout.tsx:78`), the API says
`mobile-devices` for endpoint bindings, and the extension form says "Owner
type" — a schema word, not a user word.

**Recommendation.** Standardize on: **People** (users), **Lines** (extensions),
**Devices & apps** (endpoint bindings), **Phone provisioning** (device
profiles). Relabel "Owner type" to "Used by" with options
`A person / A shared device / Not assigned yet`.

---

## 3. WSS / WebRTC enablement

### 3.1 What exists today

- WSS lives on the global FreeSWITCH `internal` profile as raw settings
  (`wss-binding`, `tls`, `tls-*`, `dtls-srtp`), superadmin-only under
  `/admin/sip-profiles` (`SuperadminLayout.tsx:105`,
  `backend/routes/api.php:104`)
- The form does have a genuinely good affordance: one "WebRTC Transport" toggle
  bulk-enables the ~11 interdependent params with sane defaults
  (`frontend/src/pages/admin/SipProfileFormPage.tsx:73-119,280-309,609-663`)
- Server-side validation refuses incoherent combinations — WSS without
  `tls-cert-dir`, or without the required booleans
  (`backend/app/Http/Controllers/Api/SipProfileController.php:112-185`)
- Per-extension WebRTC config is derived from that one profile
  (`backend/app/Services/WebRtcConfigService.php:11-24`)

The single-toggle design is the right instinct and should be the template for
other low-level surfaces in this product.

### 3.2 There is no per-organization WSS enablement — it is all tenants or none

`WebRtcConfigService::forExtension()` reads the global `internal` profile and
returns `enabled` purely from binding + DTLS state
(`WebRtcConfigService.php:11-41`). No organization setting, column, or feature
flag participates. `CapabilityService` is read-only and global
(`backend/app/Services/Admin/CapabilityService.php:9-60`).

So the requested "activate WSS for all or just a single client" is currently
impossible: you cannot pilot the web phone with one friendly customer, stage a
rollout, or withhold it from a tenant whose network cannot support it.

**Recommendation.** Two layers, which together give exactly the requested
control:

1. **Transport layer (platform)** — the existing global WSS binding. Keep it
   superadmin-only; it is genuinely infrastructure.
2. **Entitlement layer (per tenant)** — a first-class
   `webrtc_enabled` tri-state (`inherit | on | off`) on the organization.

**The enforcement point matters, and it is not `WebRtcConfigService`.** An earlier
revision of this document recommended checking the flag in
`WebRtcConfigService::forExtension()`. That would gate *metadata only*: its sole
consumer is a read-only `sipConfig` endpoint
(`backend/app/Http/Controllers/Api/ExtensionController.php:177-188`), so
returning `enabled: false` changes nothing on the wire. Credentials still go to
FreeSWITCH for every active extension of any operational organization, with no
transport awareness (`DialplanCompiler.php:45-48,82`), and WSS shares the single
`internal` profile and directory with port 5060
(`backend/docker/freeswitch/conf/sip_profiles/wss.xml:1,11-12`). A user in a
non-entitled tenant who knows their SIP password could point a WebRTC client at
the global WSS URL and authenticate normally.

Real enforcement needs one of:

- **Transport-aware directory auth** — consult the org flag in the XML-CURL
  directory path and refuse `sip_auth` when the registering transport is
  `ws`/`wss` and the tenant is not entitled. Note this needs new plumbing: the
  handler currently discards everything except domain and user
  (`backend/app/Http/Controllers/FreeswitchXmlController.php:60-67`), even though
  transport is available elsewhere in the payload (`DialplanCompiler.php:124`).
- **Or a separate WSS profile plus ACL**, entitling tenants at the profile
  boundary instead of per registration — likely the simpler option, and it also
  removes the shared-profile restart blast radius in §3.3.

`WebRtcConfigService` should still report the same flag, but only so the UI agrees
with the server — not as the control.

Then give superadmins a rollout screen: *Web phone enabled for:
`All organizations` / `Selected organizations` (+ picker)*, and put a "Web phone"
toggle on the organization form that reads `Requires platform WSS (currently
active)` — disabled with an explanatory link when the transport is off.

### 3.3 One save can restart the shared profile many times, and failures are swallowed

`SipProfileSetting::booted()` recompiles all profiles to disk and then issues
`reloadxml` plus `sofia profile <name> restart` on **every saved setting row**
(`backend/app/Models/SipProfileSetting.php:34-53`). The WebRTC toggle writes
~11 settings in one submit (`SipProfileFormPage.tsx:283-295`), and the
controller upserts them in a loop (`SipProfileController.php:86-97`).

That is up to eleven restarts of the `internal` profile from a single click.
Every restart drops registrations for **every tenant** on that profile, and
in-progress calls are at risk. Worse, ESL errors are caught and discarded
(`SipProfileSetting.php:51-52`), so the UI reports success whether or not
FreeSWITCH ever applied the change.

**Recommendation.** Debounce the apply step: collect dirty profiles during the
request and issue one `reloadxml` + one `restart` per profile after the
transaction commits. Surface the result — `Applied HH:MM` vs
`Saved, pending switch reload` with a retry — instead of swallowing the
exception. And warn before a restart that drops registrations, with an
"apply during maintenance window" option.

### 3.4 Enabling WSS has no preflight and no diagnosis

`WebRtcConfigService` reports `enabled: false` when the binding is present but
`dtls-srtp` is off (`WebRtcConfigService.php:38-41`) with no explanation
reaching any screen. Certificates, port 7443 reachability, `ext-sip-ip`, and
TURN are all outside the form's field of view. STUN/TURN come only from
`config('telephony.webrtc')` env (`WebRtcConfigService.php:80-98`), so an admin
cannot fix a client stuck behind symmetric NAT from the UI at all.

The pattern for fixing this already exists: `provisioning_health` on the
organization resource (`OrganizationResource.php:29,44-57`) and
`TelephonyRuntimeHealthService`.

**Recommendation.** Add a "WebRTC readiness" check in the same shape —
certificate present and not expiring, WSS port listening, DTLS enabled, TURN
reachable, `mod_sofia` profile up — rendered as a checklist with next actions on
both the SIP profile page and the organization's web phone toggle. Promote
STUN/TURN to admin-editable platform settings.

### 3.5 A second file declares the same profile name

`backend/docker/freeswitch/conf/sip_profiles/wss.xml` declares
`<profile name="internal">` with hardcoded `ws-binding :5066` and
`wss-binding :7443`, while the live profile is generated to
`backend/storage/app/freeswitch/sip_profiles/internal.xml`
(`backend/app/Services/SipProfileCompiler.php:14-40`) — the directory the
container actually mounts (`docker-compose.telephony.yml:62`).

The static file is not mounted today, so it is dormant rather than broken. But
it is a second, divergent definition of the same profile name sitting in the
tree, which is how "I toggled it in the UI and nothing changed" incidents start.

**Recommendation.** Delete it, or rename it to `internal.example.xml` with a
header comment saying it is a non-loaded reference sample.

---

## 4. Outbound caller ID selection

### 4.1 What exists today — and it is good

Per-extension caller-ID entitlement is properly modeled:

- Many-to-many allow-lists plus defaults: `allowed_outbound_did_ids`,
  `allowed_outbound_gateway_ids`, `default_outbound_did_id`,
  `default_outbound_gateway_id`
  (`backend/app/Http/Resources/ExtensionResource.php:16-20`)
- `PhoneNumberAccessResolver` enforces the allow-list, organization ownership,
  and active state, and refuses a default that is not allowed
  (`backend/app/Services/PhoneNumberAccessResolver.php:36-76`)
- The form is well built: checkbox allow-lists, a default select constrained to
  the allowed set, and cross-field validation that clears the default when its
  DID is unchecked (`ExtensionFormPage.tsx:85-92,679-717,756-780`)
- `POST calls/originate` accepts a per-call `did_id`
  (`CallController.php:38-42`) and the originate string sets
  `origination_caller_id_number` from the resolved DID
  (`backend/app/Services/Call/OutboundOriginateService.php:36-52`)

So per-call caller-ID selection **already works end to end on the API path**. A
web phone caller-ID picker is a UI task against a contract that exists.

### 4.2 Registered SIP phones have no outbound route at all

This is the finding that matters most, and it sits underneath the question
rather than beside it.

`compileDialplan()` resolves, in order: pre-routing policies, inbound DID,
delivery entrypoint, `flow_*`, convenience service codes, `did_preset_*`,
internal extensions — then falls through to a 404 failsafe
(`backend/app/Services/DialplanCompiler.php:138-236,1464-1476`). There is no
egress route for an external destination. Correspondingly,
`bridge(sofia/gateway/…)` appears in exactly one place in the entire backend:
`OutboundOriginateService.php:43`.

So a desk phone or softphone registered with SIP credentials, dialing an
external number, gets no route — outbound PSTN works only when the API
originates the call. Caller-ID selection for "normal SIP credential users" is
not a UX gap; the call path itself is missing.

And when that route is added, the naive version will be wrong: the directory
entry sets `effective_caller_id_number` to the **extension number**
(`DialplanCompiler.php:98`), which would present `1001` as the caller ID on the
PSTN.

**Recommendation.** Add outbound route compilation as the prerequisite for
everything else in this section:

- Match external destination patterns per organization, resolve gateway +
  caller-ID DID through `PhoneNumberAccessResolver` (so the allow-list is
  enforced identically on both paths), and export
  `effective_caller_id_number` from the resolved DID rather than the extension.
- Apply the organization privacy mode in the same place
  (`DialplanCompiler.php:100-110`).
- **Explicitly match and reject emergency patterns** — do not route them. Match
  `config('telephony.emergency')` and answer with a spoken "emergency calling is
  not available on this service."
- Fail loudly and legibly when an extension has no allowed DID — a spoken
  "no outbound number is assigned" beats a 404.

On that third point, an earlier revision of this document recommended the
opposite: an emergency override presenting "the location-registered number."
**That was unsafe advice and is withdrawn.** NIZAM documents emergency calling as
unsupported — *"NIZAM does not support emergency calling in v1.0"*
(`backend/config/telephony.php:71-73`) and *"No location (PIDF-LO) or PSAP routing
is provided,"* with the stated remedy being a dedicated E911 provider outside
NIZAM (`backend/docs/KNOWN_LIMITATIONS.md:122-138`). No location registry exists
in the schema, and `grep -rn emergency backend/app` returns nothing, so the
configured patterns are never read by any code today.

A caller-ID override would have produced the *appearance* of E911 support with no
routing behind it — the specific failure mode the limitations doc warns about
("prevent accidental reliance on an untested path"). Blocking is the correct
behavior, and wiring it is a **blocking requirement of adding egress at all**:
today there is no outbound route, so emergency numbers fail closed by accident.
The moment §4.2 is implemented, they would start reaching a carrier without
location data unless rejection is built in the same change.

### 4.3 No way for a SIP-credential user to pick a number per call

Even once the route exists, a desk-phone user has no selection mechanism: no
feature-code prefix, no per-device default. `EndpointBinding` carries no
caller-ID override (`backend/app/Models/EndpointBinding.php:48-70`), so
"the desk phone shows Sales, the mobile app shows the main line" is
unexpressible.

**Recommendation.** Generate a prefix route per allowed DID (e.g. `*71<number>`
= second allowed DID) and render the mapping as a copyable cheat sheet on the
extension page — desk-phone users need something they can tape to a monitor. Add
an optional caller-ID override per endpoint binding for the per-device case.

### 4.4 The web phone does not exist yet, and the API lets clients spoof the name

`frontend/package.json` has no SIP stack — no SIP.js, no JsSIP
(`frontend/package.json:24-49`) — and no component references a softphone. The
"built-in web phone" is a plan, not a shipped surface. What exists is the
provisioning payload it would consume (`WebRtcConfigService`) and the originate
contract it would call.

One security-relevant detail is live **today**, independent of whether the web
phone ever ships: `caller_id_name` is accepted as `nullable|string` with no
allow-list check (`CallController.php:34`) and passed straight into
`origination_caller_id_name` (`OutboundOriginateService.php:36,49`). Any user
who passes the `originate` gate — which, per §8.1, includes any agent with zero
explicit permission grants — can present an arbitrary display name on an
outbound PSTN call: "IRS", a colleague's name, anything. `did_id` is properly
validated against the extension's allow-list; the name is not validated at all.

**Recommendation — server-side, and not gated on the web phone.** Derive
`caller_id_name` from the resolved DID or the extension's configured
`effective_caller_id_name`, and reject client-supplied names outright. Treat this
as a blocking fix alongside the other authorization items in Wave 0; the display
name is the half of caller ID a recipient actually reads, so leaving it
spoofable while the number is locked down defeats the point of the allow-list.

**Recommendation — client-side, when the web phone is built.** Make the caller-ID
picker a first-class control in the dialer (default from
`default_outbound_did_id`, options from the allow-list, showing
`+1 555 0100 — Sales main` using the DID `description` from
`DidResource.php:18`), remember the last choice per device, and show the active
number persistently while on a call.

### 4.5 Dead caller-ID fields and a hidden privacy setting

`outbound_caller_id_name` and `outbound_caller_id_number` are fillable on
`Extension` (`Extension.php:38-39`) but `prohibited` in both store and update
requests (`StoreExtensionRequest.php:126-127`,
`UpdateExtensionRequest.php:129-130`) and read by no service. They are dead
columns that will mislead the next developer into thinking per-extension
caller-ID override is supported.

Separately, caller-ID privacy is real but hidden: `outbound_caller_id_privacy`
is read from the organization's JSON settings blob
(`DialplanCompiler.php:100-110`) — discoverable only by reading the compiler
source (see §9.1). There is no per-extension or per-call "hide my number"
control.

**Recommendation.** Drop the dead columns or wire them up as the documented
per-extension override. Promote privacy mode to a typed organization setting
with a labeled control, and add a per-call privacy toggle to the web phone plus
a star-code equivalent for desk phones.

---

## 5. Call visibility and troubleshooting

This area was not in the original brief, but it is inseparable from
"maintaining VoIP service": it is where support tickets are resolved, and it is
the thinnest surface in the product relative to the data already collected.

### 5.1 Call History has no phone numbers on it

The table columns are: icon, truncated UUID, state, outcome, start time, and a
detail link (`frontend/src/pages/admin/CallHistoryPage.tsx:176-227`). Direction
is hardcoded dead — `{directionIcon(null)}` with a comment saying the field is
"coming from CDR reconciliation soon" (`CallHistoryPage.tsx:190`).

The cause is that the page queries `/calls` → `CallSession`, which has no
from/to/direction/duration fields
(`backend/app/Models/CallSession.php:17-28`,
`backend/app/Http/Resources/CallSessionResource.php:38-63`), while
`CallDetailRecord` already carries `caller_id_number`, `destination_number`,
`direction`, `duration`, `billsec`, `hangup_cause`, `recording_path`, and
`mos_score` (`backend/app/Models/CallDetailRecord.php:21-48`) — exposed at
`GET …/cdrs` (`backend/routes/api.php:209`) with nothing consuming it.

A call log where you cannot see who called whom is not usable for the job it
exists to do.

**Recommendation.** Repoint Call History at the CDR endpoint (or join CDR into
the session resource) so the primary columns are From, To, Direction, Start,
Ring, Talk, Result, Recording.

### 5.2 Filters, pagination, and export all exist server-side and are unused

- `CdrSearchService` already implements search, direction, call type, caller and
  destination number, UUID, `hangup_cause`, date range, duration range, MOS, and
  tags (`backend/app/Services/Cdr/CdrSearchService.php:21-88`). The page sends
  **no parameters at all** (`CallHistoryPage.tsx:93-100`).
- A streaming 19-column CSV exporter exists at `GET|POST …/cdrs/export`
  (`backend/routes/api.php:207-208`) with no button anywhere.
- **The KPI tiles are wrong by construction.** The backend paginates 15 per page
  (`backend/app/Http/Controllers/Api/CallSessionController.php:31`), the page
  discards `meta`/`links`, and then computes "Total Calls", "Answered", and
  "Missed" from that 15-row slice
  (`CallHistoryPage.tsx:96-97,124,133,143`). "Total Calls" can never exceed 15,
  and an admin has no way to know the number is a lie.

**Recommendation.** Bind a filter bar 1:1 to the existing search params, wire the
paginator, add an Export CSV button, and move the counters to a server-side
aggregate (`cdrs/analytics/summary` already exists at
`backend/routes/api.php:212-217`).

### 5.3 No live call visibility at all

`calls/status`, `calls/hangup`, `calls/transfer`, `calls/recording`,
`calls/hold`, and `calls/originate` are all routed and gated
(`backend/routes/api.php:200-205`,
`backend/app/Http/Controllers/Api/CallController.php:103-201`). **No frontend
file calls any of them** — verified by grep across `frontend/src`.

So a supervisor cannot see a single call in progress, cannot pick up or
intervene, and cannot start recording on a call that is going wrong — which is
precisely the moment on-demand recording exists for (§1.6). The SSE live event
stream `call-events/stream` (`backend/routes/api.php:190`) is likewise unused.

**These endpoints are not tenant-safe, and that changes the work.** An earlier
revision of this document called an Active Calls page "frontend-only work". That
was wrong and would have been dangerous advice:

- `CallController::status()` runs `show channels as json` and returns every row
  the switch reports, with **no organization filter** — the route's
  `Organization $organization` argument is never used to narrow the result
  (`backend/app/Http/Controllers/Api/CallController.php:103-121`). On a shared
  FreeSWITCH that is every tenant's live calls, including caller and callee
  numbers.
- `hangup`, `transfer`, `hold`, and `toggleRecording` each accept an arbitrary
  channel UUID and pass it straight to `uuid_kill` / `uuid_transfer` /
  `uuid_hold` / `uuid_record` with **no check that the channel belongs to the
  caller's organization**
  (`CallController.php:126-225`). Combined with default-open permissions (§8.1),
  an agent in one tenant can hang up or start recording another tenant's call if
  they learn a UUID — and `status()` hands out every UUID on the switch.

**Recommendation.** Backend first: filter `status()` by the organization's
domain/`CallSession` records, and resolve every inbound UUID through a
`CallSession` lookup scoped to the route's organization before dispatching an ESL
command. Only then build the Active Calls page. This belongs in Wave 0 with the
other authorization items, not in the UI wave.

### 5.4 "Why didn't my phone ring?" has no answer surface

The diagnostic corpus is unusually complete and almost entirely unreadable:

- `calls/{id}/analyze` (`backend/routes/api.php:199`) — no UI
- `call-events/{callUuid}/trace`, `replay/{eventId}`, `redispatch/{eventId}`
  (`backend/routes/api.php:191-193`) — no UI, including a write-capable
  recovery tool with no operator access
- `DeviceRegistrationSnapshot` — the table that answers whether the phone was
  even registered when the call arrived — is consumed only by
  `PresenceAggregator` (`backend/app/Services/Presence/PresenceAggregator.php:43,75,112`)
  and never joined to a call
- Delivery failure reasons pass through as raw strings
  (`backend/app/Services/Interaction/InteractionOverviewService.php:191`), and
  the `ReachabilityResolver` / `DeliveryPlanner` decisions that explain *why* an
  endpoint was skipped never reach the payload
- `SipStatusPage` shows current registrations only, superadmin-only
  (`frontend/src/layouts/SuperadminLayout.tsx:116`), so a client admin has no
  registration history at all

`InteractionDetailPage` is the bright spot — it renders a merged timeline,
delivery attempts, push logs, and trace errors
(`frontend/src/pages/admin/InteractionDetailPage.tsx:368-680`). But its header
shows trace duration rather than call duration, has no hangup cause in any form,
no ring-vs-talk split (despite `CallDeliveryAttempt` storing
`started_at`/`answered_at`/`ended_at` at
`backend/app/Models/CallDeliveryAttempt.php:77-88`), and identifies the
answering device only as e.g. "ios webrtc"
(`InteractionDetailPage.tsx:98,182,492`).

**Recommendation.** On the interaction detail page add: a plain-language
"Why it ended" row mapping `hangup_cause`; Ring / Talk / Total per leg;
"Registration state at call time" from the nearest `DeviceRegistrationSnapshot`;
and a per-attempt "why this endpoint was or wasn't tried" verdict from the
reachability decision. That last pair resolves most of this ticket class without
a single new data source.

### 5.5 Pipeline health and alerting are invisible or dead

- `ProcessedCdrFile` has a `STATUS_FAILED` state written by
  `XmlCdrIngestionService::markFailed`
  (`backend/app/Services/Cdr/XmlCdrIngestionService.php:88-100`) that no API or
  UI ever reads. The `xml-cdr-watcher` worker can die silently.
- `/health` checks db, cache, ESL, SIP runtime, FreeSWITCH, gateways, and
  registrations — but not CDR ingestion
  (`backend/app/Http/Controllers/Api/HealthController.php:38-53`). It feeds both
  the dashboard and SIP status page, so adding a check there inherits two UIs
  for free.
- `cdrs_today` is the only pipeline signal a human sees
  (`frontend/src/pages/admin/DashboardPage.tsx:333,389`) — and `0` looks
  identical to "the watcher crashed".
- **The alerting subsystem is dead code.** `AnomalyDetectorService` computes
  abandon rate, webhook failures, gateway flapping, and SLA drops and creates
  `Alert` rows (`backend/app/Services/AnomalyDetectorService.php:21-188`), and
  `AlertService` exists alongside it — but neither has any caller in `app/`,
  `routes/`, or `modules/`, and the scheduler runs only
  `nizam:prune-recordings` (`backend/routes/console.php:25`). `Alert` and
  `AlertPolicy` are tables that are never written and never read.

**Recommendation.** Add a `cdr_ingestion` check to `/health`; schedule
`AnomalyDetectorService` on the cron; add an Alerts inbox with policy CRUD.
Without this, the first person to notice a broken tenant is the tenant.

### 5.6 Reporting is almost entirely API-only

No UI exists for: `cdrs/analytics/{summary,volume,quality,destinations}`
(`backend/routes/api.php:212-217`), all three `supervisor-reports/*`
(`backend/routes/api.php:219-223`), `usage/{summary,collect,reconcile}`
(`backend/routes/api.php:132-134`), queue realtime/history metrics, `wallboard`,
`agent-states`, or `codec-metrics` (`backend/routes/api.php:180-185`).
`InsightsService` (`backend/app/Services/InsightsService.php:19-133`) has no API
at all — only a unit test.

**Recommendation.** A Reports nav section with one page per endpoint group is the
highest value-per-effort addition in this document: pure frontend work against
shipped, authorized APIs.

---

## 6. Self-service and contact-center surfaces

### 6.1 Agents are dropped into the admin console with near-full access

There is one layout and one route tree: `/login` and `/admin/*`
(`frontend/src/app.tsx:123-205`). `ProtectedRoute` checks only for a session
(`app.tsx:74-78`), `GuestRoute` sends every authenticated user to `/admin`
(`app.tsx:85`), and `AuthContext` never reads `user.role`
(`frontend/src/context/AuthContext.tsx:29-79`).

Nav gating has only `superadminOnly` and `adminOnly` flags applied as a
deny-list (`frontend/src/layouts/SuperadminLayout.tsx:55-57,143-151`), and
nothing in Phone System or Contact Center carries either flag
(`SuperadminLayout.tsx:77-98`). An `agent`-role user therefore sees Extensions,
Devices, Directory, Office Features, Call Flows, Media Library, Queues, Teams,
Agents, and Call History — with their role rendered as the raw string `agent`
(`SuperadminLayout.tsx:137-141`), because the UI has no label for them.

Combined with default-open permissions (§1.8), that agent can also **edit every
extension in the tenant**, including other people's voicemail PINs
(`backend/app/Policies/ExtensionPolicy.php:36-40` checks only permission slug
plus same-organization; there is no "own extension" concept in any policy).

**Recommendation.** Invert nav gating to an allow-list (`minRole`) so unknown or
low roles get nothing by default; add a `/portal` tree and land non-admins
there; add an "own extension" scope to `ExtensionPolicy` for self-service writes.

### 6.2 The one purpose-built self-service endpoint has no caller

`PUT organizations/{org}/extensions/{ext}/features`
(`backend/routes/api.php:139`) is fully implemented and validated
(`backend/app/Http/Controllers/Api/ExtensionFeatureController.php:19-52`,
`backend/app/Services/ExtensionFeatureService.php:27-71`, including follow-me
endpoint-binding sync). **No frontend file calls it.** DND and follow-me reach
the UI only as two fields buried in the 900-line admin extension form
(`ExtensionFormPage.tsx:833,865`), gated behind `extensions.update`.

Everything else per-user is reachable only by dialling a star code: call
forwarding `*72`/`*73`/`*74`, call return `*69`, send-to-voicemail `*99`
(`backend/app/Services/DialplanCompiler.php:723-757`).

An earlier revision said forwarding was "dialplan-only with no persisted state."
**That was wrong.** `_call_forward.lua` writes straight to the database — setting
`extensions.follow_me_enabled`, `follow_me_destination`, and clearing
`dnd_enabled` (`backend/docker/freeswitch/scripts/custom/_call_forward.lua:191-203`)
— then rebuilds the `pstn_forward` endpoint binding and invalidates the manifest
(`:214-287`). Those are the same fields the features endpoint already reads and
writes (`ExtensionFeatureController.php:48-49`) and that `ExtensionResource`
already serializes (`:28-29`).

So the gap is purely presentational, which makes it cheaper to close than the
original framing implied: the state is there, correct, and API-addressable — no
screen ever shows it. A user who forwards their line by star code has no way to
see or cancel it, and neither does their admin.

One real inconsistency to resolve before displaying it: the Lua stores the
**raw** dialled destination on the extension (`:202`) but the **normalized E.164**
form on the binding (`:272`), so the two rows can disagree in format and any
"Forwarding to X" UI must pick one deliberately.

**Recommendation.** Build a "My phone" page whose only writes go to the existing
`features` endpoint — DND toggle, follow-me destination, and a "Forwarding to X —
cancel" row. No new persistence is needed.

### 6.3 Voicemail has no message store and no mailbox UI

There are **no voicemail routes** in `backend/routes/api.php`, no `Voicemail`
model, and no voicemail migration. The module is event-only:
`VoicemailEventService` writes to `CallEventLog`
(`backend/app/Modules/Voicemail/VoicemailEventService.php:16,70`). In the
frontend, voicemail appears only as two admin extension fields, a read-only
badge, and a flow-builder deposit node. Per-user greetings do not exist; the
only way to hear a message is to dial `*98`.

For a hosted PBX in 2026, "voicemail is dial-in only" is a competitive
liability, not just a UX gap.

**Recommendation.** Add a `voicemail_messages` table fed from
`handleReceivedPayload`, expose list/delete/stream endpoints, then a portal inbox
with an inline player and per-user greeting upload.

### 6.4 The contact-center backend has no operator surfaces

- `POST agents/{agent}/state` exists (`backend/routes/api.php:175`) but
  `AgentsPage` only edits and deletes; state is a read-only badge
  (`frontend/src/pages/admin/AgentsPage.tsx:105-107`). Agents cannot set
  themselves Available / Break / Wrap-up.
- The whole wallboard pipeline — `WallboardProjectionService`,
  `WallboardReadService`, and the `wallboard` / `agent-states` endpoints — has
  **zero UI consumers**, despite the projections being denormalized for exactly
  that purpose.
- `QueueDetailPage` fetches queue, members, and agents with no
  `refetchInterval` (`frontend/src/pages/admin/QueueDetailPage.tsx:62-82`) while
  `queues/{queue}/metrics/realtime` and `/history` sit unused.

**Recommendation.** Add an agent state control on the portal home, a
`/admin/wallboard` page polling the existing endpoint, and a realtime strip
(calls waiting, longest wait, SLA) on queue detail.

### 6.5 The directory is a two-column table

`DirectoryPage` renders Name and Extension only
(`frontend/src/pages/admin/DirectoryPage.tsx:73-98`); `DirectoryService::search`
LIKE-matches three fields, capped at 50 with no pagination
(`backend/app/Services/DirectoryService.php:16-35`). Meanwhile
`GET extensions/status/all` (bulk registration state) and
`POST calls/originate` both exist and are never called from it.
`PresenceAggregator` already resolves status from registration snapshots and
active calls (`backend/app/Services/Presence/PresenceAggregator.php:19,51`) and
is referenced by nothing but its own test.

**Recommendation.** Expose `GET …/presence` over `PresenceAggregator`, then add
a presence dot and a click-to-call button per directory row — three existing
backends, one new UI.

---

## 7. Day-1 tenant onboarding

### 7.1 A complete readiness model is computed and thrown away

`OrganizationProvisioningHealthService::evaluate()` returns `status`, `summary`,
`warning_count`, `blocker_count`, `checks[]`, and human-written `next_actions[]`
like *"Assign main DID"* and *"Publish inbound routing"*
(`backend/app/Services/Organization/OrganizationProvisioningHealthService.php:33-51,215-235`).
It is serialized on **every** organization payload
(`backend/app/Http/Resources/OrganizationResource.php:29`).

The frontend never reads it. It is not even in the zod schema
(`frontend/src/types/models.ts:40-60`), so it is silently dropped on arrival.

Instead of that checklist, the UI shows raw UUIDs: `OrganizationsPage.tsx:153-169`
renders an "Organization defaults" column that is just `default_schedule_id` and
`default_holiday_calendar_id` in monospace, and `OrganizationFormPage.tsx:309-320`
repeats them as two mono badges. The accompanying copy is an implementation note
— *"Default schedule and holiday calendar are provisioned by backend
business-phone setup"* (`OrganizationFormPage.tsx:299-303`) — rather than
guidance about what to do next. After create, the admin is returned to the list
(`OrganizationFormPage.tsx:213-216`), discarding a 201 body that contained the
entire readiness payload.

Notably, `DashboardPage.tsx:266-279` already implements exactly the right card
for this — message, `Recommended action:`, expected-vs-loaded detail — fed by
`TelephonyRuntimeHealthService`. The org payload has the same shape and no
consumer.

**Recommendation.** Reuse that card. Show `provisioning_health.status` as a badge
in the organizations table, render `next_actions` as a setup checklist on the
dashboard with each item linking to the page that fixes it, and route
post-create to `/admin/organizations/:id/setup` seeded from the 201 body.
Replace the UUID badges with resolved names.

### 7.2 The auto-created starter DID is a fabricated number that looks real

`OrganizationEntrypointProvisioningService` mints an entrypoint number as
`'+1999' . sprintf('%010u', crc32($organization->id))`
(`OrganizationEntrypointProvisioningService.php:209-214`). It appears in
`DidsPage` styled identically to a real number and badged **Active**
(`DidsPage.tsx:169-171,187-189`), described only as "Default Business Phone
Entrypoint". An admin can reasonably conclude inbound calling works.

Worse, the health check identifies that DID by matching that exact English
description string
(`OrganizationProvisioningHealthService.php:198-208`). The DID description is a
plain free-text input (`DidFormPage.tsx:592-604`), so an admin who edits it
silently flips `entrypoint_did` to blocked with no warning.

**Recommendation.** Add an `is_placeholder` (and `is_entrypoint`) flag column;
badge placeholder numbers as *"Placeholder — not dialable"* with a "Replace with
your real number" CTA; stop identifying system objects by display text.

### 7.3 There is no trunk page, and trunks are created as a side effect of a DID

- **No gateway/trunk route exists in the frontend at all**
  (`frontend/src/app.tsx:147-168`), while the backend has two full CRUD surfaces
  (`backend/routes/api.php:103,172`) and even a finished design at
  `backend/docs/stitch-designs/html/Gateway_Detail.html`.
- The only way to create one is a "Provider" tab on the DID form
  (`DidFormPage.tsx:748-755`), and `NumberProviderController::store` creates a
  **brand-new gateway per DID** with a hardcoded `'profile' => 'external'`,
  refusing a second (`backend/app/Http/Controllers/Api/NumberProviderController.php:27-35`).
  Ten numbers on one carrier means ten duplicated credential records, and no
  screen lists them.
- **Trunk registration status is superadmin-only**, so the org admin who owns the
  trunk sees `Unknown` forever with no explanation
  (`DidsPage.tsx:83-91,197-199`;
  `backend/app/Http/Controllers/Api/SipStatusController.php:79`) — even though an
  org-scoped `gateways/{gateway}/status` route already exists
  (`backend/routes/api.php:149`).
- Raw FreeSWITCH state strings are the status badge: `REGED`, `NOREG`,
  `UNREGED`, `FAIL_WAIT`, taken unmodified from `sofia status gateway`
  (`SipStatusController.php:277-285`, colour-sniffed at `DidsPage.tsx:46-62`).
- **Trunk provisioning failures are swallowed and reported as success.**
  `GatewayObserver::syncCreated` catches and logs
  (`backend/app/Observers/GatewayObserver.php:36-46`),
  `FreeSwitchGatewayLifecycleExecutor::execute` asserts nothing
  (`backend/app/Services/Media/FreeSwitchGatewayLifecycleExecutor.php:11-68`),
  and the UI fires `toast.success('Provider saved.')` regardless
  (`DidFormPage.tsx:445`).
- **Silent 15-trunk cap.** `ExtensionFormPage.tsx:287-289` asks for
  `per_page: 500`; `GatewayController::index` hardcodes `paginate(15)` and
  ignores it (`backend/app/Http/Controllers/Api/GatewayController.php:25`). Past
  15 trunks the rest are invisible in "Allowed outbound gateways" with no
  truncation notice.
- **No test-call affordance anywhere** — zero frontend references to
  `calls/originate` despite the endpoint existing (`backend/routes/api.php:200`).

**Recommendation.** Ship `/admin/trunks` (list + detail) against the existing
controller; make the DID Provider tab a select over existing trunks with
"+ New trunk"; expose the org-scoped gateway status to org admins; translate
gateway states to plain language with the raw value in a tooltip; return the
lifecycle result and stop reporting unconditional success; honour `per_page`;
add "Place test call" on trunk and DID detail.

### 7.4 Empty states and error handling

- The highest-stakes empty state in the product — no numbers, therefore no
  inbound calling — is the dead sentence "No numbers assigned."
  (`DidsPage.tsx:229-231`), with no CTA and no mention that a trunk is a
  prerequisite.
- Five pages share the identical string "No X found. Create one to get
  started." (`QueuesPage.tsx:86-88`, `TeamsPage.tsx:87-89`,
  `DeviceProfilesPage.tsx:80-82`, `AgentsPage.tsx:94-96`), and
  `UsersPage.tsx:172-174` is just "No users found."
- **Nine pages have `useMutation` calls with no `onError`**: `DidsPage`,
  `ExtensionsPage`, `OrganizationSettingsPage`, `OrganizationsPage`,
  `SipStatusPage`, `SystemSettingsPage`, `UserFormPage`,
  `UserPermissionsPage`, `UsersPage`. A failed org delete (FK constraint, active
  calls, 403) shows nothing at all — the dialog simply stays open
  (`OrganizationsPage.tsx:53-67`). The correct pattern already exists in the
  `useApiMutation` helper used by `QueueFormPage.tsx:89-99`.
- `OrganizationFormPage.tsx:217-230` maps errors only for a hardcoded allowlist
  of nine field names; a 500, a 403, or a quota rejection produces no toast and
  no message — the Save button just re-enables.
- `OrganizationSettingsPage.tsx:87-100` has neither `onError` nor success
  feedback, so saving the JSON settings blob gives no confirmation either way.
- Domain validation reports uniqueness against the *composed* domain
  (`StoreOrganizationRequest.php:37`) with no `messages()` override, so an admin
  who typed `acme` into a field labelled "Domain" sees "The domain has already
  been taken." remapped onto the prefix input.

**Recommendation.** Route every mutation through `useApiMutation`; add a fallback
`toast.error` for unmapped failures; give each empty state a purpose line plus
its prerequisite; add `messages()` for domain and prefix validation.

---

## 8. Permission model and multi-tenant safety

The "allow hierarchy" the brief asks for cannot be built on the current
authorization layer. These are the load-bearing problems.

### 8.1 Permissions subtract instead of add

Covered in §1.8 for recordings; it is systemic. `hasPermission()` returns `true`
for any user with zero grants (`backend/app/Models/User.php:126-138`), and
`admin`/`superadmin` short-circuit to `true` before the check, so permissions
only ever constrain agents — and only after someone grants at least one.

The UI does not say this. `UserPermissionsPage.tsx:106-108` reads "Enable or
revoke explicit permissions. Changes save immediately when toggled." For an
admin target every checkbox is decorative; for an agent, unchecking the **last**
box silently widens access to everything.

**Recommendation.** Deny-by-default for non-admin roles, with role presets
seeding a starting grant at user-create time. Until that lands, the page needs a
banner for both states: *"Admins hold all permissions — this list has no
effect"* and *"No explicit grants — this user currently has full access."*

### 8.2 No policy scopes below the organization

All 18 files in `backend/app/Policies/` follow one shape: superadmin bypass, then
`organization_id` equality plus a permission slug. Not one scopes to an
extension, team, or queue. `CallPolicy.php:18-34` does not even take a model —
`originate`, `viewStatus`, and `callControl` are org-wide booleans. The single
self-scoped check in the codebase is `UserPolicy.php:26-32`
(`$user->id === $model->id`).

`CallSessionController::index` has **no authorization at all** — the comment says
"Assuming `Gate::authorize(...)` logic can be wired later"
(`backend/app/Http/Controllers/Api/CallSessionController.php:20-25`). That is the
endpoint Call History calls, so today every org member can read everyone's calls.
`TeamController` and `AgentController` likewise have no `authorize()` calls,
relying only on `EnsureOrganizationAccess`.

**Recommendation.** Add a `scopeVisible(Builder, User)` contract on Recording,
CallDetailRecord, CallSession, and Extension that narrows to own-extension or
supervised-team unless the caller holds an `.all` variant, and call it from every
index endpoint. Split sensitive read slugs into `.own` / `.team` / `.all`.

### 8.3 There is no supervisor, and the existing supervisor flag is inert

Roles are `superadmin | admin | agent` (`UserFormPage.tsx:42`), string-compared
in the backend with no enum (`User.php:105-118`). `Agent::ROLE_SUPERVISOR`
exists (`backend/app/Models/Agent.php:19`) but describes a contact-center agent
row, not a `User`, and no policy or gate ever consults it.

Team and queue membership carry no role: `Team` has no supervisor column
(`backend/app/Models/Team.php:14-22,57-60`), `TeamMember` is
team + endpoint + priority, `QueueMember` is a bare pivot with priority. The
three supervisor report services take an `Organization` and scope wholesale, with
no `team_id` filter, authorized by the same org-wide `viewAny` an agent may
already hold by default.

**Recommendation.** Add a `supervisor` User role and a `team_supervisors` pivot,
link `Agent` to `User`, and pass supervised-team IDs into the report services as
a mandatory filter unless the caller holds an org-wide grant.

### 8.4 Slug drift in both directions

Enforced but never created, therefore ungrantable once a user has any explicit
grant:

- `endpoint_bindings.view|create|update|delete`
  (`EndpointBindingPolicy.php:21-45`) — absent from the core list and from every
  module's `permissions()`.
- `view-flows` / `manage-flows` (`FlowPolicy.php:24,33,38,47,56`) — dash-style,
  while the command creates dot-style `flows.view|create|update|delete`
  (`SyncPermissionsCommand.php:71-74`). Five policy methods check slugs that can
  never exist.

An earlier revision also listed `gateways.view`, `gateways.manage`, and
`calls.control` here. **That was wrong**: the command merges
`ModuleRegistry::collectPermissions()` for enabled modules
(`SyncPermissionsCommand.php:90-97`,
`backend/app/Modules/ModuleRegistry.php:277-290`), `PbxMediaPolicy` contributes
both gateway slugs, `PbxAutomation` contributes `calls.control`, and all six
modules are enabled (`modules_statuses.json`). Those three are created and
grantable. Note the corollary, though: because they arrive only from modules,
disabling `PbxMediaPolicy` silently makes `gateways.*` ungrantable — a coupling
between module state and authorization that nothing surfaces.

Granted but never enforced: `teams.*`, `flows.*`, `users.*`, `cdrs.export`,
`organizations.create|update|delete`. `UserPolicy.php:23-48` hardcodes
`role === 'admin'` instead of checking `users.*`, and `CdrExportController.php:29`
authorizes `viewAny` on CDRs rather than `cdrs.export`.

**Recommendation.** Add a CI test asserting that the set of slugs referenced in
policies and controllers equals the set the sync command creates. This class of
bug is invisible until a customer's permissions behave backwards.

### 8.5 The permission editor is a flat slug grid

`UserPermissionsPage.tsx:119-143` renders one checkbox per permission with the
**slug itself as the label** (`:134`). Grouping is by `permission.module`, which
yields exactly two buckets because the sync command writes module permissions
with `['module' => 'module']` and no description
(`SyncPermissionsCommand.php:93-96`) — so every module row also reads "No
description available." There is no search, no role template, no "copy from
user", no effective-permission preview, and no diff step across ~60 items.

Every toggle is an immediate unbatched write with the whole grid disabled during
each round-trip and no error surface, so a failed revoke leaves a stale but
plausible UI (`UserPermissionsPage.tsx:59-88,130`).

**Recommendation.** Make role presets the primary control (Agent, Supervisor,
Client Admin) with individual slugs behind an "Advanced overrides" disclosure;
batch into one explicit Save with a "granting X / revoking Y" confirmation;
render errors inline. Also rename `CapabilitiesPage` — it is a read-only platform
feature registry with no relationship to permissions
(`CapabilitiesPage.tsx:29-33`).

### 8.6 Sensitive reads are unaudited, and permission changes are not audited at all

`Auditable` hooks exactly three events — `created`, `updated`, `deleted`
(`backend/app/Traits/Auditable.php:9-28`) — and `AuditLog::record` is called from
nowhere else. So:

- Recording **downloads** write no audit row
  (`RecordingController.php:66-85`); only deletion leaves a trace.
- A 50,000-row CSV export of another team's calls is entirely unlogged
  (`CdrExportController.php:27-64`).
- `User`, `Team`, `TeamMember`, and `Permission` do **not** use the trait, and
  `grantPermissions`/`revokePermissions` touch a pivot that fires no model
  events (`User.php:145-160`) — so permission grants, revocations, and role
  escalation are invisible.

**Recommendation.** Emit explicit audit entries for `recording.downloaded`,
`cdr.exported`, `permission.granted`, `permission.revoked`, and `role.changed`,
add them to the badge vocabulary, and wire the audit page's filters (the API
already supports `action`, `user_id`, and date range at
`AuditLogController.php:26-44`; the page sends none of them).

### 8.7 Two concrete multi-tenant exposures

These are the two findings in this document that warrant fixing on their own
schedule rather than as part of a UX wave.

1. **`SipProfileController` has no authorization whatsoever** — no `authorize()`,
   no `Gate::authorize('platform-admin')`, and its route sits in the plain
   `auth:sanctum` group outside `EnsureOrganizationAccess`
   (`backend/app/Http/Controllers/Api/SipProfileController.php:12-105`,
   `backend/routes/api.php:104`). `SipProfile` is a **global** FreeSWITCH object:
   any authenticated user, including an agent, can list, create, mutate, and
   delete platform SIP profiles — which, per §3.3, also triggers a switch
   profile restart affecting every tenant. Comparable controllers
   (`PlatformSettingController`, `LogViewerController`,
   `AdminCapabilityController`, `SipStatusController`) all gate correctly, so
   this is an omission rather than a design choice.

2. **`AdminGatewayController::index` returns every organization's gateways**
   with no organization filter
   (`backend/app/Http/Controllers/Api/Admin/AdminGatewayController.php:19-25`),
   also outside `EnsureOrganizationAccess`
   (`backend/routes/api.php:103`). Its only guard is
   `authorize('viewAny', Gateway::class)` → `hasPermission('gateways.view')`,
   which per §8.1 returns `true` for **any** user holding zero explicit grants —
   the default state for a newly created agent. A default-state agent in one
   tenant can therefore enumerate another tenant's gateway hostnames, realms,
   proxies, and usernames, since `GatewayResource` serializes them.

   (An earlier revision argued this was compounded by `gateways.view` having no
   `permissions` row. That was wrong — `PbxMediaPolicy` contributes the slug, see
   §8.4. The exposure does not depend on it: default-open grants the check
   regardless of whether the row exists, and the missing organization filter is
   unconditional.)

**Recommendation.** Attach a `platform-admin` middleware to the whole `admin/*`
route block rather than relying on per-controller gates, and add
`Gate::authorize('platform-admin')` to `SipProfileController`'s five methods.

Also worth closing while there: `sip-profiles`, `sip-profiles/create`,
`sip-profiles/:id/edit`, `system-logs`, and `sip-status` are marked
`superadminOnly` in the nav but are **not** wrapped in `SuperadminOnlyRoute` in
the router (`frontend/src/app.tsx:195,197,199-201`). The backend gates the log
and status endpoints, so most of that is a broken-page issue — except SIP
profiles, which is finding 1 above.

---

## 9. Cross-cutting UI issues

### 9.1 Organization settings is a raw JSON textarea

`OrganizationSettingsPage.tsx` is one `Textarea` validated only as
"parses as a JSON object" (`OrganizationSettingsPage.tsx:28-45,130-146`). Yet
this blob holds real call behavior — `outbound_caller_id_privacy` among others.

There is no schema, no field list, no defaults display, no per-key validation,
and no discoverability: an admin cannot learn that a key exists without reading
PHP. A typo silently changes nothing; a wrong value silently changes call
handling.

**Recommendation.** Highest-leverage single fix outside recordings UI. Publish a
settings schema from the backend (key, type, label, help, default, allowed
values) and render a typed, grouped form — Calling, Recording, WebRTC, Privacy,
Limits — with the JSON view demoted to an "Advanced" tab for debugging.

### 9.2 No client self-service surface

`frontend/src/pages` contains only `admin/` and `auth/`. Every telephony
self-service action — follow-me, DND, voicemail settings, caller-ID choice —
requires either an admin editing the extension or a star code the user has to
know. For a SaaS PBX, the absence of a "my line" page pushes routine end-user
requests onto the client admin, which is exactly the cost clients complain
about.

**Recommendation.** Add a scoped `/me` area reusing the existing
`ExtensionFeatureService` / office-feature endpoints: my devices, my voicemail,
my forwarding and DND, my default outbound number (within the admin's
allow-list), my recent calls.

### 9.3 Platform vocabulary leaks into the client admin nav

The nav is one flat 20+ item list mixing tenant and platform concerns, gated by
`superadminOnly` / `adminOnly` flags
(`frontend/src/layouts/SuperadminLayout.tsx:69-116`). Client admins share the
information architecture — and the vocabulary — of a platform operator.

**Recommendation.** Split into two shells with distinct groupings: a tenant
console (People, Lines, Numbers, Call routing, Recordings, Reports) and a
platform console (Organizations, SIP profiles, FreeSWITCH modules,
Capabilities, System logs).

### 9.4 More raw internals shown as primary UI

Beyond the JSON settings blob (§9.1) and the UUID device column (§2.5):

- **Music-on-hold is a free-text box placeholdered with a FreeSWITCH URI** —
  `placeholder="local_stream://default"` (`QueueFormPage.tsx:264-274`). The
  product has a whole media library (`SystemMediaPage`); this field ignores it
  and asks the admin to know `mod_local_stream` syntax. Make it a select over
  uploaded media with an advanced escape hatch.
- **The SIP profile editor's primary surface is a raw sofia key/value grid**,
  self-described as "Edit raw FreeSWITCH parameters"
  (`SipProfileFormPage.tsx:706-780`), with a free-text Name column validated
  against nothing and the empty state "No settings added yet." The curated
  WebRTC card on the same page is the model to follow for the ~15 params that
  matter.
- **Bare UUIDs as first-class detail values** — "Interaction ID", `call_uuid`,
  plus `attempt.id` and `log.id` on every row
  (`InteractionDetailPage.tsx:186,228,403,407`). Collapse into one "Copy
  diagnostic IDs" action.
- **Machine enum names de-underscored as labels** — `strategy.replace('_', ' ')`
  yields "ring all", "round robin", "least recent"
  (`TeamFormPage.tsx:191-194`, `QueueFormPage.tsx:170-174`), with no description
  of what any of them do. Note the team version uses non-global `replace`, so a
  three-word strategy renders half-underscored. Use explicit
  `{value, label, description}` lists, as `recordingPolicyOptions` already does
  (`OrganizationFormPage.tsx:41-47`).

### 9.5 The SIP credential handoff can email the word "Hidden" as a password

`ExtensionDetailPage.tsx:169` renders `{sipConfig.sip_password || 'Hidden'}`, and
the copy-to-clipboard builder repeats the same fallback —
`` `Password: ${sipConfig.sip_password || 'Hidden'}` `` (`:190`) — then fires
`toast.success('SIP credentials copied to clipboard')` unconditionally. An admin
onboarding a user's softphone can paste `Password: Hidden` into an email and
spend the afternoon debugging a registration failure that never had a chance.

**Recommendation.** When the password is not returned, replace the copy button
with "Generate & reveal password". Never let a placeholder reach the clipboard.

### 9.6 Smaller items worth fixing while nearby

- **Mislabeled tile.** "Total Capacity" on the extensions page shows
  `extensions.length` (`ExtensionsPage.tsx:107-112`) — that is the count, not
  the capacity. The organization carries `max_extensions`
  (`OrganizationResource.php:34`); show `12 of 25 licensed`.
- **Grammar in empty states.** "Select a organization to view extensions."
  appears in several pages (`ExtensionsPage.tsx:80`,
  `ExtensionDetailPage.tsx:44`).
- **No consequence copy on recording controls.** The recording policy select
  has no note about consent law, storage cost, or retention interaction —
  the one setting in the product most likely to carry legal weight.

---

## Suggested sequencing

Ordered by value per unit of work and by what unblocks what.

**Wave 0 — close before anything else (not UX work, but blocking)**

1. Authorize `SipProfileController` and scope `AdminGatewayController::index`;
   put `platform-admin` middleware on the whole `admin/*` block (§8.7)
2. Make `hasPermission()` deny-by-default for non-admin roles with role presets
   (§8.1); add authorization to `CallSessionController`, `TeamController`,
   `AgentController` (§8.2)
3. Guard the five unwrapped superadmin routes in the router (§8.7)
4. Derive `caller_id_name` server-side and reject client-supplied display names
   on `calls/originate` (§4.4) — exploitable today, and not dependent on the web
   phone existing
5. Scope the live-call endpoints: filter `calls/status` by organization and
   resolve every inbound channel UUID through an organization-scoped
   `CallSession` before dispatching hangup / transfer / hold / record (§5.3)

**Wave 1 — make existing behavior visible (mostly frontend, with the small
backend additions noted per item)**

6. Recordings page + inline playback in call history and interaction detail
   (§1.7). *Backend: join recording presence into the CDR/interaction resource so
   a row knows whether audio exists.*
7. Repoint Call History at CDRs and bind the existing filters, paginator, and
   CSV export; fix the KPI tiles that cap at 15 (§5.1, §5.2). *Backend: none for
   the table and filters; the counters need `cdrs/analytics/summary`, which
   already exists.*
8. Render `provisioning_health.next_actions` as a setup checklist using the card
   pattern that already exists on the dashboard (§7.1). *Backend: add
   `provisioning_health` to the frontend zod schema only — the payload already
   ships.*
9. Effective-recording-policy display on all three forms (§1.2); surface
   `recording_retention_days` (§1.9); remove org-scope `Inherit` (§1.3).
   *Backend: a new effective-policy GET endpoint wrapping the existing resolver,
   plus `recording_retention_days` on `OrganizationResource` and a dry-run count
   for the preview.*
10. Reports section over the shipped analytics, supervisor-report, and usage
    endpoints (§5.6). *Backend: none — all endpoints exist and are authorized.*
11. Cheap correctness fixes: extension number instead of raw UUID (§2.5), the
    capacity tile (§9.6), the "Hidden" password clipboard bug (§9.5), and
    `onError` on the nine mutations missing it (§7.4)

**Wave 2 — fix the model and the missing call path**

12. Outbound route compilation with DID-based caller ID, privacy mode, and
    explicit emergency-pattern rejection (§4.2) — nothing else in caller ID
    matters until this exists, and the emergency rejection must land in the same
    change, not after it
13. Recording scopes for queue/team/agent (§1.4) and the enforcement lock (§1.5)
14. Collapse the duplicate extension↔device link (§2.3); person-first
    user↔extension UI (§2.4)
15. Debounce SIP profile restarts and stop swallowing ESL failures (§3.3)
16. Trunk pages, reusable trunk selection on DIDs, org-visible registration
    status, and a test-call button (§7.3)
17. Active Calls page with hangup/transfer/hold/record (§5.3) — requires Wave 0
    item 5; do not build this UI against the unscoped endpoints

**Wave 3 — the requested control surfaces**

18. Per-organization `webrtc_enabled` tri-state + superadmin rollout screen
    (§3.2) and WebRTC readiness checks (§3.4)
19. Endpoint-binding UI: devices & apps per extension (§2.6)
20. Typed organization settings from a published schema (§9.1)
21. Web phone with an in-dialer caller-ID picker (§4.4) and desk-phone prefix
    codes (§4.3) — the server-side name validation this depends on is Wave 0
    item 4
22. Troubleshooting panel: hangup cause, ring/talk split, registration state at
    call time, per-attempt reachability verdict (§5.4)

**Wave 4 — hierarchy, self-service, and operations**

23. Scoped visibility (`own`/`team`/`org`) across recordings, CDRs, and call
    sessions, with a real supervisor role and `team_supervisors` pivot
    (§1.8, §8.2, §8.3)
24. Audit sensitive reads and permission changes (§8.6); rebuild the permission
    editor around presets (§8.5)
25. `/me` self-service area, voicemail message store and inbox, agent state
    control (§6.2, §6.3, §6.4)
26. Tenant vs platform console split (§9.3); CDR ingestion health check and
    scheduled anomaly detection (§5.5)

---

## Method and caveats

Repo-based review of the working tree at `claude/voip-service-ux-review-76impf`.
Every finding cites file evidence and was read in source; no runtime or
FreeSWITCH behavior was exercised, so runtime-only claims (for example the exact
number of profile restarts a save produces in practice) are inferences from the
code path rather than observations.

Line numbers are accurate as of this commit and will drift.

**Corrections made during review.** Six findings in the first revision were wrong
or materially imprecise, and they failed in two clusters:

*Read a component without reading its caller:*

- §1.4 claimed extension recording policy is ignored on queue-answered calls. The
  resolver does gate on `answered_target_type`, but `EventProcessor` sets that to
  `extension` from the winning endpoint binding, so precedence does apply. The
  real gap is narrower: no policy exists on Queue/Team/Agent.
- §8.4 listed `gateways.*` and `calls.control` as ungrantable. They are
  contributed by enabled modules via `ModuleRegistry::collectPermissions()`.
- §6.2 called star-code forwarding "dialplan-only with no persisted state." The
  Lua writes the extension row and the endpoint binding directly. The gap is
  presentational only.
- §2.2 said assigning a desk phone drops the person link outright. A device-side
  path exists — but `syncOwnedDevice()` destroys it on any later extension save,
  which is a worse bug than the one originally described.

*Recommended a fix at the wrong layer:*

- §5.3 called an Active Calls page "frontend-only work." The live-call endpoints
  are unscoped across tenants, so that advice would have shipped a cross-tenant
  exposure. Now a Wave 0 backend item.
- §3.2 put the per-tenant WebRTC gate in `WebRtcConfigService`, which only shapes
  a read-only metadata response. Enforcement has to happen in directory auth or
  at a separate profile boundary.

One recommendation was withdrawn as unsafe rather than merely wrong: §4.2
originally proposed an emergency-calling caller-ID override, which would have
manufactured the appearance of E911 support on a platform that documents having
no PSAP or location path at all.

Treat the remaining single-source claims with proportionate caution — especially
any that assert something is *absent*, which is the hardest thing to prove from a
partial read, and any recommendation that names a specific enforcement point.
The reliable findings here are the ones where a code path was traced from entry
point to effect.
