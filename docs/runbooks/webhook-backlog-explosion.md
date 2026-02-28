# Runbook: Webhook Backlog Explosion

## Severity: High
## On-Call Response Time: 10 minutes

---

## Symptoms
- Webhook delivery delays increasing
- Queue jobs table growing rapidly
- `webhook_delivery_attempts` table shows many pending/failed entries
- Webhook consumers reporting missing or delayed events
- Queue workers consuming high CPU/memory

## Detection
- Webhook failure rate alert from anomaly detector
- Queue depth monitoring shows growth
- `WebhookDeliveryAttempt` records with `status = 'failed'` increasing
- Application log shows repeated HTTP timeout errors to webhook URLs

## Immediate Actions

### 1. Assess the Backlog
```bash
# Check queue depth
php artisan queue:monitor

# Check pending webhook jobs
php artisan tinker --execute="
echo 'Pending jobs: ' . DB::table('jobs')->count() . PHP_EOL;
echo 'Failed jobs: ' . DB::table('failed_jobs')->count() . PHP_EOL;
"

# Check webhook delivery stats
php artisan tinker --execute="
use App\Models\WebhookDeliveryAttempt;
echo 'Last hour attempts: ' . WebhookDeliveryAttempt::where('created_at', '>=', now()->subHour())->count() . PHP_EOL;
echo 'Failed: ' . WebhookDeliveryAttempt::where('created_at', '>=', now()->subHour())->where('status', 'failed')->count() . PHP_EOL;
"
```

### 2. Identify Failing Webhooks
```bash
# Find webhooks with highest failure rates
php artisan tinker --execute="
use App\Models\WebhookDeliveryAttempt;
\$failures = WebhookDeliveryAttempt::where('created_at', '>=', now()->subHour())
    ->where('status', 'failed')
    ->selectRaw('webhook_id, count(*) as count')
    ->groupBy('webhook_id')
    ->orderByDesc('count')
    ->get();
print_r(\$failures->toArray());
"
```

### 3. Disable Failing Webhooks
If specific webhook URLs are consistently failing and causing backlog:
```bash
# Disable specific failing webhook (via API or tinker)
php artisan tinker --execute="
use App\Models\Webhook;
// Disable webhooks that have > 100 failures in last hour
\$webhookIds = DB::table('webhook_delivery_attempts')
    ->where('created_at', '>=', now()->subHour())
    ->where('status', 'failed')
    ->selectRaw('webhook_id, count(*) as fail_count')
    ->groupBy('webhook_id')
    ->having('fail_count', '>', 100)
    ->pluck('webhook_id');
Webhook::whereIn('id', \$webhookIds)->update(['is_active' => false]);
echo 'Disabled ' . count(\$webhookIds) . ' webhooks' . PHP_EOL;
"
```

### 4. Clear Failed Jobs (if needed)
```bash
# Flush failed jobs (they will not be retried)
php artisan queue:flush

# Or retry specific failed jobs
php artisan queue:retry all
```

### 5. Scale Queue Workers (if needed)
```bash
# Start additional queue workers temporarily
php artisan queue:work --queue=webhooks --tries=3 --timeout=30 &
php artisan queue:work --queue=webhooks --tries=3 --timeout=30 &
```

### 6. Verify Recovery
```bash
# Monitor queue depth is decreasing
watch -n 5 'php artisan tinker --execute="echo DB::table(\"jobs\")->count();"'

# Check webhook delivery success rate
php artisan tinker --execute="
use App\Models\WebhookDeliveryAttempt;
\$recent = WebhookDeliveryAttempt::where('created_at', '>=', now()->subMinutes(5));
echo 'Success: ' . \$recent->clone()->where('status', 'success')->count() . PHP_EOL;
echo 'Failed: ' . \$recent->where('status', 'failed')->count() . PHP_EOL;
"
```

## Post-Incident
- [ ] Identify which webhook URLs were failing and why
- [ ] Contact webhook consumers about delivery issues
- [ ] Review webhook retry policy and backoff strategy
- [ ] Check for event flood that caused the backlog
- [ ] Consider implementing circuit breaker for webhook delivery
- [ ] Update incident log

## Escalation
If backlog continues to grow after 30 minutes:
1. Scale queue infrastructure (more workers, more memory)
2. Implement emergency rate limiting on webhook dispatch
3. Contact webhook consumers to investigate their endpoint issues

## Prevention
- Implement circuit breaker pattern for webhook delivery
- Set maximum retry count with exponential backoff
- Auto-disable webhooks after N consecutive failures
- Monitor queue depth with alerts
- Set reasonable timeouts for webhook HTTP requests (5-10 seconds)
- Implement webhook delivery SLO monitoring
