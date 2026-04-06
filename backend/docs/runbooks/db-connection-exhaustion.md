# Runbook: Database Connection Exhaustion

## Severity: Critical
## On-Call Response Time: 5 minutes

---

## Symptoms
- `SQLSTATE[HY000] [2002] Connection refused` errors
- `Too many connections` database errors
- Application returns 500 errors on all API endpoints
- Queue workers crashing with database errors
- Health check fails

## Detection
- Application error logs with database connection errors
- Database `max_connections` reached
- Connection pool exhaustion warnings
- All API endpoints returning 500

## Immediate Actions

### 1. Assess Database State
```bash
# Check current connections (PostgreSQL)
psql -c "SELECT count(*) FROM pg_stat_activity;"
psql -c "SELECT state, count(*) FROM pg_stat_activity GROUP BY state;"

# Check max connections setting
psql -c "SHOW max_connections;"

# Find long-running queries
psql -c "SELECT pid, now() - pg_stat_activity.query_start AS duration, query, state
FROM pg_stat_activity
WHERE (now() - pg_stat_activity.query_start) > interval '5 minutes'
ORDER BY duration DESC;"
```

### 2. Terminate Idle/Stuck Connections
```bash
# Kill idle connections older than 10 minutes
psql -c "SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE state = 'idle'
AND query_start < now() - interval '10 minutes'
AND pid != pg_backend_pid();"

# Kill stuck queries (> 5 minutes)
psql -c "SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE state = 'active'
AND query_start < now() - interval '5 minutes'
AND pid != pg_backend_pid();"
```

### 3. Restart Application Workers
```bash
# Restart queue workers (they may be holding stale connections)
php artisan queue:restart

# Restart web server to reset connection pool
systemctl restart php-fpm
# or
systemctl restart nginx
```

### 4. Increase Connections (Temporary)
```bash
# Increase max connections (requires restart for PostgreSQL)
# In postgresql.conf:
# max_connections = 200

# For immediate relief, consider using PgBouncer
# Check PgBouncer status if installed
psql -p 6432 -U pgbouncer pgbouncer -c "SHOW POOLS;"
```

### 5. Verify Recovery
```bash
# Check connection count is back to normal
psql -c "SELECT count(*) FROM pg_stat_activity;"

# Verify application health
curl -s http://localhost/api/health | jq .

# Check for pending queue jobs
php artisan queue:monitor
```

## Application Configuration Review
Check `.env` and `config/database.php`:
```env
# Connection pool settings
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432

# In config/database.php, check:
# 'pool' => ['min' => 2, 'max' => 10]
```

## Post-Incident
- [ ] Identify source of connection leak (missing connection closing)
- [ ] Review queue worker connection handling
- [ ] Check for N+1 query patterns causing excessive connections
- [ ] Review connection pool configuration
- [ ] Consider implementing connection pooling (PgBouncer)
- [ ] Update incident log

## Escalation
If connections cannot be reduced:
1. Scale database vertically (more connection slots)
2. Deploy PgBouncer for connection pooling
3. Review application for connection leak bugs

## Prevention
- Use connection pooling (PgBouncer for PostgreSQL)
- Set appropriate pool sizes in application config
- Monitor active connections with alerts at 80% of max
- Implement query timeouts to prevent stuck connections
- Regular review of slow query log
