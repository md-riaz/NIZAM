# Runbook: ESL Disconnect Storm

## Severity: High
## On-Call Response Time: 10 minutes

---

## Symptoms
- Rapid ESL connect/disconnect cycles in logs
- `EslConnectionManager` reconnection attempts flooding logs
- Partial call control failures (some commands succeed, others fail)
- Application response times increasing

## Detection
- Log pattern: repeated `ESL connection lost` / `ESL reconnecting` messages
- Gateway flapping alert from anomaly detector
- Device registration/unregistration events spike

## Immediate Actions

### 1. Assess the Storm
```bash
# Count recent ESL disconnect events in logs
grep -c "ESL connection" /var/log/nizam/laravel.log | tail -100

# Check FreeSWITCH event socket status
fs_cli -x "event_socket status"

# Check FreeSWITCH load
fs_cli -x "status"
fs_cli -x "show channels count"
```

### 2. Check Network Connectivity
```bash
# Verify network path to FreeSWITCH
ping -c 3 <freeswitch_host>

# Check for network interface errors
ip -s link show

# Check for firewall changes
iptables -L -n | grep 8021
```

### 3. Check FreeSWITCH Event Socket Configuration
```bash
# Verify ESL config
cat /etc/freeswitch/autoload_configs/event_socket.conf.xml

# Check max connections
fs_cli -x "event_socket show"
```

### 4. Rate-Limit Reconnections
If the application is hammering ESL with reconnection attempts:
- Check `EslConnectionManager` configuration for backoff settings
- Temporarily increase reconnection delay in configuration
- If needed, restart the application queue workers to reset connection state

```bash
# Restart queue workers to reset ESL connections
php artisan queue:restart
```

### 5. Verify Stabilization
```bash
# Watch for stabilization (no new disconnect messages for 2 minutes)
tail -f /var/log/nizam/laravel.log | grep "ESL"

# Verify call control is working
curl -s http://localhost/api/health | jq .
```

## Root Cause Analysis
- Network instability between app server and FreeSWITCH
- FreeSWITCH overload (too many channels)
- ESL max connection limit reached
- FreeSWITCH module crash causing ESL reset
- TCP keepalive timeout issues

## Post-Incident
- [ ] Review ESL connection logs for pattern
- [ ] Check if a specific event flood preceded the storm
- [ ] Verify all active calls were not interrupted
- [ ] Review and tune reconnection backoff parameters
- [ ] Update incident log

## Escalation
If disconnect storm persists for more than 10 minutes:
1. Consider isolating the FreeSWITCH node
2. Redirect traffic to standby node if available
3. Engage FreeSWITCH platform team

## Prevention
- Implement exponential backoff for ESL reconnections
- Set up connection pooling with max connections
- Monitor ESL connection health separately
- Add circuit breaker for ESL commands
