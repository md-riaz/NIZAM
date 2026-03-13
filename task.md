Good instinct. Cutting the half-baked UI early is exactly the kind of ruthless decision good system designers make. Interfaces are cheap to build later; domain models and execution engines are expensive to rewrite. So stabilizing the backend and API first is the correct move.

Let’s treat this like a controlled refactor, not a demolition. The goal is:

Turn NIZAM into a clean API-first communications control plane, with no UI assumptions.

Then React (or any client) becomes just another consumer.


---

Phase 1 — Freeze the Current System

First protect what already works.

1. Create a stabilization branch

Do this before touching anything.

git checkout -b refactor/api-first-core

The main branch should remain deployable.


---

2. Tag the current architecture

You want a historical checkpoint.

git tag v0.1-pbx-platform
git push --tags

This lets you compare future architecture decisions.


---

Phase 2 — Remove UI Responsibility

Right now NIZAM mixes control plane and admin UI.

Examples:

/ui/... routes

Blade views

surface page system

UiController


These should disappear from the backend core.

Your backend should expose only:

API
+
runtime services


---

3. Remove /ui routes

Delete or comment this block:

routes/web.php

Route::middleware('auth')->prefix('ui')...

Those pages are presentation concerns.

The backend should not care.


---

4. Delete UI controllers

Remove:

app/Http/Controllers/Web/

Especially:

UiController
AuthController

Authentication should move entirely to API.


---

5. Remove Blade views

Delete:

resources/views/ui
resources/views/dashboard
resources/views/extensions

The backend should not render HTML.


---

6. Keep only two web routes

These must stay:

/freeswitch/xml-curl
/provision/{mac}

Because FreeSWITCH and devices depend on them.

Everything else becomes API.


---

Phase 3 — Turn the backend into a pure control plane

Your backend must expose domain APIs, not PBX abstractions.

Right now the domain is still PBX-ish:

IVR
RingGroup
TimeCondition
DID

We want to evolve toward:

Number
Flow
Node
Schedule
Team
Endpoint

But don't break everything yet.

First stabilize existing APIs.


---

7. Create API versioning

Right now routes look like:

/api/tenants

Change to:

/api/v1/tenants

Future-proofing matters.

routes/api.php

Route::prefix('v1')->group(function() {
 ...
});


---

8. Introduce a service boundary

Currently many controllers probably talk directly to models.

Instead enforce:

Controller
 ↓
Service
 ↓
Repository / Model

Example:

ExtensionController
 ↓
ExtensionService
 ↓
ExtensionRepository

This prevents business logic leaking into controllers.


---

9. Introduce a Domain layer

Inside app/ create:

Domain/

Structure example:

app/Domain

Call/
Flow/
Tenant/
Routing/
Media/
Automation/
Provisioning/

Example:

Domain/Flow
Domain/Call
Domain/Number
Domain/Team

Your current models migrate into domains gradually.


---

Phase 4 — Build the Flow Engine (the real core)

This is the heart of the modern PBX.

Everything else is infrastructure.


---

10. Introduce Flow entities

Create tables:

flows
flow_versions
flow_nodes
flow_edges

Example schema:

flows
-----
id
tenant_id
name
active_version_id
created_at

flow_versions
-------------
id
flow_id
version
definition_json
is_active


---

11. Introduce Node types

Nodes represent call actions.

Example node types:

start
business_hours
holiday_check
menu
ring_user
ring_team
voicemail
webhook
play_audio
end


---

12. Create FlowExecutionService

app/Services/FlowExecutionService.php

Responsibility:

execute(call_context)

Pseudo logic:

node = flow.start_node

while node exists:
 result = NodeHandler(node).execute(context)

 node = result.next_node


---

13. Introduce Node Handlers

Example:

app/Flow/Nodes/

MenuNode
HoursNode
RingNode
VoicemailNode
WebhookNode

Each implements:

execute(CallContext $ctx)

This isolates telephony logic cleanly.


---

Phase 5 — Refactor FreeSWITCH IntegrationFreeSWITCH should ask your system what to do next, not run static dialplans.

Your system already uses:

mod_xml_curl

That is perfect.


---

14. XML handler becomes entry point

Flow:

FreeSWITCH → xml_curl
 ↓
NIZAM
 ↓
Resolve number → flow
 ↓
Start flow execution


---

15. Add CallSession model

Track runtime call state.

call_sessions
-------------
uuid
tenant_id
flow_id
current_node
state
started_at

Now calls become stateful workflows.


---

Phase 6 — Event Pipeline

This is where modern platforms shine.


---

16. Normalize events

Current ESL events:

CHANNEL_CREATE
CHANNEL_ANSWER
CHANNEL_BRIDGE
CHANNEL_HANGUP

Normalize them into domain events:

call.started
call.answered
call.bridged
call.hangup
call.missed

You already started this idea.

Double down on it.


---

17. Create event processors

app/Domain/Call/EventProcessors

Examples:

RecordingProcessor
WebhookProcessor
AnalyticsProcessor
FlowContinuationProcessor

Each listens to call events.


---

Phase 7 — Observability

One massive advantage over competitors:

trace every call execution.


---

18. Call trace table

call_trace_events

Example record:

call_uuid
node_id
node_type
action
timestamp
payload

This enables:

Call debugger
Flow replay
Failure analysis

Most PBX systems lack this.


---

Phase 8 — Clean API Surface

Your public API should expose only domain concepts.

Example:

Numbers

GET /numbers
POST /numbers

Flows

GET /flows
POST /flows
PUT /flows/{id}

Teams

GET /teams
POST /teams

Calls

POST /calls/originate
GET /calls/{id}

Events

GET /events


---

Phase 9 — UI comes later

After API stabilizes:

Create a separate repo.

nizam-ui

Stack suggestion:

React
React Flow
TanStack Query
Tailwind

Flow builder:

React Flow library

Your backend stays untouched.


---

Phase 10 — New NIZAM architecture

After refactor it becomes:

React UI
 │
REST API
 │
Laravel Control Plane
 │
Flow Engine
 │
Event Bus
 │
FreeSWITCH

Which is exactly how modern communication platforms look.


---

Final advice

You're doing something many engineers never realize:

FreeSWITCH is infrastructure, not the product.

The product is:

communication automation

If NIZAM focuses on that idea, it can genuinely become a very modern voice platform.


---

If you'd like, I can also show you something extremely useful next:

The ideal database schema for the Flow Builder and call execution engine.

That schema design will save you months of refactoring later.
