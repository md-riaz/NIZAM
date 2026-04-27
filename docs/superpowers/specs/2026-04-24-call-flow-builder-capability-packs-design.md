# Call Flow Builder Capability Packs Design

## Goal

Expand NIZAM's inbound call flow builder so organization admins can create more real-world receptionist, routing, and after-hours flows using product-friendly terminology, while preserving a controlled mapping to FreeSWITCH-capable runtime behavior.

## Why this change

Current builder supports only a small routing set in product and runtime layers:
- frontend palette currently exposes `start`, `ivr`, `ring_group`, `queue`, `transfer`, `terminal`
- backend compiler/registry currently centers on `start`, `schedule_check`, `menu`, `ring_team`, `voicemail`, `hangup`
- inspector coverage is narrow, with dedicated editors only for IVR and ring-group/team style nodes

That leaves common PBX journeys either impossible or too implicit:
- explicit business-hours / holiday branching
- caller-based routing
- number-based routing
- ring vs hunt semantics presented in product terms
- recording, play-message, failover, and voicemail-first routing as first-class options

User goal is not to turn builder into raw dialplan editor. Product must stay approachable, with advanced PBX knobs available only where safe and useful.

## Non-goals

Out of scope for this slice:
- raw FreeSWITCH XML or Lua editing in UI
- arbitrary FreeSWITCH app string input
- unrestricted channel-variable scripting by admins
- outbound orchestration redesign
- replacing existing flows with incompatible schema in one cutover
- exposing every FreeSWITCH primitive in v1

## Product principles

1. **Product language first**
   - UI uses labels like `Business Hours`, `Caller Match`, `Play Message`, `End Call`
   - UI avoids raw PBX jargon unless placed under explicit advanced sections

2. **Capability packs, not universal raw builder**
   - Add a curated set of high-value node families
   - Each family maps to known runtime/compiler contracts

3. **Basic first, advanced second**
   - Default inspector shows minimal fields needed for success
   - Collapsed `Advanced PBX Options` section exposes safe extra controls

4. **Compile-time safety**
   - Unsupported combinations fail validation before publish
   - Runtime should not discover malformed graph for first time during live call

5. **Incremental growth**
   - Node family additions should be independently testable
   - New node types should plug into existing registry/compiler/validator structure

6. **Transfer-only routing core**
   - Flow entry and routing-only nodes must transfer without answering
   - Only media-handling destinations such as menu, play-message, or voicemail should answer when runtime requires it

## Recommended approach

Use **capability packs**.

Expand builder through a small number of strongly-defined node families instead of a generic action DSL or raw expert-mode dialplan surface.

Why this approach:
- matches current frontend `nodeRegistry` + `FlowInspector` architecture
- matches backend `NodeSpecRegistry` + per-node validator/compiler pattern
- supports better UX copy and grouped palette organization
- keeps publish validation and IR compilation deterministic
- allows adding power without forcing every user to understand FreeSWITCH internals

## Current architecture baseline

### Frontend

Current relevant files:
- `frontend/src/pages/admin/FlowEditorPage.tsx`
- `frontend/src/components/flow-builder/FlowCanvas.tsx`
- `frontend/src/components/flow-builder/FlowInspector.tsx`
- `frontend/src/components/flow-builder/FlowNodePalette.tsx`
- `frontend/src/components/flow-builder/nodeRegistry.ts`
- `frontend/src/components/flow-builder/nodes/IvrNodeEditor.tsx`
- `frontend/src/components/flow-builder/nodes/RingGroupNodeEditor.tsx`

Current palette grouping in `FlowNodePalette.tsx` is simple:
- Entry
- Routing
- End

Current builder definitions in `nodeRegistry.ts` expose only a few node types and each definition owns:
- product label/title/description
- icon/accent color
- default node name
- `createConfig()` defaults

Current inspector switches on node type and renders focused editors for only some node types.

### Backend

Current relevant files:
- `backend/app/Domain/Flow/Compile/NodeSpecRegistry.php`
- `backend/app/Services/Flow/Compile/FlowToIrCompiler.php`
- `backend/app/Services/Flow/FlowIntegrityValidator.php`
- `backend/app/Services/Flow/FlowDefinitionMapper.php`
- `backend/app/Services/Flow/FlowApplicationService.php`
- `backend/app/Services/Flow/FlowPublishService.php`
- `backend/app/Services/Flow/Compile/*NodeCompiler.php`
- `backend/app/Services/Flow/Validation/*NodeValidator.php`
- `backend/app/Models/Flow.php`
- `backend/app/Models/FlowVersion.php`
- `backend/app/Models/FlowNode.php`
- `backend/app/Models/FlowEdge.php`
- `backend/app/Models/FlowCompiledArtifact.php`

Important existing behavior:
- `NodeSpecRegistry` already defines type aliases and allowed transitions
- `FlowToIrCompiler` already dispatches by node type to per-node compilers
- `FlowIntegrityValidator` already validates core graph structure and reachability
- publish path already compiles flow versions into artifacts

This means new capability packs should extend registry/compiler/validator/editor layers rather than invent a parallel flow system.

## Proposed v1 capability packs

### 1. Conditions pack

#### Business Hours
Product label: `Business Hours`

Purpose:
- branch call by organization schedule state

Default behavior:
- use organization primary schedule and holiday calendar

Optional override behavior:
- select a specific schedule / holiday calendar when node needs local exception logic

Branches:
- `open`
- `closed`
- `holiday`

Notes:
- current backend already has `schedule_check` / alias `business_hours`
- current spec includes `open`, `closed`, `break`; this slice should normalize product/runtime path so holiday is explicit first-class branch in product
- if `break` remains needed internally, treat it as an advanced/secondary state, not primary product branch

#### Caller Match
Product label: `Caller Match`

Purpose:
- route by caller category or number match

Supported v1 match modes:
- anonymous caller
- exact number
- prefix match
- VIP list / allowed list
- fallback `no_match`

Branches:
- `match`
- `no_match`
- optional named branches only if compiler/runtime supports them without fragile edge semantics

#### Number Match
Product label: `Number Match`

Purpose:
- route by called DID / number group / inbound entry number

Supported v1 match modes:
- exact DID
- number group
- fallback branch

Branches:
- `match`
- `no_match`

### 2. Call handling pack

#### Ring Team
Product label: `Ring Team`

Purpose:
- user-facing name for current ring-team / ring-group style behavior

Behavior:
- ring configured team or destination set
- preserve current team integration paths

Branches:
- `answered`
- `timeout`
- `failed`

#### Hunt Group
Product label: `Hunt Group`

Purpose:
- explicit hunt-style routing instead of generic ring-only semantics

Supported v1 strategies:
- simultaneous
- sequential
- longest idle

Branches:
- `answered`
- `timeout`
- `failed`

#### Queue
Product label: `Queue`

Purpose:
- send caller to queue with timeout/failover behavior

Branches:
- `answered`
- `timeout`
- `failed`

#### Voicemail
Product label: `Voicemail`

Purpose:
- deposit message to mailbox or extension voicemail target

Basic fields:
- mailbox target
- greeting selector

Advanced safe fields:
- transcription toggle if supported by platform path
- email notification toggle if supported by platform path

Terminal by default, unless runtime intentionally supports post-voicemail continuation

#### Record Call
Product label: `Record Call`

Purpose:
- enable recording policy for next path segment or branch scope

Supported v1 modes:
- off
- inbound leg
- outbound leg
- both

Behavior notes:
- should be modeled as side-effect node or pre-target action node only if compiler/runtime can represent it cleanly
- if IR model cannot support standalone side-effect node cleanly, this capability may be better as advanced option on Ring Team / Hunt Group / Queue / Transfer nodes in v1

#### Play Message
Product label: `Play Message`

Purpose:
- play audio or TTS before continuing or ending

Branches:
- `next`
- optional `failed` only if media fetch/runtime failure can be represented clearly

#### Transfer
Product label: `Transfer`

Purpose:
- send call to internal destination or transfer target

Branches:
- `success`
- `failed`

#### End Call
Product label: `End Call`

Purpose:
- explicit terminal node using product wording instead of technical `terminal` or `hangup`

Fields:
- reason label / hangup cause mapping

## Product naming model

Frontend labels should be business-facing even when backend node type remains technical.

Recommended naming strategy:
- UI label may differ from stored backend type when helpful
- backend type names should stay stable and compiler-friendly
- aliases in `NodeSpecRegistry` should absorb historical names where needed

Examples:
- UI `Open Hours / Closed / Holiday` -> backend `schedule_check` or successor type with alias support
- UI `Menu` -> backend `menu`
- UI `Ring Team` -> backend `ring_team`
- UI `End Call` -> backend `hangup`

## Advanced PBX options model

Relevant nodes get collapsed `Advanced PBX Options` section in inspector.

Allowed v1 advanced fields:
- timeout
- retry count where runtime supports it safely
- failover target / fail branch semantics
- caller ID override where supported by call path
- ringback / early-media toggle where safe
- recording flags on target nodes
- DTMF timeout / invalid-digit handling on menu-style nodes

Disallowed v1 advanced fields:
- raw application string
- raw dialplan snippets
- arbitrary channel variable mutation with no validation
- arbitrary Lua fragment injection

Guideline:
- every advanced field must map to typed schema, validator, and compiler output
- nothing in advanced section may bypass publish validation

## Frontend design

### Palette evolution

Replace current minimal grouping with capability-based grouping:
- Entry
  - Start
- Conditions
  - Business Hours
  - Caller Match
  - Number Match
- Routing
  - Menu
  - Ring Team
  - Hunt Group
  - Queue
  - Transfer
- Actions
  - Play Message
  - Record Call
  - Voicemail
- End
  - End Call

Each item still uses drag-and-drop and click-to-add.

### Node registry evolution

`frontend/src/components/flow-builder/nodeRegistry.ts` should become richer source of truth for builder metadata:
- grouped category
- product label/title/description
- icon + accent styling
- default config factory
- basic field summary metadata where useful
- advanced support flag
- optional backend type alias if UI/stored type naming diverges

### Inspector evolution

`FlowInspector.tsx` should stop accumulating all node forms inline.

Recommended structure:
- one focused editor component per node family under `frontend/src/components/flow-builder/nodes/`
- shared inspector shell handles node name, product badge, basic vs advanced section framing
- node editor handles only typed config form for that node family

Likely new editor files:
- `BusinessHoursNodeEditor.tsx`
- `CallerMatchNodeEditor.tsx`
- `NumberMatchNodeEditor.tsx`
- `HuntGroupNodeEditor.tsx`
- `QueueNodeEditor.tsx` (extract from inline code)
- `VoicemailNodeEditor.tsx`
- `PlayMessageNodeEditor.tsx`
- `TransferNodeEditor.tsx` (extract or refine)
- `EndCallNodeEditor.tsx`

### UX behavior

Builder should surface errors earlier:
- missing required branch target highlights node or save/publish validation panel
- unsupported advanced toggle shows disabled explanation, not silent no-op
- publish button blocks invalid graph with node-specific messaging

## Backend design

### Node spec registry

Extend `backend/app/Domain/Flow/Compile/NodeSpecRegistry.php` with new node specs and aliases.

For each new node type, define:
- canonical type
- IR type
- allowed transitions
- terminal status
- validator class
- aliases for legacy/product compatibility where needed

### Validators

Add or extend validator classes under `backend/app/Services/Flow/Validation/` for each node family.

Responsibilities:
- required config fields present
- legal transition names only
- advanced fields constrained to safe values
- incompatible combinations rejected before compile/publish

### Compilers

Add per-node compilers under `backend/app/Services/Flow/Compile/`.

Responsibilities:
- translate typed node config + outgoing edges into IR instructions
- keep runtime branch names deterministic
- avoid hidden fallback behavior not visible in builder

### IR / runtime

Compiler output should stay product-safe but runtime-capable.

Preferred rule:
- condition nodes emit explicit branch instructions
- target/action nodes emit one well-defined action with optional success/failure continuation
- terminal nodes compile to explicit hangup/end instruction

If a candidate node cannot be expressed cleanly in current IR without hidden side effects, do not expose it as independent node in first slice. Attach it as advanced option to supported target node instead.

### Integrity validation

`FlowIntegrityValidator` should evolve beyond graph reachability:
- verify condition nodes have required named branches when mandatory
- verify terminal expectations where node contract requires no outgoing edges
- verify single-entry rules if certain action nodes must not be orphaned
- report messages in node-aware language where possible

## Data flow

1. Admin adds product node in builder palette
2. frontend writes typed node config into flow version definition
3. API persists flow nodes/edges/version graph
4. publish path runs integrity validation + per-node validation
5. compiler maps node types into IR instructions
6. artifact service stores compiled artifact
7. runtime executes compiled artifact on inbound call

Key rule:
- no UI-only node semantics
- every node visible in builder must have persisted, validated, compiled, and executable meaning

## Migration and compatibility

Do not break existing saved flows.

Compatibility strategy:
- preserve existing stored types and aliases
- add aliases for renamed product labels where possible
- update frontend to render older node types using current product label mappings
- where a richer product node supersedes an older generic node, retain legacy support until explicit migration exists

Examples:
- existing `business_hours` alias support should remain valid
- existing `ring_team` should continue to load even if product copy says `Ring Team`
- legacy `terminal` should map to `End Call` presentation

## Recommended v1 implementation order

1. Strengthen builder metadata and inspector structure
2. Normalize existing naming mismatches between frontend and backend node types
3. Expand condition pack
   - Business Hours
   - Caller Match
   - Number Match
4. Expand call-handling pack
   - Ring Team / Hunt Group semantics
   - Queue
   - Voicemail
   - Play Message
   - End Call
5. Add advanced PBX options only on nodes with clear typed runtime mapping
6. Add browser verification flows for org-admin creation/edit/publish journeys

## Testing strategy

### Backend

Add targeted tests for:
- node validator behavior per family
- integrity validation for missing branches / unreachable paths / invalid config
- compiler output snapshots per new node family
- publish path failures for invalid graphs
- compatibility loading for legacy node types and aliases

Relevant existing test anchors:
- `backend/tests/Feature/Api/FlowApiTest.php`
- `backend/tests/Feature/FlowToIrCompilerTest.php`
- `backend/tests/Feature/FlowCompilerSnapshotTest.php`
- `backend/tests/Feature/FlowPublishServiceTest.php`

### Frontend

Add focused tests for:
- palette grouping and product labels
- node creation default config
- inspector basic vs advanced section behavior
- save/load roundtrip for new node configs
- legacy node rendering labels

### Browser verification

Required manual/browser checks:
- create `Business Hours -> Ring Team -> Voicemail` flow
- create `Caller Match -> VIP branch / normal branch` flow
- create `Number Match` flow for multiple inbound numbers
- publish, reopen, edit, and republish flows
- verify invalid flow cannot publish and surfaces clear editor errors

## Success criteria

Feature is successful when:
- org admin can build common front-door inbound flows without FreeSWITCH expertise
- builder covers business-hours, caller-match, number-match, queue, voicemail, play-message, and ring/hunt flows in product terms
- advanced PBX section adds control without dominating default UX
- publish path catches invalid graphs before activation
- legacy saved flows still open and render correctly

## Open decisions intentionally resolved in this spec

To avoid ambiguity, this spec fixes these decisions now:
- v1 uses capability packs, not raw expert-mode dialplan editing
- v1 prioritizes condition nodes and call-handling nodes, not external webhook/API action surface
- advanced PBX options are typed and guarded, not arbitrary free-form commands
- `holiday` is first-class product branch for business-hours style routing
- if a feature cannot map cleanly into current IR/runtime safely, it should ship as node option later or stay hidden for this slice

## Risks and mitigations

### Risk: frontend/backed type mismatch grows worse
Mitigation:
- centralize node metadata and aliases
- add compatibility tests around existing persisted node types

### Risk: builder adds nodes runtime cannot compile safely
Mitigation:
- require validator + compiler + publish tests before exposing node in palette

### Risk: advanced options overwhelm users
Mitigation:
- basic fields only by default
- advanced section collapsed and opt-in

### Risk: graph validation remains too structural
Mitigation:
- extend integrity validation to understand branch contracts and required target semantics
