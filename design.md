# NIZAM UI Design Spec

## Purpose
This document is a **visual-wireframe-ready product design specification** for NIZAM.
It is written so it can be pasted into **Google Stitch** or used by product/design/frontend teams to generate:
- visual wireframes
- high-fidelity admin screens
- component inventory
- page architecture
- layout rules
- design token foundations

The product is a **modern communications control platform**.
It must **not** look like a legacy PBX panel.
It should feel like a serious SaaS operations product with telephony depth.

---

# 1. Product Design Direction

## Product personality
NIZAM should feel:
- modern
- structured
- operational
- trustworthy
- technical without being ugly
- dense enough for operators, but not chaotic

## Visual reference direction
Blend inspiration from:
- Linear for clarity and restraint
- Stripe Dashboard for admin confidence
- Vercel for calm structure
- modern observability dashboards for status surfaces

Avoid:
- legacy PBX styling
- dark neon hacker dashboard aesthetics
- overloaded table walls without hierarchy
- three-level nested navigation
- giant card-only layouts that waste space

## Core UX principles
1. **Routing-first visibility**
2. **Operational clarity**
3. **Safe editing for complex telecom objects**
4. **One-level submenu navigation only**
5. **Detail pages use tabs, not deeper sidebar nesting**
6. **Every routing object shows dependencies and effective route**
7. **Runtime state must be visible, not hidden**
8. **Advanced options progressive, not dumped up front**

---

# 2. Global Information Architecture

## Top-level navigation
- Dashboard
- Phone System
- Routing
- Connectivity
- Calls
- Contact Center
- Integrations
- Admin

## Sidebar submenu structure

### Dashboard
- Overview

### Phone System
- Extensions
- DIDs
- Ring Groups
- IVRs
- Time Conditions
- Schedules
- Holiday Calendars
- Device Profiles

### Routing
- Policies
- Flows
- Bridges
- Route Explorer
- Simulations
- Published Dialplan

### Connectivity
- Gateways
- Registrations
- SIP Profiles
- NAT / Media

### Calls
- Live Calls
- CDRs
- Recordings
- Events
- Trace

### Contact Center
- Queues
- Agents
- Wallboard
- Metrics

### Integrations
- Webhooks
- API Tokens
- Event Streams

### Admin
- Users
- Roles
- Tenant Settings
- Audit Logs
- Usage

## Navigation behavior rules
- Sidebar supports **one level of submenu only**.
- Parent menu items are clickable and open overview pages.
- Third-level hierarchy is forbidden in sidebar.
- Deeper structure must be represented with page tabs.
- On smaller screens, sidebar collapses to icon rail with flyout submenu.

---

# 3. Global App Shell

## Shell layout

### Left sidebar
Contains:
- logo/brand mark
- product name
- tenant switcher
- top-level nav sections
- submenu items
- bottom utility area
  - user avatar/menu
  - notifications shortcut
  - theme toggle
  - quick system health chip

### Top header
Contains:
- breadcrumb
- page title context
- global search
- tenant context chip
- environment chip (prod/staging/dev)
- quick actions button
- alerts button
- profile menu

### Main content region
Contains:
- page title row
- summary cards when relevant
- primary CTA area
- filter/search row
- content layout

### Optional right rail
Use on important detail pages for:
- runtime health
- dependencies
- warnings
- recent activity
- quick actions

---

# 4. Design Tokens

## 4.1 Color tokens

### Neutral palette
- `color.neutral.0 = #FFFFFF`
- `color.neutral.25 = #FCFCFD`
- `color.neutral.50 = #F8FAFC`
- `color.neutral.100 = #F1F5F9`
- `color.neutral.200 = #E2E8F0`
- `color.neutral.300 = #CBD5E1`
- `color.neutral.400 = #94A3B8`
- `color.neutral.500 = #64748B`
- `color.neutral.600 = #475569`
- `color.neutral.700 = #334155`
- `color.neutral.800 = #1E293B`
- `color.neutral.900 = #0F172A`
- `color.neutral.950 = #020617`

### Brand palette
Use blue-indigo with restrained saturation.
- `color.brand.50 = #EEF2FF`
- `color.brand.100 = #E0E7FF`
- `color.brand.200 = #C7D2FE`
- `color.brand.300 = #A5B4FC`
- `color.brand.400 = #818CF8`
- `color.brand.500 = #6366F1`
- `color.brand.600 = #4F46E5`
- `color.brand.700 = #4338CA`
- `color.brand.800 = #3730A3`
- `color.brand.900 = #312E81`

### Semantic palette
#### Success
- `color.success.50 = #ECFDF3`
- `color.success.100 = #D1FADF`
- `color.success.500 = #12B76A`
- `color.success.700 = #027A48`

#### Warning
- `color.warning.50 = #FFFAEB`
- `color.warning.100 = #FEF0C7`
- `color.warning.500 = #F79009`
- `color.warning.700 = #B54708`

#### Danger
- `color.danger.50 = #FEF3F2`
- `color.danger.100 = #FEE4E2`
- `color.danger.500 = #F04438`
- `color.danger.700 = #B42318`

#### Info
- `color.info.50 = #EFF8FF`
- `color.info.100 = #D1E9FF`
- `color.info.500 = #2E90FA`
- `color.info.700 = #175CD3`

### Routing-specific accents
Use sparingly as categorical accents.
- `color.routing.flow = #7C3AED`
- `color.routing.policy = #2563EB`
- `color.routing.bridge = #0F766E`
- `color.routing.gateway = #7C2D12`
- `color.routing.did = #9333EA`
- `color.routing.queue = #B45309`

## 4.2 Background tokens
- `bg.canvas = color.neutral.25`
- `bg.surface = color.neutral.0`
- `bg.surfaceMuted = color.neutral.50`
- `bg.sidebar = color.neutral.0`
- `bg.header = rgba(255,255,255,0.85)`
- `bg.overlay = rgba(15,23,42,0.48)`
- `bg.code = #0B1220`

## 4.3 Text tokens
- `text.primary = color.neutral.900`
- `text.secondary = color.neutral.600`
- `text.tertiary = color.neutral.500`
- `text.inverse = color.neutral.0`
- `text.brand = color.brand.700`
- `text.success = color.success.700`
- `text.warning = color.warning.700`
- `text.danger = color.danger.700`
- `text.info = color.info.700`

## 4.4 Border tokens
- `border.default = color.neutral.200`
- `border.strong = color.neutral.300`
- `border.brand = color.brand.300`
- `border.danger = color.danger.300`
- `border.success = color.success.300`

## 4.5 Shadow tokens
- `shadow.xs = 0 1px 2px rgba(16,24,40,0.05)`
- `shadow.sm = 0 1px 3px rgba(16,24,40,0.08), 0 1px 2px rgba(16,24,40,0.04)`
- `shadow.md = 0 4px 8px rgba(16,24,40,0.08), 0 2px 4px rgba(16,24,40,0.04)`
- `shadow.lg = 0 12px 24px rgba(16,24,40,0.12)`
- `shadow.focus = 0 0 0 4px rgba(99,102,241,0.16)`

## 4.6 Radius tokens
- `radius.xs = 6px`
- `radius.sm = 8px`
- `radius.md = 12px`
- `radius.lg = 16px`
- `radius.xl = 20px`
- `radius.full = 999px`

## 4.7 Spacing tokens
Use 4px base scale.
- `space.1 = 4px`
- `space.2 = 8px`
- `space.3 = 12px`
- `space.4 = 16px`
- `space.5 = 20px`
- `space.6 = 24px`
- `space.8 = 32px`
- `space.10 = 40px`
- `space.12 = 48px`
- `space.16 = 64px`

## 4.8 Typography tokens

### Font families
- `font.sans = Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif`
- `font.mono = JetBrains Mono, SFMono-Regular, Menlo, monospace`

### Type scale
- `text.xs = 12px / 16px / 500`
- `text.sm = 14px / 20px / 500`
- `text.md = 16px / 24px / 500`
- `text.lg = 18px / 28px / 600`
- `text.xl = 20px / 30px / 600`
- `text.2xl = 24px / 32px / 700`
- `text.3xl = 30px / 38px / 700`

### Usage rules
- page titles: `text.2xl`
- section titles: `text.lg`
- card titles: `text.md`
- body text: `text.sm`
- table text: `text.sm`
- labels/helper text: `text.xs`
- code/XML/JSON: `font.mono`, `text.sm`

## 4.9 Sizing tokens
- `sidebar.width.expanded = 272px`
- `sidebar.width.collapsed = 76px`
- `header.height = 64px`
- `table.row.height = 48px`
- `input.height.md = 40px`
- `input.height.lg = 44px`
- `button.height.sm = 32px`
- `button.height.md = 40px`
- `button.height.lg = 44px`

## 4.10 Motion tokens
- `motion.fast = 120ms ease`
- `motion.base = 180ms ease`
- `motion.slow = 260ms ease`

Use motion for:
- sidebar expand/collapse
- tabs
- drawers
- subtle hover/focus states

Do not use flashy transitions.

---

# 5. Component Style Rules

## Buttons
### Primary
- solid brand background
- white text
- medium radius
- subtle shadow

### Secondary
- neutral background
- border default
- dark text

### Danger
- light red background or outlined red

### Ghost
- transparent background
- used in table rows and icon actions

## Inputs
- white background
- 1px neutral border
- 40px height default
- strong focus ring using brand focus shadow
- helper text under input
- validation text below helper text

## Cards
- white background
- subtle border
- subtle shadow
- 16px to 24px padding
- title row with action slot

## Tables
- sticky header where useful
- zebra not required
- row hover subtle neutral background
- status chips in cells
- route chips for destinations
- right-aligned action menu

## Tabs
- underline or pill-light style
- compact
- strong active contrast
- no giant segmented controls

## Drawers
- width 480px to 560px
- header, body, sticky footer actions
- used for simple CRUD only

## Code panels
- dark code background
- mono font
- line wrap toggle
- copy button

---

# 6. Core Reusable Components

## Navigation
- `AppSidebar`
- `SidebarSection`
- `SidebarItem`
- `SidebarSubmenu`
- `TenantSwitcher`
- `TopHeader`
- `Breadcrumbs`
- `GlobalSearch`

## Display
- `StatusChip`
- `RouteChip`
- `RuntimeBadge`
- `HealthBanner`
- `SummaryCard`
- `MetricCard`
- `EmptyState`
- `WarningCallout`

## Data display
- `DataTable`
- `FilterBar`
- `SearchInput`
- `ColumnVisibilityMenu`
- `Pagination`
- `RowActionMenu`

## Form/routing components
- `DestinationPicker`
- `ObjectReferenceSelect`
- `GatewaySelect`
- `ConditionBuilder`
- `ScheduleEditor`
- `FlowNodeInspector`
- `SecretInput`

## Runtime/debug components
- `DependencyPanel`
- `RecentActivityPanel`
- `RoutePreviewCard`
- `TraceTimeline`
- `XmlPreviewPanel`
- `JsonPreviewPanel`
- `SimulationResultPanel`

---

# 7. Screen Wireframes and Layout Architecture

Below is the full app wireframe architecture in text form.
This is written so Google Stitch can generate visual screens from it.

---

# 8. Dashboard Wireframe

## Screen: Dashboard Overview

### Goal
Show operator health at a glance.

### Layout
- top header with page title `Dashboard`
- right side actions: `Create`, `Run Reconcile`, `Open Route Explorer`
- first row: 5 summary cards
- second row: two-column layout
- third row: operations tables

### Summary cards
1. Active Calls
2. Registered Gateways
3. Queued Calls
4. Failed Webhooks
5. Routing Warnings

### Left column section
#### System Health card
- app API health
- FreeSWITCH health
- Redis health
- DB health
- event stream health

#### Recent Routing Changes table
Columns:
- object
- action
- actor
- time

### Right column section
#### Alerts and Warnings card
- DID pointing to inactive target
- gateway unregistered
- flow draft not published
- ring group empty members warning

#### Quick Actions card
- create DID
- create Gateway
- create Bridge
- open simulation
- reconcile gateway XML

### Bottom section
#### Recent Calls table
Columns:
- from
- to
- direction
- state
- duration
- route

#### Recent Events table
Columns:
- timestamp
- event type
- call UUID
- payload preview

---

# 9. Phone System Wireframes

## Screen: Extensions List

### Layout
- title `Extensions`
- create extension button
- filter bar
- table

### Filter bar
- search by extension/name
- filter by status
- filter by device profile
- filter by active

### Table columns
- extension
- display name
- caller ID
- device profile
- status
- last activity
- actions

## Screen: Extension Detail

### Header summary strip
- extension number
- user/display name
- active status chip
- device profile chip
- actions: edit, disable, delete

### Tabs
- Overview
- Devices
- Routing
- Activity
- Dependencies

### Overview tab layout
Left main:
- general info card
- caller ID card
- registration summary card

Right rail:
- dependencies panel
- recent calls mini list

---

## Screen: DID List

### Layout
- title `DIDs`
- create DID button
- filters
- table

### Filters
- search by number or label
- active/inactive
- destination type
- generic/gateway-specific/registration-specific

### Table columns
- number
- label
- source scope
- destination
- status
- last matched
- actions

## Screen: DID Detail

### Header summary strip
- DID number
- label
- active chip
- precedence chip
- effective route chip
- actions: edit, disable, delete, simulate

### Tabs
- Overview
- Routing
- Source Matching
- Activity
- Dependencies

### Overview tab
Left main column:
- DID identity card
- effective route card
- last matched call summary

Right rail:
- runtime warnings
- linked policy/flow/bridge summary

### Routing tab
- destination type selector
- destination target selector
- route preview card
- validation/warning callouts

### Source Matching tab
- generic match
- gateway-specific match
- registration-specific match
- precedence explanation panel

---

## Screen: Ring Groups List

### Table columns
- name
- strategy
- members
- timeout
- fallback target
- status

## Screen: Ring Group Detail

### Tabs
- Overview
- Members
- Routing
- Runtime
- Dependencies

### Routing tab
- timeout seconds
- member strategy
- fallback destination picker
- fallback behavior summary
- warning if no active members

---

## Screen: IVR List
- name
- extension
- prompt status
- timeout route
- invalid route
- active

## Screen: IVR Detail

### Tabs
- Overview
- Menu Options
- Prompts
- Routing
- Dependencies

### Menu Options tab
- digits table
- destination chips
- add option button

### Routing tab
- timeout destination
- invalid destination
- route preview chips

---

## Screen: Time Conditions List
- name
- timezone
- schedule summary
- match route
- no-match route
- status

## Screen: Time Condition Detail

### Tabs
- Overview
- Schedule
- Routing
- Preview
- Dependencies

### Preview tab
- current time evaluation
- next state change
- test datetime picker
- simulation result panel

---

## Screen: Schedules List
- name
- timezone
- business hours summary
- holiday calendar
- active

## Screen: Schedule Detail

### Tabs
- Overview
- Rules
- Holidays
- Preview
- Dependencies

---

## Screen: Holiday Calendars List
- name
- region
- entry count
- active

## Screen: Device Profiles List
- name
- transport
- codec set
- security profile
- active

---

# 10. Routing Wireframes

## Screen: Routing Overview

### Summary cards
- total flows
- published flows
- total policies
- bridges in use
- routing warnings

### Main sections
#### Draft vs Published card
- count of draft-only flows
- publish shortcuts

#### Routing Warnings table
- object
- issue
- severity
- open action

#### Recently Changed Routing Objects table
- object type
- name
- updated by
- time

---

## Screen: Policies List

### Table columns
- name
- type
- match route
- no-match route
- active
- updated at

## Screen: Policy Detail

### Tabs
- Overview
- Conditions
- Routing
- Preview
- Dependencies

### Conditions tab
Use builder UI:
- add condition row
- field selector
- operator selector
- value input
- logical grouping support later

### Routing tab
- match destination picker
- no-match destination picker
- preview card

---

## Screen: Flows List

### Table columns
- name
- draft/published status
- node count
- used by count
- last published
- actions

## Screen: Flow Detail

### Header summary strip
- name
- draft/published chip
- node count
- compile status
- publish button
- actions

### Tabs
- Overview
- Editor
- Publish
- Dependencies
- Versions

### Editor tab wireframe
Three-column workspace.

#### Left panel
- node list
- add node button
- flow structure outline

#### Center canvas
- graph canvas or block editor
- selected path highlight
- zoom controls

#### Right panel
- selected node inspector
- node fields
- destination picker
- validation messages

### Publish tab
- draft summary
- compile preview panel
- publish impact warnings
- confirm publish action

---

## Screen: Bridges List

### Table columns
- name
- bridge type
- gateway
- destination template
- used by count
- active

## Screen: Bridge Detail

### Header summary strip
- bridge name
- type chip
- gateway chip if gateway-type
- active chip
- used by count

### Tabs
- Overview
- Target
- Usage
- Runtime

### Overview tab
- plain language description: reusable outbound destination
- destination template preview
- gateway link

### Target tab
- bridge type selector
- gateway selector
- destination template input
- validation panel

### Usage tab
Table of linked objects:
- object type
- object name
- route role
- open link

### Runtime tab
- compiled bridge output preview
- warnings if gateway inactive

---

## Screen: Route Explorer

### Goal
This is a flagship diagnostic tool.

### Layout
Three-column analytical workspace.

#### Left column: Inputs
- tenant select
- DID / dialed number input
- caller ID input
- gateway select
- registration select
- datetime picker
- run simulation button

#### Center column: Resolution path
Visual vertical route timeline:
1. DID matched
2. source precedence selected
3. policy matched or skipped
4. flow entered
5. nodes traversed
6. bridge/gateway chosen
7. final destination output

#### Right column: Insights
- warnings
- inactive targets
- unresolved references
- route dependencies
- linked objects

### Result cards
- effective route
- confidence/valid state
- final destination string
- runtime notes

---

## Screen: Simulations

### Suggested tabbed layout
- DID Simulation
- Policy Simulation
- Flow Simulation
- Time Preview

Each tab uses:
- left input panel
- center result
- right notes/warnings

---

## Screen: Published Dialplan

### Layout
- tenant selector
- latest publish status card
- generated at timestamp
- warnings banner if stale
- code preview panel
- manifest tree sidebar

### Main sections
- manifest summary
- compiled XML preview
- source object references

---

# 11. Connectivity Wireframes

## Screen: Connectivity Overview

### Summary cards
- gateways up
- gateways down
- last reconcile status
- profile health
- NAT/media warnings

### Main sections
#### Gateway Runtime table
Columns:
- gateway
- state
- status
- realm
- proxy
- last updated

#### XML Drift / Runtime Hygiene card
- db count
- xml count
- orphan xml count
- reconcile action button

---

## Screen: Gateways List

### Filters
- search
- active/inactive
- transport
- registered/unregistered

### Table columns
- name
- host
- transport
- profile
- registration state
- active
- last sync
- actions

## Screen: Gateway Detail

### Header summary strip
- gateway name
- active chip
- registration state chip
- transport chip
- profile chip
- actions: edit, disable, reconcile, delete

### Tabs
- Overview
- Connection
- Authentication
- Codec & Media
- Runtime
- XML

### Overview tab
- summary card with host, realm, proxy, port
- register yes/no
- gateway identity
- linked bridges count

### Connection tab
Fields:
- host
- port
- transport
- proxy
- register proxy
- realm
- from domain

### Authentication tab
Fields:
- username
- password
- extension
- from-user
- caller-id-in-from toggle

### Codec & Media tab
Fields:
- inbound codecs
- outbound codecs
- allow transcoding
- SIP/RTP notes

### Runtime tab
Panels:
- current Sofia status
- last command results
- last reconcile result
- registration history later

### XML tab
Panels:
- generated XML code viewer
- file path
- last written timestamp
- copy button

---

## Screen: Registrations List

### Table columns
- gateway
- state
- status
- username
- realm
- proxy
- uptime / last seen
- actions

### Actions
- refresh
- killgw
- startgw
- reconcile

---

## Screen: SIP Profiles

### Detail tabs
- Overview
- Bindings
- Codec Defaults
- NAT
- Runtime

Make admin-only if necessary.

---

## Screen: NAT / Media

### Layout
- key config summary cards
- warning banners
- environment variable mapping
- docs/help side panel

### Fields shown
- SIP IP
- RTP IP
- EXT_SIP_IP
- EXT_RTP_IP
- AGGRESSIVE_NAT_DETECTION
- LOCAL_NETWORK_ACL

---

# 12. Calls Wireframes

## Screen: Calls Overview

### Summary cards
- active calls
- answered today
- missed today
- failed calls
- recordings today

### Main sections
- live calls table
- hangup cause distribution
- recent traces
- recent recordings

---

## Screen: Live Calls

### Table columns
- call UUID
- direction
- from
- to
- state
- duration
- gateway
- route
- actions

### Row click opens detail drawer
Drawer includes:
- call header
- event timeline
- route summary
- call controls

### Call control actions
- transfer
- hold
- unhold
- start recording
- stop recording
- hangup

---

## Screen: CDRs

### Filters
- date range
- direction
- gateway
- hangup cause
- queue
- search number/call UUID

### Table columns
- start time
- from
- to
- direction
- duration
- billsec
- hangup cause
- gateway
- recording

## Screen: CDR Detail

### Tabs
- Overview
- Legs
- Routing
- Recording
- Events

### Routing tab
- route summary
- gateway used
- bridge used
- flow path if available
- hangup cause

---

## Screen: Recordings

### Table columns
- date
- from
- to
- duration
- linked CDR
- retention state
- actions

### Actions
- play
- download
- delete

---

## Screen: Events

### Table columns
- timestamp
- event type
- call UUID
- tenant
- payload excerpt
- actions

### Detail drawer
- full JSON payload
- linked call/CDR

---

## Screen: Trace Viewer

### Goal
One-call forensic timeline.

### Layout
- sticky call header
- center timeline
- right rail insights

### Timeline steps
- call created
- DID resolved
- policy matched
- flow node traversal
- bridge selected
- queue/agent actions if any
- hangup cause
- recording link

### Right rail
- call summary
- route graph mini panel
- warnings
- export trace

---

# 13. Contact Center Wireframes

## Screen: Queues List
- name
- strategy
- waiting
- available agents
- SLA
- status

## Screen: Queue Detail

### Tabs
- Overview
- Agents
- Routing
- Metrics
- Events

### Overview tab
- queue stats
- longest wait
- active calls
- overflow/fallback route

---

## Screen: Agents List
- name
- extension
- queues
- status
- last seen

## Screen: Agent Detail

### Tabs
- Overview
- Queues
- Activity
- Performance

---

## Screen: Wallboard
Full-screen dense but clean operational dashboard.

Sections:
- large queue KPI cards
- queue list with waiting counts
- agent status board
- alert banners

---

## Screen: Metrics
Tabs:
- Queue Metrics
- Agent Metrics
- Trends

---

# 14. Integrations Wireframes

## Screen: Webhooks List
- name
- event types
- destination URL
- status
- last delivery
- fail rate

## Screen: Webhook Detail

### Tabs
- Overview
- Events
- Deliveries
- Retry History

---

## Screen: API Tokens
- token name
- scopes
- created by
- last used
- expires
- actions

---

## Screen: Event Streams
- client name
- stream type
- status
- last activity
- auth mode

---

# 15. Admin Wireframes

## Screen: Users List
- name
- email
- role
- tenant
- status
- last login

## Screen: User Detail
Tabs:
- Overview
- Access
- Activity

---

## Screen: Roles
- role name
- permissions count
- assigned users

## Screen: Role Detail
- permission matrix
- assigned users list

---

## Screen: Tenant Settings
Tabs:
- General
- Branding
- Telephony Defaults
- Security
- Usage
- Advanced

---

## Screen: Audit Logs
- actor
- action
- object type
- object name
- timestamp
- tenant
- diff preview

## Screen: Audit Detail
- before/after JSON
- actor info
- linked objects

---

## Screen: Usage
- API usage cards
- call volume charts
- recording storage usage
- tenant consumption summaries

---

# 16. Page Shell Rules

## Overview page shell
Use for:
- section overview pages
- dashboard

Pattern:
1. title row
2. summary cards row
3. alerts/warnings strip
4. primary content grid
5. recent activity tables

## List page shell
Pattern:
1. title + primary CTA
2. filter/search row
3. bulk actions if needed
4. main data table
5. pagination

## Detail page shell
Pattern:
1. breadcrumb
2. page title + status chips + actions
3. summary strip
4. tabs
5. tab content
6. optional right rail

## Workspace shell
Use for:
- route explorer
- flow editor
- trace viewer
- simulations

Pattern:
- left inputs/context
- center workspace
- right inspector/insights

## Drawer shell
Use only for simple create/edit.
Not for complex routing workflows.

## Full-page form shell
Use for:
- DID
- Gateway
- IVR
- Policy
- Flow
- Queue

---

# 17. Form Architecture Rules

## Routing forms
Any screen that routes somewhere must use a consistent destination selector.

### Destination selector pattern
1. destination type dropdown
2. target object selector filtered by type
3. route preview chip
4. validation hints
5. dependency warning if target inactive/missing

### Supported destination types
- extension
- ring_group
- ivr
- voicemail
- time_condition
- call_routing_policy
- flow
- bridge

## Gateway form grouping
Sections:
- General
- Connection
- Authentication
- Codec & Media
- Advanced
- Runtime preview

## DID form grouping
Sections:
- Identity
- Matching scope
- Routing
- Status
- Preview

## Bridge form grouping
Sections:
- General
- Type
- Gateway target
- Destination template
- Runtime preview

---

# 18. Data Density and Responsiveness

## Desktop
- sidebar expanded by default
- detail pages can use right rail
- tables show full columns

## Laptop
- sidebar can collapse
- summary cards wrap to 2 or 3 per row
- right rail becomes inline card stack

## Tablet
- submenu becomes accordion/flyout
- tables become narrower with fewer columns
- detail tabs horizontally scroll

## Mobile
- not the primary target
- support basic read/edit
- heavy workspaces like flow editor and route explorer should not be prioritized for full mobile parity

---

# 19. Accessibility Rules

- color must not be sole signal for status
- all status chips include text labels
- keyboard navigation across sidebar, tabs, tables, forms
- focus states visible and consistent
- code panels support copy and text selection
- contrast ratio minimum WCAG AA
- row actions accessible from keyboard

---

# 20. Empty State Copy Guidance

## Bridges empty state
"No bridges yet. Create a reusable outbound destination for routes, policies, or fallbacks."

## Flows empty state
"No flows yet. Build programmable routing logic and publish it when ready."

## Gateways empty state
"No gateways configured. Add a carrier or SIP trunk to enable outbound or inbound connectivity."

## DIDs empty state
"No DIDs configured. Add inbound numbers and connect them to routes."

## Route Explorer empty state
"Run a simulation to see how a call resolves across DID matching, policy logic, flows, and bridge selection."

---

# 21. Recommended Visual Prompts for Google Stitch

Use the prompts below when generating screens.

## Prompt: overall style
"Design a modern B2B SaaS admin interface for a communications control platform called NIZAM. Use a clean light theme, restrained blue-indigo brand accents, dense but readable layout, modern typography, subtle borders, low-noise cards, high information clarity, and a professional operations dashboard feel. Avoid legacy PBX aesthetics. Use one-level sidebar submenu navigation and tabbed detail pages."

## Prompt: dashboard
"Create a dashboard for a telecom operations admin product. Left sidebar with submenu sections, top header with search and alerts, summary KPI cards for active calls, registered gateways, queue health, webhook failures, and routing warnings. Below, a two-column operational layout with health status, recent routing changes, warnings, quick actions, and recent calls table. Modern SaaS style, light theme, dense and practical."

## Prompt: DID detail
"Design a DID detail screen for a communications platform. Show page header with DID number, active status, precedence level, effective route chip, and action buttons. Use tabs: Overview, Routing, Source Matching, Activity, Dependencies. Show route preview, source-specific matching, warnings for inactive dependencies, and a clean right-side runtime panel."

## Prompt: gateway detail
"Design a gateway detail page for a SIP trunk/carrier management interface. Show tabs: Overview, Connection, Authentication, Codec & Media, Runtime, XML. Include registration state, transport, realm, host, profile, runtime health badges, generated XML preview panel, and clean admin form sections. Modern SaaS style, light theme, high operational clarity."

## Prompt: bridge detail
"Design a bridge detail page for a telecom routing platform. Explain the bridge as a reusable outbound destination. Include tabs: Overview, Target, Usage, Runtime. Show bridge type, target gateway, destination template, linked objects table, and route/runtime preview."

## Prompt: flow editor
"Design a modern flow editor for programmable call routing. Three-column layout: left node list, center visual graph canvas, right node inspector. Include draft/published state, validation warnings, publish action, and a professional SaaS admin style."

## Prompt: route explorer
"Design a route explorer screen for a telecom platform. Three-column workspace: left inputs for number, caller ID, gateway, registration, and datetime; center vertical route-resolution timeline; right insights panel with warnings and dependencies. Modern observability-inspired SaaS design, light theme, information dense, clean and highly structured."

---

# 22. Recommended Output Set for Visual Generation

Generate these screens first in Google Stitch:
1. App Shell + Sidebar + Header
2. Dashboard Overview
3. DID List
4. DID Detail
5. Gateway List
6. Gateway Detail
7. Bridge List
8. Bridge Detail
9. Flows List
10. Flow Editor
11. Route Explorer
12. Live Calls
13. CDR Detail
14. Routing Overview
15. Connectivity Overview

---

# 23. Final Build Guidance

## Priority order for frontend implementation
1. app shell
2. design system primitives
3. list/detail framework
4. DID screens
5. Gateway screens
6. Bridge screens
7. Routing overview
8. Flows basic editor
9. Calls runtime screens
10. Route explorer

## Non-negotiable UX rules
- no third-level sidebar nesting
- every parent section has an overview page
- all routing surfaces use one consistent destination picker
- every important detail page shows dependencies and runtime state
- routing logic must be visible, not hidden behind jargon
- advanced controls should be progressive

---

# 24. Stitch-ready compressed brief

Use this exact brief when needed:

"Design a modern light-theme SaaS admin UI for NIZAM, a communications control platform and programmable telephony management system. The app has a left sidebar with one-level submenus and top sections: Dashboard, Phone System, Routing, Connectivity, Calls, Contact Center, Integrations, Admin. Visual style should feel like Linear plus Stripe Dashboard, not a legacy PBX. Dense but readable layout, high operational clarity, subtle borders, white surfaces, blue-indigo accents, semantic status colors, professional typography, and clean tables. Key screens include dashboard overview, DID list/detail, gateway list/detail with runtime/XML tabs, bridge list/detail, routing overview, flow editor, route explorer, live calls, and CDR detail. Routing visibility, dependencies, runtime health, and simulation are the product’s signature UX strengths."
