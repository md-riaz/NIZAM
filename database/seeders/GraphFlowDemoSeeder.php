<?php

namespace Database\Seeders;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\HolidayCalendar;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\Tenant;
use App\Services\Flow\FlowGraphService;
use Illuminate\Database\Seeder;

class GraphFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'nizam-graph-demo'],
            [
                'name' => 'NIZAM Graph Demo',
                'domain' => 'graph-demo.nizam.local',
                'settings' => ['default_country_code' => '1'],
                'is_active' => true,
                'status' => Tenant::STATUS_ACTIVE,
            ]
        );

        $salesA = Extension::firstOrCreate(
            ['tenant_id' => $tenant->id, 'extension' => '2001'],
            [
                'password' => 'pass2001',
                'directory_first_name' => 'Sales',
                'directory_last_name' => 'One',
                'effective_caller_id_name' => 'Sales One',
                'effective_caller_id_number' => '2001',
                'voicemail_enabled' => true,
                'voicemail_pin' => '2001',
                'is_active' => true,
            ]
        );

        $salesB = Extension::firstOrCreate(
            ['tenant_id' => $tenant->id, 'extension' => '2002'],
            [
                'password' => 'pass2002',
                'directory_first_name' => 'Sales',
                'directory_last_name' => 'Two',
                'effective_caller_id_name' => 'Sales Two',
                'effective_caller_id_number' => '2002',
                'voicemail_enabled' => true,
                'voicemail_pin' => '2002',
                'is_active' => true,
            ]
        );

        $calendar = HolidayCalendar::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'US Demo Holidays'],
            [
                'timezone' => 'UTC',
                'is_active' => true,
            ]
        );

        $schedule = Schedule::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Main Office Hours'],
            [
                'holiday_calendar_id' => $calendar->id,
                'timezone' => 'UTC',
                'is_active' => true,
            ]
        );

        if (! $schedule->rules()->exists()) {
            foreach ([1, 2, 3, 4, 5] as $day) {
                $schedule->rules()->create([
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                ]);
            }

            $schedule->breaks()->create([
                'day_of_week' => 1,
                'start_time' => '13:00',
                'end_time' => '14:00',
            ]);
        }

        $team = Team::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Sales Team'],
            [
                'strategy' => 'simultaneous',
                'timeout' => 20,
                'is_active' => true,
            ]
        );

        if (! $team->members()->exists()) {
            $team->members()->createMany([
                [
                    'endpoint_type' => 'extension',
                    'endpoint_id' => $salesA->id,
                    'priority' => 10,
                    'is_active' => true,
                ],
                [
                    'endpoint_type' => 'extension',
                    'endpoint_id' => $salesB->id,
                    'priority' => 20,
                    'is_active' => true,
                ],
            ]);
        }

        $flow = Flow::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Main Office Flow'],
            ['description' => 'Schedule -> menu -> ring team demo flow']
        );

        if (! $flow->versions()->exists()) {
            app(FlowGraphService::class)->updateFlowWithVersion($flow, [
                'name' => $flow->name,
                'description' => $flow->description,
                'publish' => true,
                'version' => [
                    'definition' => [
                        'nodes' => [
                            ['id' => 'start', 'type' => 'start', 'name' => 'Start', 'config' => []],
                            ['id' => 'schedule', 'type' => 'schedule_check', 'name' => 'Schedule Check', 'config' => ['schedule_id' => $schedule->id]],
                            ['id' => 'menu', 'type' => 'menu', 'name' => 'Main Menu', 'config' => ['prompt' => 'ivr/main_menu.wav', 'timeout' => 5, 'digits' => ['1']]],
                            ['id' => 'ring-sales', 'type' => 'ring_team', 'name' => 'Ring Sales Team', 'config' => ['team_id' => $team->id, 'timeout' => 20]],
                            ['id' => 'after-hours', 'type' => 'voicemail', 'name' => 'After Hours Voicemail', 'config' => ['mailbox' => '2001']],
                            ['id' => 'hangup', 'type' => 'hangup', 'name' => 'Hangup', 'config' => ['cause' => 'NORMAL_CLEARING']],
                        ],
                        'edges' => [
                            ['source_node_id' => 'start', 'target_node_id' => 'schedule', 'condition' => 'next'],
                            ['source_node_id' => 'schedule', 'target_node_id' => 'menu', 'condition' => 'open'],
                            ['source_node_id' => 'schedule', 'target_node_id' => 'after-hours', 'condition' => 'closed'],
                            ['source_node_id' => 'schedule', 'target_node_id' => 'after-hours', 'condition' => 'break'],
                            ['source_node_id' => 'schedule', 'target_node_id' => 'after-hours', 'condition' => 'holiday'],
                            ['source_node_id' => 'menu', 'target_node_id' => 'ring-sales', 'condition' => 'digit_1'],
                            ['source_node_id' => 'menu', 'target_node_id' => 'after-hours', 'condition' => 'default'],
                            ['source_node_id' => 'ring-sales', 'target_node_id' => 'hangup', 'condition' => 'answered'],
                            ['source_node_id' => 'ring-sales', 'target_node_id' => 'after-hours', 'condition' => 'timeout'],
                        ],
                    ],
                ],
            ]);

            $flow = $flow->fresh(['activeVersion']);
        }

        Did::updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => '+15551002000'],
            [
                'description' => 'Graph-native main office DID',
                'destination_type' => 'flow',
                'destination_id' => $flow->id,
                'is_active' => true,
            ]
        );
    }
}
