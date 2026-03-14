Below is a concrete migration plan from current NIZAM to the compiled, local-runtime, modular dialplan + Lua architecture we discussed.

I checked the repo state. The short version is:
- NIZAM already has a good API/control-plane base with routes for flows, schedules, holiday calendars, teams, call routing policies, and tenants 
- It already has a graph-based flow model with versions, nodes, edges, publish flow logic, and integrity validation 
- It already has schedule/holiday/team domain models that are close to what you need conceptually 
- But the current runtime is still largely application-interpreted: flow runtime starts from xml_curl, builds an execution definition, runs FlowExecutionService, uses a runtime ScheduleEngine, and node handlers issue media commands while waiting on events 
- The existing DialplanCompiler is still mostly in the FusionPBX-style XML compiler world for extensions, DIDs, ring groups, time conditions, and policies, and the current flow destination path just answers and parks instead of compiling a local runtime program 

So the repo is not starting from zero at all. The right move is not a rewrite-from-scratch tantrum. It is a controlled architectural pivot:

from
API + interpreted runtime + event-driven flow execution

to
API + compiler + compiled modular dialplan + small Lua helpers + local FreeSWITCH execution

## 1. Target architecture
This is the state you want NIZAM to reach:

**Control plane**
Laravel app manages:
- tenants
- numbers / DIDs
- schedules
- holiday calendars
- teams
- flows
- publish/version lifecycle
- compile/deploy lifecycle
- observability

**Compile plane**
When config changes, NIZAM compiles:
- tenant dialplan fragments
- flow node extensions
- schedule condition fragments
- optional Lua parameters / helper invocations
- manifest / version metadata

**Runtime plane**
FreeSWITCH executes locally:
- XML dialplan as orchestrator
- small reusable Lua scripts only where dialplan is weak
- no DB/API evaluator in call hot path
- events emitted back to NIZAM only for trace/analytics

That means:
- NIZAM is the compiler and control plane.
- FreeSWITCH is the local deterministic runtime.

## 2. Current-to-target gap map
Here is the repo’s biggest mismatch with the target design.

**What is already aligned**
The repo already has:
- flow graph storage
- publishing/versioning
- schedule entities
- holiday calendars
- teams
- call sessions
- trace/event mindset

That is good. Keep it.

**What is misaligned**
The repo currently still assumes:
- runtime flow interpretation in PHP
- runtime schedule evaluation in PHP
- runtime media orchestration via app services
- park + app runtime starter for flows
- node handlers as the live execution mechanism

That is the part to replace gradually.

In plainer words:
your data model is ahead of your runtime architecture.

## 3. Migration strategy
Do this as a staged migration, not a “delete everything and pray” event.

Use this rule for every phase:
*Preserve the API and graph model where possible. Replace the runtime and compile path under them.*

## 4. Phase 0 — Freeze and document the current runtime
Before changing architecture, lock the current behavior.

**Tasks**
- Create an architecture snapshot doc:
  - how FreeswitchXmlController currently creates sessions and starts runtime 
  - how FlowRuntimeStarter currently invokes FlowExecutionService 
  - how FlowExecutionService currently loops node-by-node and persists waiting/executing/completed states 
  - how ScheduleEngine currently evaluates schedules at runtime 
  - how MediaControlService currently commands FreeSWITCH from app code 
- Tag the current repo:
  - `v0-interpreted-runtime`
- Add a feature flag concept:
  - `flow_runtime_mode = interpreted | compiled`
  - this lets you migrate flow-by-flow or tenant-by-tenant

**Output**
A stable fallback path while you build the new compiler path.

## 5. Phase 1 — Clean the model boundariesRight now the domain concepts are mostly right, but their responsibilities need to be tightened.

**Keep as core domain objects**
- Flow, FlowVersion, nodes, edges 
- Schedule, ScheduleRule, ScheduleBreak, ScheduleException 
- HolidayCalendar, Holiday 
- Team, TeamMember 
- CallSession and trace/events for observability 

**Stop treating these as runtime engines**
These should become compile-time inputs, not hot-path evaluators:
- ScheduleEngine
- FlowExecutionService
- live node handlers for core call path

**New conceptual split**
Create explicit folders or namespaces for:
- Domain/Flow — graph and publish validation
- Domain/Schedule — canonical schedule definitions
- Compile/* — XML/Lua generation
- RuntimeManifest/* — deployed artifacts metadata
- Observability/* — events, traces, audits

**Output**
A cleaner mental model:
graph/config data stays; interpreted runtime logic becomes transitional.

## 6. Phase 2 — Define the compiler target
Do not compile directly from graph to XML strings inside one giant class. That road leads to cursed spaghetti.

Introduce an intermediate representation.

**Add a new compile IR**
Example conceptual IR:
- MatchDestinationNumber
- SetVar
- CheckSchedule
- PlayPrompt
- CollectDigits
- BranchOnVar
- BridgeTeam
- Voicemail
- Hangup
- TransferExtension

This IR is not the runtime. It is the compiler’s structured output before XML/Lua generation.

**Why this matters**
It lets you:
- validate compilation
- unit test compiler behavior
- later swap XML strategies without rewriting graph translation
- keep node compiler plugins small

**New services**
Create something like:
- FlowToIrCompiler
- ScheduleToIrCompiler
- IrToDialplanGenerator
- IrToLuaParamGenerator or LuaHelperCallGenerator

**Output**
A real compile pipeline instead of “graph + vibes + string concatenation.”

## 7. Phase 3 — Rework schedules into compiled local policy
This is one of the most important pivots.

Right now schedule logic is evaluated live in PHP via ScheduleEngine queries. That is exactly what you said you do not want at runtime.

**Target behavior**
Schedules should compile into local FreeSWITCH-executable logic.

**Rule precedence**
The canonical precedence should be:
- holiday
- exception
- break
- open
- closed

This rule lives in the compiler, not the call path.

**Recommended compilation strategy**

*For schedules*
Compile schedules into dialplan-native time conditions first, not Lua, whenever FreeSWITCH supports it.
Use:
- wday
- mday
- mon
- time-of-day

*For holidays/exceptions*
Generate specific date-based dialplan branches before weekly hours.

*For breaks*
Generate separate matched ranges that set `schedule_state=break`.

*For final state signaling*
Each compiled schedule node should set a channel variable like:
`nizam_schedule_state`
and then transfer or continue to the next modular extension.

**Important design**
Compile schedules separately from flows.
Example:
- schedule change recompiles schedule fragment
- flow change recompiles flow fragments
- unaffected artifacts remain untouched

**New compiler components**
- ScheduleCompiler
- HolidayCalendarCompiler
- ScheduleFragmentRegistry

**Output**
No ScheduleEngine in the hot path anymore.
FreeSWITCH evaluates compiled schedule conditions locally.

## 8. Phase 4 — Move from interpreted flow runtime to modular dialplan runtime
This is the big one.

Right now a flow DID destination in DialplanCompiler just answers and parks, then PHP starts the runtime starter from xml_curl. That must change.

**Target behavior**
A flow destination should compile to a modular dialplan entrypoint.
Instead of:
- answer
- park
- app runtime

it should do:
- transfer into compiled flow context/extension

**Recommended internal representation**
Each major node becomes a modular extension.
Example:
- `tenant_42_flow_100_start`
- `tenant_42_flow_100_schedule_2`
- `tenant_42_flow_100_menu_3`
- `tenant_42_flow_100_ring_team_4`
- `tenant_42_flow_100_voicemail_5`

**Rule of thumb**
major nodes => separate extensions**Don’t delete them on day one.**

**Migration plan**

*Stage A*
Keep interpreted runtime behind feature flag as fallback.

*Stage B*
Compile only these node types first:
- start
- schedule_check
- menu
- ring_team
- voicemail
- hangup

These are already the supported node types in factory form, which is convenient.

*Stage C*
For published flows with only supported compiled nodes:
use compiled runtime 
For unsupported flows:
fallback to interpreted mode temporarily

*Stage D*
After parity:
- deprecate FlowExecutionService
- keep trace/session/event infrastructure
- remove hot-path dependence on handler execution

**Output**
A safe migration without lighting production on fire.

## 13. Phase 9 — Redesign flow validation for compiled runtime
FlowIntegrityValidator currently checks:
- not empty
- exactly one start node
- valid edge references
- reachability 

That is good but not enough for compilation.

**Add compile-time validation**
For each node type validate config:
- schedule node must reference an active schedule
- menu node must have valid digit branches
- ring team node must reference an active team
- voicemail node must reference a valid mailbox/extension
- end nodes must not require outgoing transitions

**Add control-flow validation**
- no ambiguous default branches
- every non-terminal node has valid outgoing branches
- every transition result used by node type is defined
- no unsupported loops unless explicitly allowed
- no orphan extension generation

**Add schedule-policy validation**
- exception ranges do not conflict in invalid ways
- timezone required
- holiday calendar linkage valid

**Output**
Published flows are compile-safe, not merely graph-valid.

## 14. Phase 10 — Formalize the workflow graph model
The repo already stores graph definitions and versioned nodes/edges well enough to start.
Now tighten the node contract so the compiler stays sane.

**Recommended node model**
Each node type must define:
- compile strategy
- allowed outgoing transitions
- config schema
- whether it is terminal
- whether it requires Lua helper
- observability events emitted

**Add node spec registry**
Something like:
- NodeSpecRegistry
- ScheduleCheckSpec
- MenuSpec
- RingTeamSpec

The compiler and validator should both use these same specs.
That prevents “validator says okay but compiler chokes” nonsense.

**Output**
A single source of truth for node behavior.

## 15. Phase 11 — Keep observability, but move it off the decision path
One thing the current repo does well is think in terms of call sessions, events, and traces:
- CallSession has variables/current node/state/lock version 
- controller traces dialplan lookup and call start 
- media services trace commands 

Keep that. It is good.

**But change the source of truth**
Instead of “PHP runtime executed node X”, you want:
- FreeSWITCH runtime hit node/extension X
- Lua helper emitted event Y
- bridge/playback/voicemail actions generated trace events

**Recommended observability model**

*Call bootstrap*
At xml_curl:
- session bootstrap
- call start event

*Dialplan event emission*
Each compiled node should emit:
- `nizam.node.enter`
- `nizam.node.exit`
- `nizam.branch`
- `nizam.media.action`

*Lua helper event emission*
Lua emits custom FreeSWITCH events or logs in structured format.

*App side*
ESL listener or log ingestor maps these into:
- CallEventLog
- CallTraceEvent

**Output**
Local runtime, central observability.

## 16. Phase 12 — API changes to support compile-time lifecycle
Your API is already broad and useful. Now it needs compile lifecycle semantics.

**Add explicit compile status to flows**
In FlowResource, expose:
- latest_version
- active_version
- compile_status
- compile_errors
- compiled_at
- runtime_mode

**Add schedule compile status**
In ScheduleResource, expose:
- referenced_by_flow_count
- compile_status
- compiled_fragment_version
- active_manifest_version

**Add publish behavior**
Current publish just marks version published.
New publish should mean:
- validate
- compile
- stage artifact
- activate artifact
- mark version active

If compile fails, publish fails.

**Output**
The API becomes truthful about deployment state, not just DB state.

## 17. Phase 13 — UI implications, even if UI comes later
Even if you pause the UI now, design the backend so the future React client is easy.

**Top-level product model for UI**
Use these concepts:
- Numbers
- Flows
- Schedules
- Holiday Calendars
- Teams
- Activity
- Settings

Not:
- low-level PBX objects first

**Flow builder payload should stay graph-based**
The current graph version payload pattern is fine as the UI contract base.

**Important UX rule**
Users should not manually build “holiday -> exception -> break -> hours” chains in flows.
They should:
- define schedules centrally
- select a schedule in a schedule-check node
- let compiler enforce precedence

That prevents chaos by design.

## 18. Phase 14 — Testing plan
Do not ship this architecture without a serious test matrix.

**Unit tests**
- node config validation
- flow graph validation
- schedule precedence compilation
- IR generation
- XML generation
- Lua helper parameter generation

**Snapshot tests**
Given a known flow graph, assert generated XML output.

**Integration tests**
- publish flow => artifact created
- schedule update => dependent fragments recompiled
- DID -> flow route => returned XML points to correct modular context
- fallback to interpreted mode works until parity

**Telephony tests**
In Docker FreeSWITCH:
- inbound call during holiday
- break window behavior
- menu digit routes correctly
- team ring fallback works
- voicemail path works
- compile update only affects new calls

**Non-negotiable**
Add regression tests for any generated XML fragments. Telecom bugs are tiny and evil.

## 19. Concrete implementation order
This is the order I recommend.

**Step 1**
Freeze current runtime and add feature flag.

**Step 2**
Introduce compile IR and node spec registry.

**Step 3**
Build schedule compiler first. This is simpler and high-value.

**Step 4**
Build modular flow compiler for:
- start
- schedule_check
- menu
- voicemail
- hangup

**Step 5**
Build team compiler + Lua helper for team routing.

**Step 6**
Add compiled artifact tables and activation manifest.

**Step 7**
Refactor xml_curl to serve compiled manifests instead of interpreted runtime.

**Step 8**
Migrate DID flow destination from answer + park to compiled flow entry extension.

**Step 9**
Keep observability by emitting trace events from compiled runtime.

**Step 10**
Deprecate interpreted node execution path after parity.

## 20. What to remove or demote later
Once compiled runtime is stable, these should no longer be core call-path components:
- FlowRuntimeStarter hot-path use 
- FlowExecutionService as live runtime engine 
- ScheduleEngine in call execution path 
- runtime node handlers as the primary engine 

They may remain:
- for simulation
- for tests
- for legacy fallback mode
- for offline flow preview

That would actually be a neat use for them.

## 21. My blunt assessment of the repo
NIZAM is already beyond “random prototype” territory. The repo has real structure:
- graph/version model
- schedule/holiday/team abstractions
- API-first core
- XML compiler base
- session/trace mindset

That’s the good news.

The bad news is that it is currently trying to live in two runtime philosophies at once:
- PBX-local XML runtime
- application-interpreted flow engine

That split-brain is the main architectural tension.

Your plan should resolve it decisively in favor of:
*compile ahead of time, run locally, observe centrally.*

That is the cleanest path from current NIZAM to the system we discussed.