# Log Viewer Feature

## Overview

The Log Viewer provides platform administrators with web-based access to FreeSWITCH and Laravel application logs without requiring console/SSH access. This feature is inspired by FusionPBX's log viewer functionality.

## Authorization

Access is restricted to **platform administrators only** (users with `role = 'admin'` and `organization_id = null`). Organization administrators and regular users cannot access system logs.

## Features

### 1. Log Files Overview
- Lists all available log files in the `storage/logs` directory
- Shows file size and last modified timestamp
- Provides quick overview of available logs

### 2. Laravel Application Logs
- View recent log entries from `laravel.log`
- Configurable line count (50, 100, 250, 500, 1000 lines)
- Maximum limit of 1000 lines enforced for performance
- Real-time refresh capability
- Monospace font display for easy reading

### 3. FreeSWITCH Status
- Query current FreeSWITCH log level via ESL
- View system status and uptime
- Configurable log level selection (console, alert, crit, err, warning, notice, info, debug)
- Real-time refresh capability

## API Endpoints

### List Log Files
```http
GET /api/v1/admin/logs
Authorization: Bearer {token}
```

### View Application Logs
```http
GET /api/v1/admin/logs/application?lines=100
Authorization: Bearer {token}
```

### Query FreeSWITCH Status
```http
GET /api/v1/admin/logs/freeswitch?level=info
Authorization: Bearer {token}
```

## Frontend Access

Navigate to `/admin/system-logs` in the web interface. The link appears in the sidebar under "System" section for platform administrators only.

## Implementation Details

### Backend
- **Controller**: `App\Http\Controllers\Api\LogViewerController`
- **Authorization Gate**: `platform-admin` (defined in `AppServiceProvider`)
- **Routes**: Defined in `routes/api.php` under `/admin/logs` prefix

### Frontend
- **Component**: `resources/js/pages/admin/LogViewerPage.tsx`
- **Route**: `/admin/system-logs`
- **UI Components**: Uses Radix UI tabs, cards, and shadcn/ui components

### Testing
- **Feature Tests**: `tests/Feature/Api/LogViewerTest.php`
- Covers authorization, line limits, error handling, and API responses

## Future Enhancements

### Live Log Streaming
For real-time log streaming, implement Server-Sent Events (SSE) or WebSocket with ESL event subscription:

```php
// Example ESL event subscription for live logs
$esl->events('plain', 'log');
$esl->setLogLevel($level);

while ($event = $esl->recvEvent()) {
    // Stream log events to frontend
}
```

### Additional Features
- Log file download
- Log search/filtering
- Log level filtering for Laravel logs
- Multiple log file support (not just laravel.log)
- Log rotation status
- Disk space monitoring

## Security Considerations

1. **Authorization**: Only platform administrators can access logs
2. **Line Limits**: Maximum 1000 lines to prevent memory issues
3. **No File Path Traversal**: Only files in `storage/logs` are accessible
4. **Sanitized Output**: Log content is displayed as-is but within controlled UI
5. **ESL Connection**: Uses configured ESL credentials from environment

## Troubleshooting

### "Failed to connect to FreeSWITCH ESL"
- Verify FreeSWITCH is running
- Check ESL configuration in `.env`
- Ensure ESL port (8021) is accessible

### "Log file not found"
- Verify `storage/logs/laravel.log` exists
- Check file permissions
- Ensure Laravel logging is configured correctly

### "403 Forbidden"
- Verify user has `role = 'admin'` and `organization_id = null`
- Check authentication token is valid
- Ensure `platform-admin` gate is properly defined
