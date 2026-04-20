# NIZAM Google Stitch Full Prompt Pack

This file is the **expanded Stitch prompt pack** for NIZAM.
Use it instead of the earlier smaller pack when you want broad coverage of the full app.

---

## Global design system prompt

Design a modern light-theme SaaS admin interface for **NIZAM**, a communications control platform and programmable telephony management system. The UI should feel like Linear plus Stripe Dashboard plus modern observability tooling, not a legacy PBX. Use a clean white and soft-gray base, restrained blue-indigo brand accents, semantic colors for status, compact professional tables, subtle borders, soft shadows, strong typography hierarchy, and dense but readable operator-focused layouts.

### Design rules
- one-level sidebar submenu navigation only
- parent sidebar items are clickable overview pages
- detail pages use tabs
- complex routing and runtime pages may use a three-column workspace
- routing visibility, runtime visibility, dependency visibility, and simulation are top priorities
- use status chips, route chips, warning banners, right-side runtime rails, and structured detail summaries
- avoid noisy dashboards, neon visuals, telecom cliches, and old-school PBX form walls

### Primary sections
- Dashboard
- Phone System
- Routing
- Connectivity
- Calls
- Contact Center
- Integrations
- Admin

---

## Prompt Pack A: App shell and auth

### Prompt A1: App shell
Create the full application shell for NIZAM. Include a left sidebar with one-level submenu navigation, organization switcher, environment chip support, and top sections Dashboard, Phone System, Routing, Connectivity, Calls, Contact Center, Integrations, and Admin. Add a top header with breadcrumbs, global search, alerts, quick actions, organization context, and profile menu. Use a modern light-theme B2B operations design with strong typography, subtle cards, dense layout, and blue-indigo accents.

### Prompt A2: Login page
Design a login screen for NIZAM. Use a clean enterprise SaaS auth layout with logo, title, email field, password field, remember me, forgot password link, sign-in button, and minimal supporting copy. Include optional environment badge and version footer. Light theme, clean, calm, modern.

### Prompt A3: Forgot password and reset flow
Design two auth screens for NIZAM: Forgot Password and Reset Password. The Forgot Password screen asks for email and sends a reset link. The Reset Password screen includes email, new password, confirm password, and primary reset action. Keep styling consistent with the login screen and the main app design system.

### Prompt A4: First admin / first organization setup
Design a first-run bootstrap screen for NIZAM. This page collects admin name, admin email, password, organization name, and organization domain or slug. The layout should feel like onboarding for a serious SaaS control plane, not a consumer app.

### Prompt A5: Welcome / getting started page
Design a post-login getting started page for NIZAM with cards for Create Extension, Add DID, Add Gateway, Create Bridge, Create Flow, and Open Route Explorer. Include a progress checklist and links to documentation.

---

## Prompt Pack B: Dashboard and overview pages

### Prompt B1: Dashboard overview
Design the NIZAM Dashboard overview page. Show summary cards for Active Calls, Registered Gateways, Queue Load, Failed Webhooks, and Routing Warnings. Below that, show System Health, Recent Routing Changes, Alerts and Warnings, Quick Actions, Recent Calls, and Recent Events. Make it feel like a high-signal operations dashboard in a modern SaaS platform.

### Prompt B2: Phone System overview
Design a Phone System overview page for NIZAM. Show total extensions, active DIDs, ring groups, IVRs, time conditions, schedules, and warnings for unassigned or inactive routing objects. Include quick links to create common objects.

### Prompt B3: Routing overview
Design a Routing overview page for NIZAM. Show cards for total flows, published flows, policies, bridges in use, and routing warnings. Add sections for Draft vs Published, recently changed routing objects, and unresolved dependency warnings.

### Prompt B4: Connectivity overview
Design a Connectivity overview page for NIZAM. Show cards for gateways up, gateways down, profile health, NAT/media warnings, and last reconcile status. Include a Gateway Runtime table and an XML Drift / Runtime Hygiene card with DB count, XML count, orphan XML count, and Reconcile action.

### Prompt B5: Calls overview
Design a Calls overview page for NIZAM. Show cards for active calls, answered today, missed today, failed calls, and recordings today. Include hangup-cause summary, recent traces, live calls summary, and recent recordings.

### Prompt B6: Contact Center overview
Design a Contact Center overview page for NIZAM. Show queue load, waiting callers, available agents, service level, abandon rate, and longest wait. Include wallboard-style status blocks but keep it modern and clean.

### Prompt B7: Admin overview
Design an Admin overview page for NIZAM. Show active users, organization count, recent permission changes, audit volume, usage summary, and platform warnings. This page should feel like an operations control page for privileged users.

---

## Prompt Pack C: Phone System pages

### Prompt C1: Extensions list and detail
Design two screens for NIZAM: Extensions List and Extension Detail. The list page should have filters and a dense table with extension, display name, caller ID, device profile, status, and last activity. The detail page should have a summary strip and tabs: Overview, Devices, Routing, Activity, Dependencies.

### Prompt C2: DID list and detail
Design two screens for NIZAM: DID List and DID Detail. The DID Detail screen must clearly answer where this number goes. Include summary strip with number, active status, precedence chip, effective route chip, and actions. Use tabs: Overview, Routing, Source Matching, Activity, Dependencies. Show route preview, gateway-specific source matching, registration-specific matching, and dependency warnings.

### Prompt C3: Ring Group detail
Design a Ring Group detail page for NIZAM. Include tabs for Overview, Members, Routing, Runtime, and Dependencies. Show strategy, member count, timeout, cause-aware fallback destination, and warning state when no active members exist.

### Prompt C4: IVR detail
Design an IVR detail page for NIZAM. Use tabs: Overview, Menu Options, Prompts, Routing, Dependencies. Show digit mapping table, timeout destination, invalid destination, prompt status, and route chips.

### Prompt C5: Time Condition detail
Design a Time Condition detail page for NIZAM. Use tabs: Overview, Schedule, Routing, Preview, Dependencies. Show current match state, next state change, test datetime picker, and route preview.

### Prompt C6: Schedule and Holiday Calendar pages
Design Schedule Detail and Holiday Calendar Detail pages for NIZAM. Schedule tabs should include Overview, Rules, Holidays, Preview, Dependencies. Holiday Calendar should show entries and linked schedules.

### Prompt C7: Device Profile detail
Design a Device Profile detail page for NIZAM. Include Overview, SIP Settings, Codec, and Linked Devices. Keep it compact and configuration-oriented.

---

## Prompt Pack D: Routing pages

### Prompt D1: Policy detail
Design a Call Routing Policy detail page for NIZAM. Use tabs Overview, Conditions, Routing, Preview, Dependencies. The Conditions tab should have a modern rule builder. The Routing tab should have match and no-match destination pickers and a route preview card.

### Prompt D2: Flows list and detail
Design Flows List and Flow Detail pages for NIZAM. The list shows draft/published state, node count, used-by count, and last published. The detail page includes tabs Overview, Editor, Publish, Dependencies, Versions.

### Prompt D3: Flow editor workspace
Design the NIZAM Flow Editor. Use a three-column workspace with node list on the left, visual graph/block canvas in the center, and selected node inspector on the right. Show draft/published state, compile status, validation warnings, and publish action.

### Prompt D4: Bridges list and detail
Design Bridges List and Bridge Detail pages for NIZAM. Explain bridge as a reusable outbound destination. Include tabs Overview, Target, Usage, Runtime. Show bridge type, target gateway, destination template, used-by table, and runtime preview.

### Prompt D5: Route Explorer
Design a flagship Route Explorer for NIZAM. Use a three-column workspace: left input form, center route-resolution timeline, right warnings and dependency rail. Inputs include number, caller ID, gateway, registration, organization, and datetime. Output should show DID match, precedence selection, policy evaluation, flow traversal, bridge selection, and final destination.

### Prompt D6: Simulations workspace
Design a Simulations page for NIZAM with tabs for DID Simulation, Policy Simulation, Flow Simulation, and Time Preview. Each tab should use left inputs, center results, and right notes/warnings.

### Prompt D7: Published Dialplan viewer
Design a Published Dialplan screen for NIZAM. Show latest publish state, generated timestamp, manifest tree, linked source objects, and compiled XML preview in a code panel.

---

## Prompt Pack E: Connectivity pages

### Prompt E1: Gateway list and detail
Design Gateway List and Gateway Detail pages for NIZAM. The detail page must have tabs: Overview, Connection, Authentication, Codec & Media, Runtime, XML. Show registration state, transport, realm, proxy, profile, runtime command results, last reconcile result, and XML preview.

### Prompt E2: Registrations runtime page
Design a Registrations runtime page for NIZAM. Show a compact table with gateway, state, status, username, realm, proxy, uptime/last seen, and actions like Refresh, KillGW, StartGW, and Reconcile.

### Prompt E3: SIP Profiles page
Design SIP Profiles pages for NIZAM with admin-oriented tabs: Overview, Bindings, Codec Defaults, NAT, Runtime. Keep it serious and technical.

### Prompt E4: NAT / Media settings page
Design a NAT / Media settings page for NIZAM. Show cards and settings summaries for SIP IP, RTP IP, external SIP/RTP IP, local network ACL, and NAT warnings. Include operational notes and docs links.

### Prompt E5: Blocked Destinations page
Design a Blocked Destinations admin page for NIZAM. Show table columns for blocked pattern or destination, reason, status, and actions. Include create/edit flows in a consistent admin style.

### Prompt E6: SSL / Certificates admin page
Design an SSL / Certificates management page for NIZAM. Include Overview, Domains, Status, Request/Renew, and History sections. This should feel like platform infrastructure management inside the app.

---

## Prompt Pack F: Calls and records pages

### Prompt F1: Live Calls page
Design a Live Calls page for NIZAM. Show a dense operations table with columns call UUID, direction, from, to, state, duration, gateway, route, and actions. Row click opens a right-side detail drawer with event timeline, route summary, and call controls such as transfer, hold, unhold, recording, and hangup.

### Prompt F2: Call Session detail
Design a Call Session detail page for NIZAM with tabs Overview, Legs, Routing, Events, Analysis. Show route summary, media state, linked recording, and runtime events.

### Prompt F3: CDR list and detail
Design CDR List and CDR Detail pages for NIZAM. The detail page should include tabs Overview, Legs, Routing, Recording, Events. The Routing tab should show gateway, bridge, flow path, and hangup cause.

### Prompt F4: Recordings page
Design a Recordings list and detail view for NIZAM. Include playback, download, metadata, linked CDR, retention state, and delete actions.

### Prompt F5: Event Log page
Design an Event Log page for NIZAM. Show timestamp, event type, call UUID, organization, payload preview, and actions. Event detail should show structured summary plus raw JSON.

### Prompt F6: Trace Viewer
Design a Trace Viewer for NIZAM as a forensic call timeline. Show call created, DID resolved, policy matched, flow traversed, bridge selected, queue/agent actions, hangup cause, and recording link. Include right-rail warnings and export action.

### Prompt F7: Calls analytics pages
Design a Calls Analytics section for NIZAM with pages for Summary, Volume, Quality, and Destinations. Use modern charts and KPI cards, not dashboard clutter.

---

## Prompt Pack G: Contact Center pages

### Prompt G1: Queue list and detail
Design Queue List and Queue Detail pages for NIZAM. Queue Detail should include tabs Overview, Members, Routing, Metrics, Events. Show waiting callers, longest wait, SLA, and overflow/fallback route.

### Prompt G2: Agent list and detail
Design Agent List and Agent Detail pages for NIZAM. Agent Detail should include Overview, Queues, Activity, and Performance.

### Prompt G3: Wallboard
Design a full-screen but clean Wallboard for NIZAM with queue KPI cards, agent status board, waiting counts, and alert banners.

### Prompt G4: Queue metrics and codec metrics
Design Queue Metrics and Codec Metrics pages for NIZAM. Show modern charts, compact filters, and operational summaries.

---

## Prompt Pack H: Integrations and admin pages

### Prompt H1: Webhooks list and detail
Design Webhooks List and Webhook Detail pages for NIZAM. Detail tabs: Overview, Events, Deliveries, Retry History, Stats. Show event types, destination URL, status, delivery metrics, and retry history.

### Prompt H2: API tokens pages
Design API Tokens pages for NIZAM. Show token name, scopes, created by, last used, expires, revoke action, and create-token flow.

### Prompt H3: Event Streams page
Design an Event Streams overview page for NIZAM. Show stream type, auth mode, client name, status, and last activity.

### Prompt H4: Users and permissions pages
Design Users List, User Detail, and Roles & Permissions pages for NIZAM. User Detail should include tabs Overview, Access, Permissions, Activity, Tokens. Role pages should include a permission matrix and assigned users.

### Prompt H5: Organizations and organization settings pages
Design Organizations List, Organization Detail, and Organization Settings pages for NIZAM. Organization Settings should include General, Branding, Telephony Defaults, Security, Usage, and Advanced.

### Prompt H6: Usage summary page
Design a Usage page for NIZAM showing organization usage summary, charts, collect/reconcile actions, and breakdown by call volume, storage, and API usage.

### Prompt H7: Audit logs pages
Design Audit Log List and Audit Log Detail pages for NIZAM. Show actor, object, action, time, organization, and diff preview. Detail should show before/after JSON and linked objects.

### Prompt H8: Admin dashboard and system settings
Design an Admin Dashboard and System Settings page for NIZAM. Include platform health, recent permission changes, usage summary, SSL, SIP profiles, blocked destinations, and system warnings.

---

## Prompt Pack I: State pages

### Prompt I1: Empty states pack
Design a set of empty states for NIZAM: no extensions, no DIDs, no gateways, no bridges, no flows, no queues, no webhooks, no recordings. Keep them useful, clear, and action-oriented.

### Prompt I2: Error states pack
Design state pages for 403, 404, 429, and 500 for NIZAM. Keep them consistent with the product design system.

### Prompt I3: Runtime unavailable / reconnecting state
Design a state for live runtime pages when connection is stale, reconnecting, or unavailable. Include retry status, stale timestamp, and fallback guidance.

### Prompt I4: Unsaved changes / publish conflict state
Design UI states for unsaved changes, draft vs published conflict, and publish confirmation in NIZAM.

---

## Design token summary prompt for Stitch

Use a light theme with white and soft-gray surfaces, neutral slate text, blue-indigo brand accents, green/amber/red semantic states, subtle shadows, soft radius, compact table rows, strong typography hierarchy, pill status chips, route chips with distinct but restrained category accents, and clean tabbed detail pages. Use one-level sidebar submenus and avoid visual clutter.

---

## Short master prompt

Design a complete modern light-theme SaaS admin UI for NIZAM, a communications control platform and programmable telephony management system. Use a structured B2B control-plane style inspired by Linear, Stripe Dashboard, and observability products. Include auth screens, dashboard overviews, phone system pages, routing workspaces, connectivity runtime pages, calls and trace pages, contact center pages, integrations pages, and admin control pages. Use one-level sidebar submenu navigation, tabbed detail pages, compact professional tables, route chips, status chips, warning rails, XML/runtime panels, and a clear operational hierarchy. Prioritize routing visibility, runtime visibility, dependency visibility, and simulation/debugging clarity.

---

## Best generation order
1. App shell + auth
2. Dashboard + overviews
3. DID detail + gateway detail + bridge detail
4. Flow editor + Route Explorer
5. Live Calls + CDR detail + Trace Viewer
6. Queue + Agent + Wallboard
7. Webhooks + Users + Audit Logs + Organization Settings
8. State pages pack
