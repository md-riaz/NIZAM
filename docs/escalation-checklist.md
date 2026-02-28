# Escalation Checklist

## Overview
This checklist guides on-call engineers through the escalation process when
incidents cannot be resolved within the expected response window.

---

## Severity Levels

| Level | Description | Response Time | Escalation After |
|-------|-------------|---------------|------------------|
| **P0 — Critical** | Total service outage, data loss risk | 5 minutes | 15 minutes |
| **P1 — High** | Major feature degraded, some tenants affected | 10 minutes | 30 minutes |
| **P2 — Medium** | Minor feature degraded, workaround exists | 30 minutes | 2 hours |
| **P3 — Low** | Cosmetic issue, no user impact | Next business day | 1 week |

---

## Escalation Steps

### Step 1: Acknowledge (within response time)
- [ ] Acknowledge the alert in the monitoring system
- [ ] Join the incident channel (if applicable)
- [ ] Assess severity using the table above
- [ ] Start incident timer

### Step 2: Diagnose (within 10 minutes of acknowledgement)
- [ ] Check relevant runbook for the failure type:
  - [FreeSWITCH Node Down](./runbooks/freeswitch-node-down.md)
  - [ESL Disconnect Storm](./runbooks/esl-disconnect-storm.md)
  - [Redis Memory Pressure](./runbooks/redis-memory-pressure.md)
  - [DB Connection Exhaustion](./runbooks/db-connection-exhaustion.md)
  - [Webhook Backlog Explosion](./runbooks/webhook-backlog-explosion.md)
- [ ] Review application logs: `tail -f /var/log/nizam/laravel.log`
- [ ] Check system health: `curl http://localhost/api/health`
- [ ] Identify affected tenants and scope of impact

### Step 3: Mitigate (within escalation window)
- [ ] Apply the appropriate runbook fix
- [ ] Verify the fix resolves the issue
- [ ] Confirm affected tenants are restored
- [ ] If fix doesn't work, proceed to Step 4

### Step 4: Escalate
- [ ] Notify the next level:
  - **P0/P1**: Page the platform lead immediately
  - **P2**: Notify via Slack/email within 30 minutes
  - **P3**: Create a ticket for next sprint
- [ ] Provide escalation summary:
  - What happened (symptoms)
  - What was tried (actions taken)
  - Current state (what's still broken)
  - Impact assessment (tenants, calls affected)

### Step 5: Resolve
- [ ] Confirm all systems are healthy
- [ ] Verify all tenant services are restored
- [ ] Run smoke tests if applicable
- [ ] Update status page / notify affected customers

### Step 6: Post-Incident
- [ ] Write incident summary within 24 hours
- [ ] Schedule post-mortem for P0/P1 incidents
- [ ] Create follow-up tickets for:
  - Root cause fix (if temporary mitigation applied)
  - Monitoring improvements
  - Runbook updates
  - Prevention measures

---

## Contact List

| Role | Contact Method |
|------|---------------|
| On-Call Primary | PagerDuty / Alert policy |
| On-Call Secondary | PagerDuty escalation |
| Platform Lead | Slack DM + phone |
| Database Admin | Slack #db-ops |
| Infrastructure | Slack #infra |

---

## Communication Templates

### Status Update (Internal)
```
[INCIDENT] Severity: P{X} | Status: Investigating/Mitigating/Resolved
Impact: {description of impact}
Affected: {tenants/services}
Action: {what is being done}
ETA: {estimated resolution time}
```

### Customer Notification
```
We are currently experiencing {issue description}.
Impact: {what customers may notice}
Status: Our team is actively working to resolve this.
Updates: We will provide updates every {30 minutes / 1 hour}.
```
