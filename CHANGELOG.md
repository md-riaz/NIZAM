# NIZAM Changelog

All notable changes to the NIZAM project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [2026-04-11]

### Added
- **Deployment Automation**: Automated FreeSWITCH NAT detection and RTP range configuration for both Docker and VPS environments.
- **SIP Configuration**: Added `switch.conf.xml` template to allow dynamic RTP port range adjustment via environment variables.

### Changed
- **SIP Credentials UI**: Added "Domain / Realm" to the SIP Credentials display on the extension detail page and included it in the "Copy credentials" action.
- **SIP Configuration**: Updated the extension SIP configuration endpoint to return the current application host for the SIP server instead of the tenant domain, improving compatibility with softphones registering from external networks.
- **VPS Installer**: Defaulted to `auto-nat` for external IP detection, ensuring robustness against VPS IP changes.
- **Seeding:** Updated `DatabaseSeeder` to use the `ADMIN_EMAIL`, `ADMIN_NAME`, and `ADMIN_PASSWORD` from `.env` consistently across all seeded admin records.

### Fixed
- **Runtime Configuration**: Refined the `reloadProfile` logic in `GatewayProvisioningService` to ensure dynamic gateway changes are applied correctly at runtime.
- **Database Seeding**: Resolved a unique constraint conflict where the platform admin and tenant admin were assigned the same email address. The platform admin now uses a `system@` prefix.
- **Test Isolation**: Isolated FreeSWITCH gateway provisioning during tests. Tests now write XML profiles to a temporary directory (`storage/framework/testing/gateways`) instead of the real configuration, preventing "orphan" registrations and "fail wait" loops in development.
- **Gateway Sync**: Corrected the configuration key used in `GatewayProvisioningServiceTest` and `GatewayCodecRenderingTest` to correctly redirect filesystem output during unit tests.

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
