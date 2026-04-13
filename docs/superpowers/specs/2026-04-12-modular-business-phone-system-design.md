# Modular Business Phone System Design

Date: 2026-04-12
Status: Draft approved for implementation planning

## 1. Purpose

Design a modern, modular business phone system built on a programmable telephony core.

The system should not behave like a traditional PBX admin panel centered on raw telephony objects. Instead, it should model real business entities and compile business intent into reliable telephony behavior.

This design targets a product that is:
- organization/domain-centric
- user and device aware
- softphone/WebRTC-first
- graph-routed with business-friendly presets
- modular like a modern communications platform
- reliable in production without over-distributing call-critical logic
- local-first for media storage with optional third-party integration
- named around stable domain concepts rather than product branding

---

## 2. Product Posture

The first serious product posture is a combination of:

1. **Business phone system first**
   - polished organization, user, extension, device, and routing experience
   - approachable for business admins
   - focused on practical business telephony needs before expanding into broader communications suites

2. **Programmable communications core first**
   - a strong underlying control plane and routing runtime
   - extensible enough to support future messaging, automation, AI, and custom communications logic

This means the platform should ship as a modern business phone system, while its internal architecture remains capable of powering more advanced programmable communications use cases later.

---

## 3. Design Principles

### 3.1 Business-first model
Use business entities as the primary authoring model:
- organizations
- users
- devices
- schedules
- routing intent

Telephony artifacts such as directory XML, dialplan, SIP profiles, or bridge strings are runtime outputs, not primary admin-facing concepts.

### 3.2 Domain is the tenant boundary
The unique **domain** is the sole organization context identifier.

There should be no separate slug-based tenant identity in the core model.

Domain should drive:
- organization isolation
- authentication context resolution
- SIP realm and telephony context binding
- XML compilation scope
- policy and routing boundaries

### 3.3 Compile, do not improvise
Authoring objects must be validated and compiled before becoming active runtime behavior.

Key runtime behaviors should come from published, validated artifacts rather than mutable draft state.

### 3.4 Modularity without call-path fragility
The system should be modular at the platform boundary, but call-critical logic must remain local, deterministic, and contract-driven.

External systems should not sit in the hot path for routing decisions unless explicitly designed as safe adapters.

### 3.5 Domain-stable naming
Core architecture, services, modules, and interfaces should use domain and business language rather than product-name-heavy naming.

This keeps the system maintainable and resilient to future rebranding.

### 3.6 Softphone/WebRTC-first
The primary endpoint model should assume:
- MicroSIP-like desktop softphones
- browser WebRTC endpoints
- mobile SIP/WebRTC clients

Desk-phone provisioning should remain supported as an optional capability, not a central product pillar.

### 3.7 Local-first media
Recordings, voicemail, prompts, and related media should be stored locally by default, with optional export/offload adapters later.

---

## 4. High-Level Architecture

The architecture is split into three layers.

### 4.1 Core domain and telephony control plane
This layer owns the canonical business and routing model.

Responsibilities:
- organization/domain context
- user identity and role model
- extension and device relationships
- schedule and holiday policy resolution
- routing graph compilation
- presence aggregation
- publication/version management
- telephony runtime artifact generation
- call-critical reliability controls

This layer is the stable center of the system.

### 4.2 Capability modules
Capability modules add product features without redefining the core model.

Examples:
- voicemail
- recordings
- contact center
- messaging
- analytics
- AI/transcription
- provisioning
- billing/integration packages

These modules operate through defined contracts, hooks, APIs, and events.

### 4.3 Infrastructure adapters
Adapters connect the platform to underlying runtime systems and optional services.

Examples:
- telephony adapter for FreeSWITCH
- WebRTC transport support
- push notification adapter
- local storage driver
- third-party storage driver
- CRM/provider integrations

Adapters should not redefine core domain logic. They implement infrastructure concerns behind stable interfaces.

---

## 5. Core Domain Model

## 5.1 Organization

Represents a business account and the primary isolation boundary.

Properties:
- unique domain
- display name
- locale/timezone defaults
- organization-wide business hours
- organization-wide holiday calendar
- storage, media, and security defaults
- usage limits (users, DIDs, calls, storage)

Notes:
- domain is the canonical identity key
- all tenant resolution and runtime scoping must derive from domain

## 5.2 User

Represents a real human identity inside an organization.

Properties:
- organization association
- name and contact identity
- authentication identity
- role(s)
- timezone/language preferences
- DND and personal availability preferences
- optional follow-me or personal routing preferences

User is the primary real-world actor in the system.

## 5.3 Extension

Represents a virtual telephony identity.

An extension is not the person; it is the telephony object that maps business identity into runtime telephony behavior.

Properties:
- organization association
- extension number or addressable identity
- SIP/runtime credentials
- caller ID behavior
- routing entry behavior
- mapping to a user, service target, or system target

Extension supports both:
- human user mapping
- non-human/service identities (queues, IVRs, service endpoints, virtual targets)

## 5.4 Device

Represents a reachable endpoint mapped to an extension.

Properties:
- extension association
- organization association
- endpoint type: `softphone`, `webrtc`, `mobile`, optional `hardware`
- registration metadata
- user agent / reachability metadata
- push/session metadata where relevant

Primary product focus:
- softphone
- WebRTC
- mobile-style SIP/WebRTC endpoints

Hardware devices remain optional and modular.

## 5.5 Routing Graph

Represents a versioned, validated call-routing definition.

Used for:
- inbound DID entrypoints
- organization-level call handling
- department/team routing
- user-level or extension-level routing
- business-hours and holiday branching
- advanced fallback logic

Graphs are the canonical advanced routing authoring format.

## 5.6 Schedule Policy

Represents reusable time-based routing policy.

Supports:
- organization-level business hours by default
- holiday calendars
- inherited rules
- optional overrides for departments, users, or routing contexts

Default philosophy:
- organization-level schedule first
- override only where necessary

## 5.7 Presence Aggregate

Represents the effective availability state used for routing.

This is not a single raw source. It is a merged decision built from:
- user status
- device registration and reachability
- active call state
- organization schedule
- user or department overrides
- optional role/queue participation state

Presence used for routing should be an interpreted, platform-level state rather than just raw SIP registration.

---

## 6. Admin and Product Model

The system should expose a **dual-view** product model.

### 6.1 Business/admin view
For most administrators, management should center around:
- users
- teams/departments
- devices
- numbers
- schedules
- business routing outcomes

This avoids making non-telecom admins think primarily in raw extension objects.

### 6.2 Telecom/power-admin view
Advanced operators should still be able to manage:
- extensions
- DIDs
- SIP profiles/gateways
- routing graphs
- detailed call policies
- runtime diagnostics

This preserves operator power without making it the default experience.

---

## 7. Routing Model

## 7.1 Hybrid routing authoring
Routing should follow a **hybrid model**:
- simple intent-driven presets for common cases
- graph mode for advanced/custom cases

Presets should not create a separate execution system.
They should compile into the same underlying routing graph and runtime engine.

Examples of presets:
- business-hours receptionist flow
- sales/support split
- department routing
- holiday mode
- after-hours voicemail or on-call forwarding

## 7.2 Entrypoints
Every inbound DID should resolve to an entrypoint.

Entrypoints may target:
- preset business flows
- routing graphs
- queues
- user/extension routing
- special campaign or department logic

## 7.3 Routing evaluation model
Routing should behave like business decisioning, not just telephony branching.

A routing decision may consider:
- organization open/closed state
- holiday state
- DID-specific policy
- eligible users or departments
- user availability
- reachable devices
- queue or department strategy
- deterministic fallback path

## 7.4 Reliability rule
Graphs should be validated before publish and compiled into deterministic runtime artifacts.

Runtime should not depend on draft objects or ad hoc graph interpretation that can fail unpredictably under load.

---

## 8. Business Hours and Holidays

### 8.1 Default model
Organization-level hours and holidays are the default source of truth.

This avoids schedule drift and supports consistent company-wide behavior.

### 8.2 Overrides
The system should support optional overrides for:
- departments
- specific flows
- specific users
- specific service targets

Override behavior should be explicit through inheritance rules, not implicit duplication.

### 8.3 Policy engine role
Schedules and holidays should be handled by a reusable policy engine that any routing node or routing preset can query.

---

## 9. Presence and Availability Model

The platform should use a **merged availability model**.

Presence decisions should combine:
- user-declared status (`available`, `busy`, `dnd`, `ooo`, etc.)
- live call state
- device registration and reachability
- schedule windows
- optional queue/role participation state

This enables routing decisions that reflect real business availability rather than only SIP-level status.

### 9.1 Presence aggregator
A dedicated presence aggregation component should continuously reconcile relevant sources and produce an actionable routing state.

### 9.2 Routing safety
Presence freshness may be event-driven and eventually current, but routing decisions must remain safe if one source becomes stale or delayed.

Example:
- if live device reachability is temporarily stale, routing still needs deterministic fallback behavior

---

## 10. Modular Architecture Strategy

The recommended strategy is:

**API-first modular core + pluggable core runtime**

### 10.1 Why this approach
A pure everything-is-a-service architecture is not ideal for call-critical routing because it can introduce distributed-system failure modes into the hot path.

Instead:
- call-critical logic remains inside a strong core runtime
- feature modules extend the platform through contracts and events
- integrations consume APIs or event streams rather than altering the hot path arbitrarily

### 10.2 Module categories

#### Core domain modules
- Organization
- Identity/User
- Extension
- Device
- Presence
- Routing
- Schedule/Holiday
- Telephony runtime integration

#### Capability modules
- Voicemail
- Recording
- Contact Center
- Messaging
- AI/Transcription
- Provisioning
- Analytics
- Billing

#### Adapter modules
- FreeSWITCH adapter
- WebRTC support adapter
- Push notification adapter
- Local storage driver
- Third-party storage driver
- External provider integrations

### 10.3 Module extension rules
Modules may extend behavior only through stable interfaces and declared extension points.

No module should be able to silently break core routing or core telephony behavior.

---

## 11. Event and Hook Model

A module registry and hook system should allow controlled extensibility.

Possible lifecycle hooks include:
- on call start
- on call answer
- on call end
- on routing compile
- on routing publish
- on media stored
- on voicemail received
- on presence changed

Rules:
- hook contracts must be explicit
- call-critical hooks must be bounded and safe
- non-critical work should run asynchronously where possible
- module failure must degrade gracefully without taking down the core runtime

---

## 12. Telephony Runtime Model

The initial telephony runtime should continue to use a FreeSWITCH adapter model.

Responsibilities of the telephony adapter:
- directory/runtime identity rendering
- dialplan or compiled routing artifact generation
- SIP profile and gateway orchestration
- event ingestion and normalization
- runtime command execution where appropriate

The telephony adapter should be infrastructure-facing, not the source of business truth.

Business truth lives in the domain model and compiled/published artifacts.

---

## 13. Media and Storage Model

### 13.1 Default approach
Media should be stored locally by default.

Media includes:
- call recordings
- voicemails
- prompts/greetings
- generated/transformed media assets

### 13.2 Metadata tracking
Every media file should be indexed with metadata such as:
- organization/domain
- user
- extension
- call/session identifier
- creation time
- retention state
- storage backend

### 13.3 Storage abstraction
The platform should define a storage driver interface.

Initial drivers:
- LocalFileSystemDriver

Optional future drivers:
- S3-compatible storage driver
- other object storage adapters

### 13.4 Product posture
Local-first storage should be treated as the primary operational model.
Third-party storage is optional and should not be required for core operation.

---

## 14. Endpoint Strategy

### 14.1 First-class endpoint types
The first serious platform experience should prioritize:
- desktop softphones
- browser WebRTC endpoints
- mobile SIP/WebRTC endpoints

### 14.2 Push and reachability
Mobile-style reachability should be supported through push/session-aware mechanisms where possible.

### 14.3 Hardware provisioning posture
Desk-phone provisioning should remain available as an optional module, but not be a central architectural driver.

This avoids over-optimizing the platform around shrinking hardware-heavy deployment patterns.

---

## 15. Reliability Requirements

The following should be treated as non-negotiable architecture principles.

### 15.1 Validate before publish
Routing graphs, schedules, and related policies must be validated before publication.

### 15.2 Version everything important
Version at least:
- routing graphs
- schedules
- holiday calendars
- media bindings/prompts
- organization policy sets

### 15.3 Separate authoring from publishing
Draft editing and active runtime state must be separate.

Publishing should:
- validate artifacts
- create immutable active versions
- support rollback to last known-good version

### 15.4 Deterministic fallback
All critical routing paths need explicit fallback behavior.

Examples:
- no matching route
- no reachable device
- organization closed
- queue timeout
- module unavailable
- adapter degraded

### 15.5 Keep external systems out of the hot path
External integrations should usually be asynchronous and event-driven.

If an external dependency is allowed to influence routing, it must do so through bounded, explicit, reliability-aware contracts.

### 15.6 Module isolation
Capability module failure must not take down core telephony operation.

### 15.7 Local-first runtime resilience
Critical operation must remain available without dependence on third-party storage or optional external integrations.

---

## 16. Naming Guidelines

The architecture must be named using stable domain vocabulary.

Preferred vocabulary:
- Organization
- Domain
- User
- Extension
- Device
- Entrypoint
- RoutingGraph
- SchedulePolicy
- PresenceAggregate
- MediaArchive
- StorageDriver
- TelephonyAdapter
- ModuleRegistry
- CapabilityModule

Avoid excessive product-name-prefixed core terms unless strictly required for packaging or branding layers.

This ensures the system remains semantically correct even if the product is renamed later.

---

## 17. What This Design Intentionally Avoids

This design intentionally avoids:
- traditional PBX-admin-first modeling where extensions are the primary human concept
- over-reliance on hardware provisioning as a core product driver
- duplicate tenant identifiers such as slug + domain in the core model
- raw graph interpretation in the runtime hot path without validation and compilation
- heavy product-name-centric architecture naming
- over-distributed microservice behavior inside call-critical routing

---

## 18. Expected Outcome

If implemented correctly, this design produces a platform that:
- feels like a modern business phone system to administrators
- remains programmable and extensible under the hood
- scales around organization/domain boundaries cleanly
- maps real people and endpoint devices correctly to telephony identities
- supports modern routing and availability logic
- stays reliable by separating authoring, compilation, publication, and runtime execution
- can evolve into broader communications functionality without becoming another legacy PBX admin surface

---

## 19. Implementation Direction

The next step should be a structured implementation plan that:
- maps this domain model onto the current codebase
- identifies which existing telephony objects remain, which are renamed conceptually, and which must be introduced
- defines module boundaries and interfaces
- sequences migration safely from the current PBX-oriented surface toward this domain-centric architecture

This design is approved as the target architecture direction for implementation planning.
