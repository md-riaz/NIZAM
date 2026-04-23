# NIZAM Changelog

All notable changes to the NIZAM project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [2026-04-23 09:25:55 UTC]

### Added
- **Extension Outbound Policy Enforcement**: Added extension-scoped outbound DID and gateway policy storage, resolver logic, and API exposure so outbound identity and route authorization now live on the extension.

### Changed
- **Originate Authorization Path**: Reworked outbound call origination to require extension-allowed DID and gateway combinations instead of accepting direct caller-ID number overrides.
- **Extension Admin API**: Extended extension create/update/list/show payloads with default outbound DID/gateway fields and allowed outbound DID/gateway assignments.

### Fixed
- **Extension Validation Safety**: Preserved bigint `user_id` validation while enforcing current extension numbering rules in create and update flows.
- **Outbound Call Coverage**: Updated targeted unit and API tests to verify latest-only extension outbound policy behavior.

## [2026-04-22]

### Added
- **Starter Extension Provisioning**: New organizations now auto-provision one active starter extension during setup so a fresh account has an immediately usable extension.
- **Phase 1 Extension Ownership Model**: Added device-owned extension support with backend ownership guards and admin UI owner selectors so extensions can now be user-owned, device-owned, or kept unassigned.

### Changed
- **Global Extension Numbering Policy**: Added platform-wide extension range settings (`101`–`500`) in System Settings and enforced that range for manual extension create/edit flows.
- **Extension Naming Language**: Renamed extension business fields from legacy `directory_*` terminology to `first_name` / `last_name` across API, backend domain logic, tests, and admin UI while keeping switch-specific mapping internal.
- **Extension Ownership Visibility**: Updated extension list, detail, and edit/create screens to show explicit owner state and assignment controls instead of treating all extensions as implicitly user-centric.
- **Owner Picker Data Loading**: Extended user and device profile admin endpoints with higher `per_page` support so ownership selectors can load complete option lists in admin flows.

### Fixed
- **Ownership Integrity Guards**: Enforced one personal extension per user, one extension per shared device, and mutual exclusivity between user-owned and device-owned assignment paths.
- **Stable List Ordering**: Added explicit ordering across backend list endpoints so records no longer jump position after edits; most admin/API lists now use newest-first ordering by ID, while DIDs and extensions keep semantic ordering by number.
- **Provider Password Cross-Check**: Updated the numbers provider editor to keep saved passwords masked by default while allowing a temporary reveal toggle for verification during edits.
- **DID Destination Hydration**: Fixed the numbers edit form so saved `ring_group` destinations hydrate with the correct destination type and selected ring group instead of falling back to extension.
- **Superadmin Role Hydration**: Fixed platform user editing so persisted `superadmin` users show the correct role in the role select instead of being coerced to agent.
- **Extension Admin Terminology**: Reworked extension admin screens to show business-friendly labels like `Name`, `First name`, and `Last name` instead of exposing FreeSWITCH-era directory terminology.

## [2026-04-21]

### Fixed
- **DID Edit Form Stability**: Fixed Radix Select hydration regressions on the number edit page so saved destination type no longer flips to the wrong value during load and provider details stay populated after opening an existing number.
- **Numbers List Provider Status**: Added live provider runtime status to the numbers list by surfacing SIP gateway registration state alongside each number's linked provider.
- **E.164 Number Entry**: Reworked DID number entry into a country-code selector plus telephone input while preserving compatibility with existing local-format numbers stored in the backend.
- **User Edit Organization Scope**: Fixed the users edit form so role and organization scope selects reliably show the persisted value instead of falling back to placeholders during load.
- **Admin Form Select Hydration**: Patched the same empty-value Select hydration bug across agent, queue, ring group, team, organization, and queue member admin forms to prevent saved dropdown selections from being cleared on edit pages.

## [2026-04-11]

### Added
- **Platform Capabilities Dashboard**: Added a dedicated superadmin page at `/admin/capabilities` that surfaces advanced PBX enhancements (FusionPBX parity, multi-registration, optimized directory) and their live status.
- **Deployment Automation**: Automated FreeSWITCH NAT detection and RTP range configuration for both Docker and VPS environments.
- **SIP Configuration**: Added `switch.conf.xml` template to allow dynamic RTP port range adjustment via environment variables.

### Changed
- **Self-Call Parity**: Updated self-call behavior to match FusionPBX; calling your own extension now correctly answers and enters the voicemail management (check) menu instead of attempting a redundant local bridge.
- **SIP Credentials UI**: Added "Domain / Realm" to the SIP Credentials display on the extension detail page and included it in the "Copy credentials" action.
- **SIP Configuration**: Updated the extension SIP configuration endpoint to return the current application host for the SIP server instead of the tenant domain, improving compatibility with softphones registering from external networks.
- **SIP Port Mapping**: Refactored `WebRtcConfigService` to prioritize SIP Profile database settings while allowing optional environment variable overrides (`FREESWITCH_SIP_PORT`, `FREESWITCH_WSS_PORT`) for Docker port-mapped environments.
- **WebRTC Transport Decoupling**: Refactored `SipProfileController` and `WebRtcConfigService` to independently manage WS and WSS transports. Enabling plain WS no longer requires SSL certificates or triggers WSS/TLS configuration in FreeSWITCH.
- **SIP Profile Automation**: Updated `SipProfileSetting` runtime hooks to trigger `reloadxml` plus a profile-specific Sofia restart when SIP transport settings change.
- **VPS Installer**: Defaulted to `auto-nat` for external IP detection, ensuring robustness against VPS IP changes.
- **Seeding:** Updated `DatabaseSeeder` to use the `ADMIN_EMAIL`, `ADMIN_NAME`, and `ADMIN_PASSWORD` from `.env` consistently across all seeded admin records.

### Fixed
- **Runtime Configuration**: Refined the `reloadProfile` logic in `GatewayProvisioningService` to ensure dynamic gateway changes are applied correctly at runtime.
- **Database Seeding**: Resolved a unique constraint conflict where the platform admin and tenant admin were assigned the same email address. The platform admin now uses a `system@` prefix.
- **Test Isolation**: Isolated FreeSWITCH gateway provisioning during tests. Tests now write XML profiles to a temporary directory (`storage/framework/testing/gateways`) instead of the real configuration, preventing "orphan" registrations and "fail wait" loops in development.
- **Gateway Sync**: Corrected the configuration key used in `GatewayProvisioningServiceTest` and `GatewayCodecRenderingTest` to correctly redirect filesystem output during unit tests.
- **Registration Visibility**: Switched active registration lookups to `sofia xmlstatus profile <profile> reg` so the SIP status APIs now expose the real extension user agent and preserve correct profile names like `internal` and `external`.
- **FusionPBX Registration Parity**: Added a shared `SipRegistrationService` that centralizes FreeSWITCH registration parsing and applies FusionPBX-style normalization, including LAN IP derivation, `expsecs(...)` expiry parsing, and cleaner registration status output across the SIP status APIs.
- **Self-Call Routing**: Internal self-extension calls now bypass the external delivery orchestrator and bridge directly with FusionPBX-style `user/<extension>@<domain>` routing, while non-self extension calls continue to use orchestrated delivery for push-notification wake-up scenarios.
- **Directory Lookups**: XML-CURL directory responses now honor specific `user` and `id` lookups so SIP authentication and registration requests do not fetch the entire tenant directory unnecessarily.
- **SIP Profile Includes**: Stopped emitting gateway include directives for the `internal` Sofia profile; only the `external` profile now owns gateway include trees, eliminating the `No files to include .../sip_profiles/internal/*.xml` warning.
- **Local Dev NAT Compatibility**: Enabled `aggressive-nat-detection` on the seeded internal SIP profile for better softphone behavior in local Docker environments.
- **SIP Profile Setting Hooks**: Fixed the `SipProfileSetting` model event hook to use the saved/deleted model instance correctly instead of referencing `$this` from static context.

---

## [2026-04-09]

### Changed
- **Frontend Routing:** Migrated the app from `<BrowserRouter>` to the data router `<RouterProvider>` (`createBrowserRouter`) to properly support React Router v6.4+ features like `useBlocker` without runtime crashes.
- **SIP Profile Form:** Separated WebRTC transport enablement into independent WS and WSS toggles, allowing WS to be enabled without forcing WSS/TLS settings (useful when proxying WebRTC via NGINX).
- **Extension Details:** Replaced the WebRTC configuration card with a generic "SIP Credentials" card that displays the SIP Server, TLS Server (if applicable), Transport options, Username, and Password. WebRTC status is now shown as an indicator badge.
- **Codec Resolution:** `BridgeCompiler` now accepts and forwards the real A-leg endpoint type (`sip` or `webrtc`) to `CodecResolutionService` instead of hardcoding `'sip'`. WebRTC calls now correctly resolve Opus-first codec defaults and honour `web_only` transcoding policies during bridge compilation.
- **Dialplan Compiler:** Added `inferEndpointType()` which detects WebRTC calls from the FreeSWITCH XML-CURL payload (`variable_sip_via_protocol=wss` or `variable_sip_transport=wss`) and threads the result through all bridge compilation paths (`compileDidExtension`, `compileAntiAction`, `compileDestinationAction`).
- **Call Session:** `FreeswitchXmlController` now persists the inferred `endpoint_type` into `CallSession->variables` on both the compiled-manifest and interpreted-fallback paths, making the A-leg transport type available for tracing, analytics, and downstream logic.

### Fixed
- **Form Validation:** Improved the `getErrorMessage` utility to parse Laravel's `errors` validation bag and display nested field errors as a readable list instead of a cryptic summary string.
- **Form Validation:** Added pre-submit frontend validation to the SIP Profile dynamic settings table to prevent empty setting rows from being sent to the API.
- **UI UX:** Added a `required` prop to the reusable `FormLabel` component to render a red asterisk `*`, and applied it to mandatory fields in the SIP Profile editor.
- **UI UX:** Empty required fields in the SIP Profile settings table now highlight with a red border if left blank after a save attempt.
- **Test Safety:** Hardened Laravel test bootstrap so `php artisan test` always uses in-memory SQLite and aborts immediately if the test environment ever tries to boot against a non-SQLite connection, protecting the Docker Postgres users table from accidental wipes.

---

## [2026-04-09] (Initial Infrastructure & Features)

### Added
#### Infrastructure
- Docker Compose baseline with 6 services: app, nginx, postgres, redis, freeswitch, queue worker.
- `GET /api/v1/health` endpoint reporting system status.
#### Switch Integration
- XML directory and dialplan endpoints for FreeSWITCH.
- ESL listener service with automatic reconnection.
#### Core Telephony
- Multi-tenant Extension, DID, Ring Group, IVR, and Time Condition models.
#### Security & Permissions
- Sanctum token auth and granular role-based permissions.
- Audit log system for tracking all model changes.
#### API & Features
- System Media management with audio prompt indexing.
- Real-time registration status queries for extensions and gateways.

---

## [2026-03-01]

### Summary
NIZAM v1.0.0 stable release. Established frozen API contract and operational readiness for multi-tenant deployments.

### Added
- MIT License and contributor governance.
- Full architectural documentation and API reference.
- PHP SDK for external integrations.
- Multi-tenant routing and isolation enforcement.
