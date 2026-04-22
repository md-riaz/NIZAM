<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Extension;
use App\Models\Queue;
use App\Models\Team;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class AgentQueueTeamSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('domain', env('ADMIN_ORGANIZATION_DOMAIN', 'app.local'))->first();

        if (!$organization) {
            $this->command->error('Organization not found. Run DatabaseSeeder first.');
            return;
        }

        $extensions = Extension::where('organization_id', $organization->id)->get();

        if ($extensions->isEmpty()) {
            $this->command->error('No extensions found for organization.');
            return;
        }

        // Create Teams
        $salesTeam = Team::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Sales Heroes'],
            ['strategy' => 'ring_all', 'timeout' => 30, 'is_active' => true]
        );

        $supportTeam = Team::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Tech Support Ninjas'],
            ['strategy' => 'round_robin', 'timeout' => 25, 'is_active' => true]
        );

        // Create Agents
        $agent1 = Agent::firstOrCreate(
            ['organization_id' => $organization->id, 'extension_id' => $extensions[0]->id],
            ['name' => 'Agent ' . $extensions[0]->first_name, 'role' => 'agent', 'state' => 'available', 'is_active' => true]
        );

        $agent2 = Agent::firstOrCreate(
            ['organization_id' => $organization->id, 'extension_id' => $extensions[1]->id],
            ['name' => 'Agent ' . $extensions[1]->first_name, 'role' => 'agent', 'state' => 'available', 'is_active' => true]
        );

        $agent3 = Agent::firstOrCreate(
            ['organization_id' => $organization->id, 'extension_id' => $extensions[2]->id],
            ['name' => 'Supervisor ' . $extensions[2]->first_name, 'role' => 'supervisor', 'state' => 'available', 'is_active' => true]
        );


        // Create Queues - ADD service_level_threshold
        $salesQueue = Queue::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Inbound Sales Queue'],
            [
                'strategy' => 'ring_all',
                'max_wait_time' => 300,
                'overflow_action' => 'voicemail',
                'service_level_threshold' => 30,
                'is_active' => true
            ]
        );

        $supportQueue = Queue::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Tier 1 Support Queue'],
            [
                'strategy' => 'least_recent',
                'max_wait_time' => 600,
                'overflow_action' => 'voicemail',
                'service_level_threshold' => 60,
                'is_active' => true
            ]
        );

        // Attach agents to queues
        $salesQueue->members()->syncWithoutDetaching([
            $agent1->id => ['priority' => 1],
            $agent3->id => ['priority' => 5],
        ]);

        $supportQueue->members()->syncWithoutDetaching([
            $agent2->id => ['priority' => 1],
            $agent3->id => ['priority' => 5],
        ]);

        $this->command->info('Queues, Teams, and Agents seeded successfully!');
    }
}
