<?php

namespace Database\Seeders;

use App\Models\CallDetailRecord;
use App\Models\DeviceProfile;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Ivr;
use App\Models\RingGroup;
use App\Models\Tenant;
use App\Models\TimeCondition;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Demo/default tenant
        $tenant = Tenant::updateOrCreate(
            ['slug' => env('ADMIN_TENANT_SLUG', 'demo-company')],
            [
                'name' => env('ADMIN_TENANT_NAME', 'Demo Company'),
                'domain' => env('ADMIN_TENANT_DOMAIN', 'demo.nizam.local'),
                'settings' => [],
                'max_extensions' => 50,
                'is_active' => true,
            ]
        );

        $bootstrapEmail = env('ADMIN_EMAIL');
        $bootstrapPassword = env('ADMIN_PASSWORD');
        $bootstrapName = env('ADMIN_NAME', 'Administrator');

        if (! $bootstrapEmail && ! app()->environment('production')) {
            $bootstrapEmail = 'admin@nizam.local';
            $bootstrapPassword = $bootstrapPassword ?: 'password';
            $bootstrapName = env('ADMIN_NAME', 'Admin User');
        }

        // 2. Admin user associated with the tenant
        if ($bootstrapEmail && $bootstrapPassword) {
            User::updateOrCreate(
                ['email' => $bootstrapEmail],
                [
                    'name' => $bootstrapName,
                    'password' => $bootstrapPassword,
                    'tenant_id' => $tenant->id,
                    'role' => 'admin',
                ]
            );
        }

        // 3. Extensions 1001-1005
        $extensionNames = [
            '1001' => ['Alice', 'Smith'],
            '1002' => ['Bob', 'Johnson'],
            '1003' => ['Carol', 'Williams'],
            '1004' => ['Dave', 'Brown'],
            '1005' => ['Eve', 'Davis'],
        ];

        $extensions = [];
        foreach ($extensionNames as $ext => [$first, $last]) {
            $extensions[$ext] = Extension::firstOrCreate(
                ['tenant_id' => $tenant->id, 'extension' => $ext],
                [
                    'password' => 'pass'.$ext,
                    'directory_first_name' => $first,
                    'directory_last_name' => $last,
                    'effective_caller_id_name' => "$first $last",
                    'effective_caller_id_number' => $ext,
                    'voicemail_enabled' => true,
                    'voicemail_pin' => $ext,
                    'is_active' => true,
                ]
            );
        }

        // 4. DIDs routed to extensions
        Did::create([
            'tenant_id' => $tenant->id,
            'number' => '+15551001001',
            'description' => 'Main line',
            'destination_type' => 'extension',
            'destination_id' => $extensions['1001']->id,
            'is_active' => true,
        ]);

        Did::create([
            'tenant_id' => $tenant->id,
            'number' => '+15551001002',
            'description' => 'Sales line',
            'destination_type' => 'extension',
            'destination_id' => $extensions['1002']->id,
            'is_active' => true,
        ]);

        // 5. Ring Group
        $ringGroup = RingGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Team',
            'strategy' => 'simultaneous',
            'ring_timeout' => 30,
            'members' => [
                $extensions['1001']->id,
                $extensions['1002']->id,
                $extensions['1003']->id,
            ],
            'is_active' => true,
        ]);

        // 6. IVR
        Ivr::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Menu',
            'greet_long' => 'ivr/demo_greet_long.wav',
            'greet_short' => 'ivr/demo_greet_short.wav',
            'timeout' => 5,
            'max_failures' => 3,
            'options' => [
                ['digit' => '1', 'destination_type' => 'extension', 'destination_id' => $extensions['1001']->id],
                ['digit' => '2', 'destination_type' => 'ring_group', 'destination_id' => $ringGroup->id],
            ],
            'is_active' => true,
        ]);

        // 7. Time Condition
        TimeCondition::create([
            'tenant_id' => $tenant->id,
            'name' => 'Business Hours',
            'conditions' => [
                [
                    'wday' => 'mon-fri',
                    'time_from' => '09:00',
                    'time_to' => '17:00',
                ],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extensions['1001']->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $extensions['1001']->id,
            'is_active' => true,
        ]);

        // 8. CDR records
        CallDetailRecord::create([
            'tenant_id' => $tenant->id,
            'uuid' => (string) str()->uuid(),
            'caller_id_name' => 'Alice Smith',
            'caller_id_number' => '+15551001001',
            'destination_number' => '1001',
            'context' => 'public',
            'start_stamp' => now()->subMinutes(15),
            'answer_stamp' => now()->subMinutes(15)->addSeconds(5),
            'end_stamp' => now()->subMinutes(12),
            'duration' => 180,
            'billsec' => 175,
            'hangup_cause' => 'NORMAL_CLEARING',
            'direction' => 'inbound',
            'recording_path' => null,
        ]);

        CallDetailRecord::create([
            'tenant_id' => $tenant->id,
            'uuid' => (string) str()->uuid(),
            'caller_id_name' => 'Bob Johnson',
            'caller_id_number' => '+15551001002',
            'destination_number' => '1002',
            'context' => 'public',
            'start_stamp' => now()->subMinutes(8),
            'answer_stamp' => now()->subMinutes(8)->addSeconds(4),
            'end_stamp' => now()->subMinutes(6),
            'duration' => 120,
            'billsec' => 116,
            'hangup_cause' => 'NORMAL_CLEARING',
            'direction' => 'inbound',
            'recording_path' => null,
        ]);

        // 9. Device Profile
        DeviceProfile::create([
            'tenant_id' => $tenant->id,
            'name' => 'Yealink T54W - Reception',
            'vendor' => 'yealink',
            'mac_address' => '00:15:65:A1:B2:C3',
            'extension_id' => $extensions['1001']->id,
            'is_active' => true,
        ]);

        // 10. Webhook
        Webhook::create([
            'tenant_id' => $tenant->id,
            'url' => 'https://demo.nizam.local/webhooks/calls',
            'events' => ['call.started', 'call.answered', 'call.hangup'],
            'secret' => 'demo-webhook-secret-key',
            'is_active' => true,
            'description' => 'Demo call event webhook',
        ]);

        $this->call(GraphFlowDemoSeeder::class);
    }
}
