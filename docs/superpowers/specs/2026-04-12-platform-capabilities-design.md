# Platform Capabilities & FusionPBX Parity Design

## Goal
Implement FusionPBX-style self-call behavior (account management/voicemail access) and surface this and other advanced PBX capabilities in a dedicated platform-admin dashboard page.

## Core Features

### 1. FusionPBX-style Self-Calling
- **Current Behavior**: Tries to `bridge` (ring) the same phone that is calling.
- **Enhanced Behavior**: Detects a self-call and executes the `voicemail` check application.
- **Logic**: In `DialplanCompiler`, if `caller_id_number` equals `destination_number`, return a dialplan that `answers` the call and enters `voicemail` in `check` mode.

### 2. Platform Capabilities Page
- **Location**: `/admin/capabilities` in the platform-admin (Superadmin) dashboard.
- **Visuals**: A grid of "Capability Cards."
- **Content**:
    - **Self-Call Management**: Description of the FusionPBX-style behavior.
    - **Multi-Registration Support**: Description of simultaneous contact support.
    - **Optimized Directory Service**: Description of the filtered XML-CURL lookup performance.
    - **Context Isolation**: Description of multi-tenant domain context logic.
- **Status Indicators**: Each card will show whether the feature is active based on live system settings or code presence.

## Architecture

### Frontend
- **Page**: `CapabilitiesPage.tsx`
- **Component**: `CapabilityCard`
- **Navigation**: Update `SuperadminLayout.tsx` to include the page under the "System" section.

### Backend
- **Endpoint**: `GET /api/v1/admin/capabilities`
- **Service**: `CapabilityService` (new) to collect live feature states.
- **Compiler Update**: Modify `DialplanCompiler.php` for self-call parity.

## Data Flow
1. Admin opens the Capabilities page.
2. Frontend fetches live state from `/api/v1/admin/capabilities`.
3. Backend checks database (SipProfileSettings) and code state.
4. Frontend renders the registry with active/inactive badges.

## Success Criteria
- Calling your own extension from MicroSIP/tSIP triggers the voicemail management prompt.
- Admins can see the list of enhancements and their status.
- System remains performant and multi-tenant isolated.
