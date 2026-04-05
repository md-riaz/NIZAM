# Product

NIZAM is an API-first communications control platform built around FreeSWITCH and Laravel.

It separates telephony media handling from business logic:
- FreeSWITCH handles SIP, RTP, WebRTC, bridging, recording, and conferencing
- Laravel is the control plane for tenants, routing, provisioning, permissions, events, and APIs

The product is multi-tenant and telecom-focused. Core areas in the codebase include:
- tenant and user management
- extensions, DIDs, ring groups, IVRs, schedules, and time conditions
- call routing policies and flow execution
- gateway and bridge management
- call event ingestion, CDRs, recordings, analytics, and webhooks
- device provisioning and WebRTC/TLS settings

When assisting in this repo, prefer changes that preserve:
- API-first design
- tenant isolation
- database-backed source of truth
- FreeSWITCH as execution/media layer, not business-state owner
- modular extension points instead of one-off feature sprawl
