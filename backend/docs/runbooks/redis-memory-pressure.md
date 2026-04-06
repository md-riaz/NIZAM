# Runbook: Redis Memory Pressure

## Severity: High
## On-Call Response Time: 10 minutes

---

## Symptoms
- Application cache failures or slow responses
- Queue job processing delays
- `MISCONF Redis is configured to save RDB snapshots` errors
- Broadcasting/WebSocket event delivery failures

## Detection
- Redis `used_memory` exceeds threshold
- Application log errors: `OOM command not allowed`
- Cache hit ratio drops significantly
- Queue processing latency increases

## Immediate Actions

### 1. Assess Memory Usage
```bash
# Connect to Redis and check memory
redis-cli INFO memory

# Key metrics to check:
# used_memory_human
# used_memory_peak_human
# mem_fragmentation_ratio
# maxmemory (if set)

# Check connected clients
redis-cli INFO clients
```

### 2. Identify Memory Consumers
```bash
# Check database sizes
redis-cli INFO keyspace

# Find large keys (sample-based, safe for production)
redis-cli --bigkeys

# Check memory usage by key pattern
redis-cli --memkeys --samples 1000
```

### 3. Emergency Memory Relief
```bash
# If using RDB persistence and getting MISCONF errors
redis-cli CONFIG SET stop-writes-on-bgsave-error no

# Flush expired keys manually
redis-cli DEBUG sleep 0

# If cache keys are the problem, flush cache (not sessions/queues!)
# CAUTION: Only flush the cache database, not all databases
php artisan cache:clear
```

### 4. Check for Key Leaks
```bash
# Look for keys without TTL (potential leaks)
redis-cli --scan --pattern '*' | head -100 | while read key; do
    ttl=$(redis-cli TTL "$key")
    if [ "$ttl" = "-1" ]; then
        echo "No TTL: $key ($(redis-cli TYPE "$key"))"
    fi
done
```

### 5. Set Memory Limits
```bash
# Set maxmemory if not set (e.g., 256MB)
redis-cli CONFIG SET maxmemory 256mb

# Set eviction policy
redis-cli CONFIG SET maxmemory-policy allkeys-lru

# Persist configuration
redis-cli CONFIG REWRITE
```

### 6. Verify Recovery
```bash
# Check memory after actions
redis-cli INFO memory | grep used_memory_human

# Verify application is healthy
curl -s http://localhost/api/health | jq .

# Check queue processing
php artisan queue:monitor
```

## Post-Incident
- [ ] Identify root cause of memory growth
- [ ] Review cache TTL settings in application
- [ ] Check for session/queue key accumulation
- [ ] Set proper maxmemory and eviction policy
- [ ] Review Redis persistence configuration
- [ ] Update incident log

## Escalation
If memory cannot be reduced below 80% of available:
1. Scale Redis instance vertically
2. Consider Redis Cluster for horizontal scaling
3. Move session/cache to separate Redis instances

## Prevention
- Set `maxmemory` and `maxmemory-policy` in Redis configuration
- Monitor Redis memory usage with alerts at 70% and 90%
- Set TTLs on all cache keys in application code
- Regular review of key space growth
- Use Redis memory analysis tools in staging
