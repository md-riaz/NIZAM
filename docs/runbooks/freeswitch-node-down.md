# Runbook: FreeSWITCH Node Down

## Severity: Critical
## On-Call Response Time: 5 minutes

---

## Symptoms
- No new calls being placed or received
- ESL connection failures in application logs
- Health check endpoint returns degraded status
- `fs_cli` unresponsive or connection refused

## Detection
- Health check API returns non-200
- ESL connection errors in `laravel.log`
- Monitoring alert: "FreeSWITCH ESL disconnect"

## Immediate Actions

### 1. Verify FreeSWITCH Status
```bash
# Check if FreeSWITCH process is running
systemctl status freeswitch

# Check if ESL port is listening
ss -tlnp | grep 8021

# Try connecting via fs_cli
fs_cli -H 127.0.0.1 -P 8021 -p ClueCon
```

### 2. Check System Resources
```bash
# Check disk space (FreeSWITCH may crash on full disk)
df -h

# Check memory
free -h

# Check open file descriptors
ls /proc/$(pidof freeswitch)/fd | wc -l
ulimit -n
```

### 3. Restart FreeSWITCH
```bash
# Graceful restart
systemctl restart freeswitch

# If graceful restart fails, force
systemctl stop freeswitch
sleep 5
systemctl start freeswitch
```

### 4. Verify Recovery
```bash
# Confirm process is running
systemctl status freeswitch

# Confirm ESL is accepting connections
fs_cli -x "status"

# Check application can connect
curl -s http://localhost/api/health | jq .
```

## Post-Incident
- [ ] Check `/var/log/freeswitch/freeswitch.log` for crash reason
- [ ] Review core dump if available: `ls /tmp/cores/`
- [ ] Check if configuration changes preceded the crash
- [ ] Verify all tenants' dialplans are loaded: `fs_cli -x "xml_locate directory"`
- [ ] Run smoke tests on call routing
- [ ] Update incident log

## Escalation
If FreeSWITCH cannot be restarted within 15 minutes:
1. Engage infrastructure team
2. Check for OS-level issues (kernel panic, hardware failure)
3. Consider failover to standby node if available

## Prevention
- Monitor FreeSWITCH memory usage (alert at 80%)
- Monitor disk space (alert at 90%)
- Set up automatic core dumps for crash analysis
- Keep FreeSWITCH updated to latest stable release
