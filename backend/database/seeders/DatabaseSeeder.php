<?php

namespace Database\Seeders;

use App\Models\CallDetailRecord;
use App\Models\DeviceProfile;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Ivr;
use App\Models\RingGroup;
use App\Models\Organization;
use App\Models\TimeCondition;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Bootstrap platform with a superadmin account
     * and a default organization with production-ready seed data.
     */
    public function run(): void
    {
        // ──────────────────────────────────────────────
        // 1. Platform Superadmin (organization-agnostic)
        // ──────────────────────────────────────────────
        $systemDomain = env('ADMIN_ORGANIZATION_DOMAIN', 'app.local');
        User::updateOrCreate(
            ['email' => "system@{$systemDomain}"],
            [
                'name'      => 'Platform Administrator',
                'password'  => env('ADMIN_PASSWORD', 'password'),
                'organization_id' => null,          // Platform-level — not scoped to any organization
                'role'      => 'superadmin',  // Full cross-organization access
            ]
        );

        // ──────────────────────────────────────────────
        // 2. Default Organization
        // ──────────────────────────────────────────────
        $organizationDomain = env('ADMIN_ORGANIZATION_DOMAIN', 'app.local');
        $organization = Organization::updateOrCreate(
            ['domain' => $organizationDomain],
            [
                'name'           => env('ADMIN_ORGANIZATION_NAME', ucfirst(strtok($organizationDomain, '.')) ?: 'Default Organization'),
                'settings'       => [],
                'max_extensions' => 100,
                'is_active'      => true,
            ]
        );

        // ──────────────────────────────────────────────
        // 3. Organization Admin User
        // ──────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@app.local')],
            [
                'name'      => env('ADMIN_NAME', 'App Administrator'),
                'password'  => env('ADMIN_PASSWORD', 'password'),
                'organization_id' => $organization->id,
                'role'      => 'admin',
            ]
        );

        // ──────────────────────────────────────────────
        // 4. Extensions — Office Staff
        // ──────────────────────────────────────────────
        $staff = [
            '1001' => ['Fatima',  'Rahman',  'CEO / Founder'],
            '1002' => ['Karim',   'Hassan',  'Operations Manager'],
            '1003' => ['Ayesha',  'Malik',   'Sales Lead'],
            '1004' => ['Tariq',   'Uddin',   'Support Engineer'],
            '1005' => ['Nadia',   'Hossain', 'Finance & Billing'],
            '1006' => ['Imran',   'Ali',     'Network Engineer'],
            '1007' => ['Sabrina', 'Khan',    'Customer Success'],
            '1008' => ['Rashid',  'Ahmed',   'Field Technician'],
        ];

        $extensions = [];
        foreach ($staff as $ext => [$first, $last, $title]) {
            $extensions[$ext] = Extension::firstOrCreate(
                ['organization_id' => $organization->id, 'extension' => $ext],
                [
                    'password'                    => 'Nzm' . $ext . '!',
                    'first_name'        => $first,
                    'last_name'         => $last,
                    'effective_caller_id_name'    => "$first $last",
                    'effective_caller_id_number'  => $ext,
                    'outbound_caller_id_name'     => $organization->name,
                    'outbound_caller_id_number'   => '+8801700000001',
                    'voicemail_enabled'           => true,
                    'voicemail_pin'               => substr(str_shuffle('123456789'), 0, 4),
                    'is_active'                   => true,
                ]
            );
        }

        // ──────────────────────────────────────────────
        // 5. Ring Groups (created BEFORE DIDs so IDs are available)
        // ──────────────────────────────────────────────
        $salesRing = RingGroup::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Sales Team'],
            [
                'strategy'     => 'simultaneous',
                'ring_timeout' => 25,
                'members'      => [
                    $extensions['1003']->id,
                    $extensions['1007']->id,
                ],
                'is_active' => true,
            ]
        );

        $supportRing = RingGroup::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Technical Support'],
            [
                'strategy'     => 'sequence',
                'ring_timeout' => 20,
                'members'      => [
                    $extensions['1004']->id,
                    $extensions['1006']->id,
                    $extensions['1008']->id,
                ],
                'is_active' => true,
            ]
        );

        // ──────────────────────────────────────────────
        // 6. IVR — Main Auto-Attendant (created BEFORE DIDs)
        // ──────────────────────────────────────────────
        $mainIvr = Ivr::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Main Auto-Attendant'],
            [
                'greet_long'   => 'ivr/welcome.wav',
                'greet_short'  => 'ivr/welcome_short.wav',
                'timeout'      => 5,
                'max_failures' => 3,
                'options'       => [
                    ['digit' => '1', 'destination_type' => 'ring_group',  'destination_id' => $salesRing->id],
                    ['digit' => '2', 'destination_type' => 'ring_group',  'destination_id' => $supportRing->id],
                    ['digit' => '3', 'destination_type' => 'extension',   'destination_id' => $extensions['1005']->id],
                    ['digit' => '0', 'destination_type' => 'extension',   'destination_id' => $extensions['1002']->id],
                ],
                'is_active' => true,
            ]
        );

        // ──────────────────────────────────────────────
        // 7. DIDs — Inbound Numbers (all destination_ids now available)
        // ──────────────────────────────────────────────
        Did::firstOrCreate(
            ['organization_id' => $organization->id, 'number' => '+8801700000001'],
            [
                'description'      => 'Main Office Line',
                'destination_type' => 'ivr',
                'destination_id'   => $mainIvr->id,
                'is_active'        => true,
            ]
        );

        Did::firstOrCreate(
            ['organization_id' => $organization->id, 'number' => '+8801700000002'],
            [
                'description'      => 'Sales Direct Line',
                'destination_type' => 'ring_group',
                'destination_id'   => $salesRing->id,
                'is_active'        => true,
            ]
        );

        Did::firstOrCreate(
            ['organization_id' => $organization->id, 'number' => '+8801700000003'],
            [
                'description'      => 'Support Hotline',
                'destination_type' => 'extension',
                'destination_id'   => $extensions['1004']->id,
                'is_active'        => true,
            ]
        );

        Did::firstOrCreate(
            ['organization_id' => $organization->id, 'number' => '+8801700000004'],
            [
                'description'      => 'Fax / Billing Line',
                'destination_type' => 'extension',
                'destination_id'   => $extensions['1005']->id,
                'is_active'        => true,
            ]
        );

        // ──────────────────────────────────────────────
        // 8. Time Condition — Business Hours
        // ──────────────────────────────────────────────
        TimeCondition::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Business Hours'],
            [
                'conditions' => [
                    [
                        'wday'      => 'sun-thu',   // Bangladesh work week
                        'time_from' => '09:00',
                        'time_to'   => '18:00',
                    ],
                ],
                'match_destination_type'    => 'ivr',
                'match_destination_id'      => $mainIvr->id,
                'no_match_destination_type' => 'voicemail',
                'no_match_destination_id'   => $extensions['1001']->id,
                'is_active'                 => true,
            ]
        );

        // ──────────────────────────────────────────────
        // 9. CDR — Recent Call History
        // ──────────────────────────────────────────────
        $callLogs = [
            [
                'caller_name'   => 'External Caller',
                'caller_number' => '+8801912345678',
                'destination'   => '1001',
                'direction'     => 'inbound',
                'duration'      => 245,
                'billsec'       => 238,
                'hangup_cause'  => 'NORMAL_CLEARING',
                'minutes_ago'   => 12,
            ],
            [
                'caller_name'   => 'Ayesha Malik',
                'caller_number' => '1003',
                'destination'   => '+8801855667788',
                'direction'     => 'outbound',
                'duration'      => 180,
                'billsec'       => 174,
                'hangup_cause'  => 'NORMAL_CLEARING',
                'minutes_ago'   => 35,
            ],
            [
                'caller_name'   => 'Customer Inquiry',
                'caller_number' => '+8801711223344',
                'destination'   => '1004',
                'direction'     => 'inbound',
                'duration'      => 420,
                'billsec'       => 415,
                'hangup_cause'  => 'NORMAL_CLEARING',
                'minutes_ago'   => 58,
            ],
            [
                'caller_name'   => 'Vendor - Telco BD',
                'caller_number' => '+8802123456789',
                'destination'   => '1002',
                'direction'     => 'inbound',
                'duration'      => 0,
                'billsec'       => 0,
                'hangup_cause'  => 'NO_ANSWER',
                'minutes_ago'   => 90,
            ],
            [
                'caller_name'   => 'Imran Ali',
                'caller_number' => '1006',
                'destination'   => '+8801600112233',
                'direction'     => 'outbound',
                'duration'      => 95,
                'billsec'       => 90,
                'hangup_cause'  => 'NORMAL_CLEARING',
                'minutes_ago'   => 120,
            ],
        ];

        foreach ($callLogs as $log) {
            CallDetailRecord::firstOrCreate(
                [
                    'organization_id'          => $organization->id,
                    'caller_id_number'   => $log['caller_number'],
                    'destination_number' => $log['destination'],
                    'start_stamp'        => now()->subMinutes($log['minutes_ago']),
                ],
                [
                    'uuid'               => (string) str()->uuid(),
                    'caller_id_name'     => $log['caller_name'],
                    'context'            => $log['direction'] === 'inbound' ? 'public' : 'default',
                    'answer_stamp'       => $log['billsec'] > 0 ? now()->subMinutes($log['minutes_ago'])->addSeconds(5) : null,
                    'end_stamp'          => now()->subMinutes($log['minutes_ago'])->addSeconds($log['duration']),
                    'duration'           => $log['duration'],
                    'billsec'            => $log['billsec'],
                    'hangup_cause'       => $log['hangup_cause'],
                    'direction'          => $log['direction'],
                    'recording_path'     => null,
                ]
            );
        }

        // ──────────────────────────────────────────────
        // 10. Device Profiles — Provisioned Phones
        // ──────────────────────────────────────────────
        DeviceProfile::firstOrCreate(
            ['organization_id' => $organization->id, 'mac_address' => '00:15:65:A1:B2:C3'],
            [
                'name'         => 'Yealink T54W — Reception (Fatima)',
                'vendor'       => 'yealink',
                'extension_id' => $extensions['1001']->id,
                'is_active'    => true,
            ]
        );

        DeviceProfile::firstOrCreate(
            ['organization_id' => $organization->id, 'mac_address' => '00:15:65:D4:E5:F6'],
            [
                'name'         => 'Yealink T46U — Operations (Karim)',
                'vendor'       => 'yealink',
                'extension_id' => $extensions['1002']->id,
                'is_active'    => true,
            ]
        );

        DeviceProfile::firstOrCreate(
            ['organization_id' => $organization->id, 'mac_address' => '80:5E:C0:11:22:33'],
            [
                'name'         => 'Fanvil X6U — Support Desk (Tariq)',
                'vendor'       => 'fanvil',
                'extension_id' => $extensions['1004']->id,
                'is_active'    => true,
            ]
        );

        // ──────────────────────────────────────────────
        // 11. Webhooks — Event Integrations
        // ──────────────────────────────────────────────
        Webhook::firstOrCreate(
            ['organization_id' => $organization->id, 'url' => sprintf('https://%s/api/webhooks/calls', $organizationDomain)],
            [
                'events'      => ['call.created', 'call.answered', 'call.hangup'],
                'secret'      => bin2hex(random_bytes(16)),
                'is_active'   => true,
                'description' => 'Internal call event notifications',
            ]
        );

        // ──────────────────────────────────────────────
        // 12. Graph Flow Demo Data
        // ──────────────────────────────────────────────
        $this->call(GraphFlowDemoSeeder::class);

        // ──────────────────────────────────────────────
        // 13. System SIP Profiles
        // ──────────────────────────────────────────────
        $this->call(SipProfileSeeder::class);
    }
}
