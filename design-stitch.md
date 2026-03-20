# NIZAM Google Stitch Prompt Pack

Use these prompts in Google Stitch to generate visual wireframes for NIZAM.

## Global style prompt

Design a modern light-theme SaaS admin UI for **NIZAM**, a communications control platform and programmable telephony management system. Use a clean professional B2B product style inspired by Linear, Stripe Dashboard, and modern observability tools. Avoid legacy PBX aesthetics.

### Visual rules
- light theme
- white and soft-gray surfaces
- restrained blue-indigo brand accents
- semantic status colors for success, warning, error, info
- dense but readable layouts
- subtle borders and soft shadows
- strong typography hierarchy
- compact professional tables
- modern tabs, chips, filters, and drawers
- one-level sidebar submenu navigation only
- no third-level sidebar nesting
- detail pages use tabs
- parent sidebar sections are clickable overview pages

### Navigation structure
Sidebar sections:
- Dashboard
- Phone System
- Routing
- Connectivity
- Calls
- Contact Center
- Integrations
- Admin

Submenus:
- Dashboard: Overview
- Phone System: Extensions, DIDs, Ring Groups, IVRs, Time Conditions, Schedules, Holiday Calendars, Device Profiles
- Routing: Policies, Flows, Bridges, Route Explorer, Simulations, Published Dialplan
- Connectivity: Gateways, Registrations, SIP Profiles, NAT / Media
- Calls: Live Calls, CDRs, Recordings, Events, Trace
- Contact Center: Queues, Agents, Wallboard, Metrics
- Integrations: Webhooks, API Tokens, Event Streams
- Admin: Users, Roles, Tenant Settings, Audit Logs, Usage

### Product UX priorities
- routing visibility
- runtime health visibility
- dependency visibility
- simulation and traceability
- operational clarity
- safe editing of complex telecom objects

### Key UI patterns
- left sidebar with one submenu level
- top header with breadcrumbs, search, alerts, quick actions
- summary cards on overview pages
- list pages with dense tables and filters
- detail pages with summary strip + tabs + optional right rail
- route chips and status chips throughout
- runtime panels and dependency panels on important detail pages

---

## Prompt 1: App shell and global navigation

Create the main application shell for NIZAM, a modern communications control platform. Show a left sidebar with logo, tenant switcher, top-level navigation sections, and one-level submenus. Show a top header with breadcrumbs, global search, environment badge, alerts, and user menu. Main content area should be spacious but dense enough for operations work. Use a clean light SaaS admin style, subtle borders, blue-indigo accents, and professional typography. Avoid old telecom or PBX styling.

---

## Prompt 2: Dashboard overview

Design a dashboard overview screen for NIZAM. Use the app shell with sidebar and top header. The page title is Dashboard.

Layout:
- top row of summary cards for Active Calls, Registered Gateways, Queued Calls, Failed Webhooks, Routing Warnings
- second row with two columns
- left column: System Health card and Recent Routing Changes table
- right column: Alerts and Warnings card and Quick Actions card
- bottom row: Recent Calls table and Recent Events table

The screen should feel like a serious telecom operations dashboard with modern SaaS clarity, not a noisy NOC wall. Use status chips, route chips, subtle cards, and compact data tables.

---

## Prompt 3: DID list

Design a DID list page for NIZAM. Show a page header with title DIDs and a primary button to Create DID. Below it, show filters for search, status, destination type, and source scope. The main content is a dense table.

Table columns:
- number
- label
- source scope
- destination
- status
- last matched
- actions

The destination column should use route chips. The page should look clean, highly structured, and professional.

---

## Prompt 4: DID detail

Design a DID detail page for NIZAM.

Header summary strip should show:
- DID number
- label
- active status chip
- precedence chip
- effective route chip
- actions: Edit, Disable, Delete, Simulate

Use tabs:
- Overview
- Routing
- Source Matching
- Activity
- Dependencies

Overview tab:
- DID identity card
- effective route card
- last matched call summary
- right rail with runtime warnings and linked routing objects

Routing tab:
- destination type selector
- destination target selector
- route preview card
- dependency warnings

Source Matching tab:
- generic match
- gateway-specific match
- registration-specific match
- precedence explanation panel

The screen should clearly answer: where does this number go?

---

## Prompt 5: Gateway list

Design a gateway list page for NIZAM under the Connectivity section. Show filters for search, active state, transport, and registration state. Main content is a compact table.

Table columns:
- name
- host
- transport
- profile
- registration state
- active
- last sync
- actions

This page should feel like carrier/trunk infrastructure management in a modern SaaS admin product.

---

## Prompt 6: Gateway detail

Design a gateway detail page for a SIP trunk/carrier in NIZAM.

Header summary strip shows:
- gateway name
- active chip
- registration state chip
- transport chip
- profile chip
- actions: Edit, Disable, Reconcile, Delete

Tabs:
- Overview
- Connection
- Authentication
- Codec & Media
- Runtime
- XML

Overview tab:
- host, realm, proxy, port, register mode
- linked bridges count

Connection tab:
- host, port, transport, proxy, register proxy, realm, from-domain

Authentication tab:
- username, password, extension, from-user, caller-id-in-from

Codec & Media tab:
- inbound codecs
- outbound codecs
- allow transcoding

Runtime tab:
- current Sofia status
- last command results
- registration health
- last reconcile result

XML tab:
- generated XML code panel
- file path
- last written timestamp
- copy button

Use a professional light theme and make runtime state very visible.

---

## Prompt 7: Bridge list

Design a bridge list page for NIZAM. Bridges are reusable outbound destinations.

Table columns:
- name
- bridge type
- gateway
- destination template
- used by count
- active

Show route-oriented UI patterns and clean status chips. Make the concept feel understandable even to users who are not telecom experts.

---

## Prompt 8: Bridge detail

Design a bridge detail page for NIZAM.

Header summary strip:
- bridge name
- type chip
- gateway chip if applicable
- active chip
- used by count

Tabs:
- Overview
- Target
- Usage
- Runtime

Overview tab:
- plain-language explanation: reusable outbound destination
- destination template preview
- gateway link

Target tab:
- bridge type selector
- gateway selector
- destination template input
- validation panel

Usage tab:
- linked objects table with object type, object name, route role, open link

Runtime tab:
- compiled output preview
- warnings if gateway inactive or missing

Use a clean B2B operations design with clear routing visibility.

---

## Prompt 9: Routing overview

Design a Routing Overview page for NIZAM.

Summary cards:
- total flows
- published flows
- total policies
- bridges in use
- routing warnings

Main sections:
- Draft vs Published card
- Routing Warnings table
- Recently Changed Routing Objects table

The page should feel like the control center for the routing brain of the product.

---

## Prompt 10: Policy detail

Design a policy detail page for NIZAM.

Tabs:
- Overview
- Conditions
- Routing
- Preview
- Dependencies

Conditions tab:
- modern condition builder UI with field selector, operator selector, value input, add row button

Routing tab:
- match destination picker
- no-match destination picker
- route preview card

Preview tab:
- simulation result panel showing how conditions resolve

The page should make policy logic understandable and safe to edit.

---

## Prompt 11: Flows list

Design a Flows list page for NIZAM.

Table columns:
- name
- draft/published state
- node count
- used by count
- last published
- actions

Use strong visual chips for draft vs published status and a modern routing-oriented UI.

---

## Prompt 12: Flow editor

Design a modern flow editor screen for programmable call routing in NIZAM.

Header summary strip:
- flow name
- draft/published state chip
- node count
- compile status
- publish button

Use tabs:
- Overview
- Editor
- Publish
- Dependencies
- Versions

Editor tab should use a three-column workspace:
- left panel: node list and add node button
- center: graph canvas or block-based flow workspace
- right panel: selected node inspector with routing fields and validation

Publish tab should show:
- draft summary
- compile preview panel
- warnings
- publish action

Use a clean light professional style with strong information hierarchy.

---

## Prompt 13: Route explorer

Design a flagship route explorer screen for NIZAM.

Use a three-column workspace.

Left column inputs:
- tenant selector
- DID or dialed number input
- caller ID input
- gateway selector
- registration selector
- datetime picker
- run simulation button

Center column:
- vertical resolution timeline showing DID match, source precedence, policy match, flow path, bridge selection, final destination

Right column:
- warnings
- dependency insights
- inactive targets
- linked objects

This should feel like a modern observability and debugging tool for call routing.

---

## Prompt 14: Live calls page

Design a Live Calls page for NIZAM.

Main content is a dense operations table.

Columns:
- call UUID
- direction
- from
- to
- state
- duration
- gateway
- route
- actions

Row click opens a right-side detail drawer with:
- event timeline
- route summary
- call control actions

Available call controls:
- transfer
- hold
- unhold
- start recording
- stop recording
- hangup

Make it look like a serious operations console but still modern and clean.

---

## Prompt 15: CDR detail

Design a CDR detail page for NIZAM.

Tabs:
- Overview
- Legs
- Routing
- Recording
- Events

Routing tab should show:
- route summary
- gateway used
- bridge used
- flow path if available
- hangup cause

Recording tab should show playback/download panel.
Events tab should show raw event timeline and structured event list.

Use a modern admin layout with summary strip, tabs, and optional right rail.

---

## Prompt 16: Connectivity overview

Design a Connectivity Overview page for NIZAM.

Summary cards:
- gateways up
- gateways down
- last reconcile status
- profile health
- NAT/media warnings

Main sections:
- Gateway Runtime table
- XML Drift / Runtime Hygiene card with db count, xml count, orphan xml count, and Reconcile action button

This screen should clearly separate config state from runtime state.

---

## Prompt 17: Calls overview

Design a Calls Overview page for NIZAM.

Summary cards:
- active calls
- answered today
- missed today
- failed calls
- recordings today

Main sections:
- live calls table
- hangup cause distribution card/chart
- recent traces
- recent recordings

Use a practical and high-information SaaS layout.

---

## Prompt 18: Full app overview board

Design a composite product overview board for NIZAM showing several pages as mini-panels in one shot: Dashboard, DID detail, Gateway detail, Bridge detail, Flow editor, and Route Explorer. Keep a consistent design system across all panels. Use a modern light-theme SaaS admin style with blue-indigo accents, status chips, route chips, tables, and tabbed detail pages.

---

## Short compressed master prompt

Design a modern light-theme SaaS admin UI for NIZAM, a communications control platform and programmable telephony management system. The app uses a left sidebar with one-level submenus and clickable parent overview pages. Main sections are Dashboard, Phone System, Routing, Connectivity, Calls, Contact Center, Integrations, and Admin. Visual style should feel like Linear plus Stripe Dashboard plus modern observability tools, not a legacy PBX. Use white and soft-gray surfaces, blue-indigo accents, semantic status colors, dense but readable layouts, compact tables, clean tabs, subtle cards, professional typography, and clear runtime visibility. Prioritize routing visibility, dependency visibility, runtime health, and simulation. Key screens include Dashboard Overview, DID List/Detail, Gateway List/Detail with Runtime and XML tabs, Bridge List/Detail, Routing Overview, Flow Editor, Route Explorer, Live Calls, and CDR Detail.

---

## Best generation order
1. App shell
2. Dashboard overview
3. DID detail
4. Gateway detail
5. Bridge detail
6. Route explorer
7. Flow editor
8. Live calls
9. Connectivity overview
10. Routing overview
