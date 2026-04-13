# Modular Business Phone System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the PBX-centric core into a modern, domain-oriented business phone system with a modular architecture and graph-based routing.

**Architecture:** A domain-stable core (Organization, User, Extension, Device) managing a merged availability state and compiled routing graphs, with swappable capability modules (Voicemail, Recording, etc.) and infrastructure adapters.

**Tech Stack:** Laravel, PostgreSQL, Redis, FreeSWITCH (ESL/XML-CURL), Vue.js/React (Frontend).

---

## Foundational Tasks (Sequential)

### Task 1: Organization & Domain Context Refactor
**Files:**
- Modify: `backend/app/Models/Tenant.php`
- Modify: `backend/database/migrations/xxxx_create_tenants_table.php` (or new migration)
- Modify: `backend/app/Services/DialplanCompiler.php` (remove slug usage)
- Modify: `backend/app/Services/TenantManifestBuilder.php`

- [ ] **Step 1: Create migration to remove `slug` from `tenants` table**
- [ ] **Step 2: Update `Tenant` model to rely solely on `domain`**
- [ ] **Step 3: Update `DialplanCompiler` and `SipProfileCompiler` to resolve tenancy by `domain` only**
- [ ] **Step 4: Run tests to ensure domain-based isolation works**
- [ ] **Step 5: Commit changes**

### Task 2: Core Identity Mapping (User-Extension-Device)
**Files:**
- Modify: `backend/app/Models/User.php`
- Modify: `backend/app/Models/Extension.php`
- Modify: `backend/app/Models/DeviceProfile.php`
- Create: `backend/app/Services/Presence/PresenceAggregator.php`

- [ ] **Step 1: Update `User` model to have a many-to-many or one-to-many relationship with `DeviceProfile`**
- [ ] **Step 2: Update `Extension` to optionally link to a `User`**
- [ ] **Step 3: Create `PresenceAggregator` to merge User status, Device registration, and active calls**
- [ ] **Step 4: Write unit tests for presence aggregation logic**
- [ ] **Step 5: Commit changes**

---

## Core Engine Tasks (Sequential)

### Task 3: Schedule & Policy Engine
**Files:**
- Modify: `backend/app/Models/Schedule.php`
- Modify: `backend/app/Models/HolidayCalendar.php`
- Create: `backend/app/Services/Policy/SchedulePolicyEngine.php`

- [ ] **Step 1: Implement Organization-level default schedule lookup**
- [ ] **Step 2: Implement inheritance/override logic for Departments and Users**
- [ ] **Step 3: Create `SchedulePolicyEngine` to answer "Is this target open right now?"**
- [ ] **Step 4: Run tests for complex holiday/override scenarios**
- [ ] **Step 5: Commit changes**

### Task 4: Routing Graph Compiler & Entrypoints
**Files:**
- Modify: `backend/app/Models/Flow.php`
- Create: `backend/app/Services/Routing/RoutingGraphCompiler.php`
- Modify: `backend/app/Services/DialplanCompiler.php`

- [ ] **Step 1: Design the internal IR (Intermediate Representation) for a compiled routing graph**
- [ ] **Step 2: Create `RoutingGraphCompiler` to validate and compile Flow graphs into deterministic artifacts**
- [ ] **Step 3: Update `DialplanCompiler` to use compiled artifacts for inbound DID routing**
- [ ] **Step 4: Implement Entrypoint resolution (DID -> Graph/Preset)**
- [ ] **Step 5: Commit changes**

---

## Parallelizable Capability Modules (Dispatched in Parallel)

### Task 5: Voicemail Module Refactor
**Files:**
- Create: `backend/app/Modules/Voicemail/VoicemailModule.php`
- Modify: `backend/app/Services/EventProcessor.php`

- [ ] **Step 1: Move voicemail logic from core into a decoupled Module**
- [ ] **Step 2: Implement local-first storage for voicemail messages**
- [ ] **Step 3: Register hooks for `voicemail.received` events**
- [ ] **Step 4: Commit changes**

### Task 6: Recording & Media Archive Module
**Files:**
- Create: `backend/app/Modules/Media/MediaArchiveModule.php`
- Create: `backend/app/Services/Storage/LocalFileSystemDriver.php`

- [ ] **Step 1: Implement `StorageDriver` interface and `LocalFileSystemDriver`**
- [ ] **Step 2: Create `MediaArchiveModule` to manage recording lifecycle and cleanup policies**
- [ ] **Step 3: Register hooks for `call.end` to archive recordings**
- [ ] **Step 4: Commit changes**

### Task 7: Messaging & SMS Module
**Files:**
- Create: `backend/app/Modules/Messaging/MessagingModule.php`
- Create: `backend/app/Services/Messaging/SmsAdapterInterface.php`

- [ ] **Step 1: Implement modular SMS routing and storage**
- [ ] **Step 2: Create SignalWire/Telnyx adapters (stubs or basic impl)**
- [ ] **Step 3: Commit changes**

### Task 8: AI & Transcription Module
**Files:**
- Create: `backend/app/Modules/AI/TranscriptionModule.php`
- Modify: `backend/app/Services/RecordingIntelligenceService.php`

- [ ] **Step 1: Refactor recording intelligence into a swappable AI module**
- [ ] **Step 2: Implement transcription hooks for recordings and voicemails**
- [ ] **Step 3: Commit changes**

---

## Platform & UI Tasks (Sequential)

### Task 9: WebRTC & Softphone Optimization
**Files:**
- Modify: `backend/app/Services/WebRtcConfigService.php`
- Modify: `backend/app/Services/ProvisioningService.php`

- [ ] **Step 1: Optimize SIP profiles for WebRTC (WSS/DTLS) and Softphones (TCP/TLS)**
- [ ] **Step 2: Move hardware provisioning to a secondary module**
- [ ] **Step 3: Implement Mobile Push adapter (FCM/APNs) in core**
- [ ] **Step 4: Commit changes**

### Task 10: Dual-View Admin UI Migration
**Files:**
- Modify: `frontend/src/pages/admin/DashboardPage.tsx`
- Create: `frontend/src/pages/business/UsersPage.tsx`
- Create: `frontend/src/pages/business/CallFlowsPage.tsx`

- [ ] **Step 1: Implement the "Business View" focusing on Users and Schedules**
- [ ] **Step 2: Implement the "Telecom View" for advanced Routing Graphs and SIP status**
- [ ] **Step 3: Update Dashboard to show merged Presence and Domain status**
- [ ] **Step 4: Commit changes**
