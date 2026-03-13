# NIZAM Laravel File Map

This is the concrete file-by-file implementation map for the refactor.
It follows the implementation checklist in execution order.

## Phase 0
- `docs/ARCHITECTURE_BASELINE.md`
- `docs/ROUTES_BASELINE.md`

## Phase 1
- `routes/web.php`
- `app/Http/Controllers/Web/AuthController.php`
- `app/Http/Controllers/Web/UiController.php`
- `resources/views/ui/*`
- `resources/views/dashboard/*`
- `resources/views/extensions/*`

## Phase 2
- `routes/api.php`
- `app/Domain/Call/*`
- `app/Domain/Flow/*`
- `app/Domain/Routing/*`
- `app/Domain/Schedule/*`
- `app/Domain/Team/*`
- `app/Domain/Media/*`
- `app/Services/Call/*`
- `app/Services/Flow/*`
- `app/Services/Routing/*`
- `app/Services/Schedule/*`
- `app/Services/Team/*`
- `app/Services/Media/*`

## Phase 3: Call spine
### Migrations
- `database/migrations/2026_03_13_120000_create_call_sessions_table.php`
- `database/migrations/2026_03_13_120100_create_call_trace_events_table.php`
- `database/migrations/2026_03_13_120200_upgrade_call_events_for_runtime_spine.php`

### Models
- `app/Models/CallSession.php`
- `app/Models/CallTraceEvent.php`
- `app/Models/CallEventLog.php` update

### Services
- `app/Services/Call/CallSessionService.php`
- `app/Services/Call/TraceWriter.php`
- `app/Services/Call/CallEventIngestionService.php`
- `app/Services/Call/CallLockService.php`

## Phase 4: Gateway registration and number routing
### Migrations
- `database/migrations/2026_03_13_120300_create_gateway_registrations_table.php`
- `database/migrations/2026_03_13_120400_upgrade_dids_for_gateway_routing.php`

### Models
- `app/Models/GatewayRegistration.php`
- `app/Models/Gateway.php` update
- `app/Models/Did.php` update
- `app/Models/Tenant.php` update

### Services
- `app/Services/Routing/GatewayResolutionService.php`
- `app/Services/Routing/NumberRoutingService.php`
- `app/Services/Media/GatewayRegistrationService.php`

### HTTP / runtime entrypoints
- `app/Http/Controllers/FreeswitchXmlController.php`
- `app/Services/DialplanCompiler.php`

## Phase 5: Flow engine core
Status: partially implemented with new graph-native schema and models.
### Migrations
- `database/migrations/*_create_flows_table.php`
- `database/migrations/*_create_flow_versions_table.php`
- `database/migrations/*_create_flow_nodes_table.php`
- `database/migrations/*_create_flow_edges_table.php`

### Domain objects
- `app/Domain/Flow/CallContext.php`
- `app/Domain/Flow/NodeResult.php`
- `app/Domain/Flow/Contracts/NodeHandler.php`

### Services
- `app/Services/Flow/FlowExecutionService.php`
- `app/Services/Flow/NodeHandlerFactory.php`
- `app/Services/Flow/EdgeResolver.php`
- `app/Services/Flow/FlowPublishService.php`
- `app/Services/Flow/FlowValidationService.php`

### Node handlers
- `app/Services/Flow/Nodes/StartNodeHandler.php`
- `app/Services/Flow/Nodes/ScheduleCheckNodeHandler.php`
- `app/Services/Flow/Nodes/MenuNodeHandler.php`
- `app/Services/Flow/Nodes/RingTeamNodeHandler.php`
- `app/Services/Flow/Nodes/VoicemailNodeHandler.php`
- `app/Services/Flow/Nodes/HangupNodeHandler.php`

## Phase 6: Schedule engine
Status: partially implemented
### Migrations
- `database/migrations/*_create_holiday_calendars_table.php`
- `database/migrations/*_create_holidays_table.php`
- `database/migrations/*_create_schedules_table.php`
- `database/migrations/*_create_schedule_rules_table.php`
- `database/migrations/*_create_schedule_breaks_table.php`
- `database/migrations/*_create_schedule_exceptions_table.php`

### Models / services
- `app/Models/Schedule.php`
- `app/Models/ScheduleRule.php`
- `app/Models/ScheduleBreak.php`
- `app/Models/ScheduleException.php`
- `app/Models/HolidayCalendar.php`
- `app/Models/Holiday.php`
- `app/Services/Schedule/ScheduleEngine.php`

## Phase 7: Teams
- `database/migrations/*_create_teams_table.php`
- `database/migrations/*_create_team_members_table.php`
- `app/Models/Team.php`
- `app/Models/TeamMember.php`
- `app/Services/Team/TeamRoutingService.php`

## Phase 8+: APIs and UI
Media control skeleton, flow validators, and schedule CRUD/API are now partially implemented.
- `app/Http/Controllers/Api/*` updates
- `app/Http/Requests/*` new validators
- separate frontend repo later
