<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Extension;
use App\Models\Queue;
use App\Models\Team;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AgentQueueTeamSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'app-communications')->first();

        if (!$tenant) {
            $this->command->error('Tenant not found. Run DatabaseSeeder first.');
            return;
        }

        $extensions = Extension::where('tenant_id', $tenant->id)->get();

        if ($extensions->isEmpty()) {
            $this->command->error('No extensions found for tenant.');
            return;
        }

        // Create Teams
        $salesTeam = Team::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Sales Heroes'],
            ['strategy' => 'ring_all', 'timeout' => 30, 'is_active' => true]
        );

        $supportTeam = Team::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Tech Support Ninjas'],
            ['strategy' => 'round_robin', 'timeout' => 25, 'is_active' => true]
        );

        // Create Agents
        $agent1 = Agent::firstOrCreate(
            ['tenant_id' => $tenant->id, 'extension_id' => $extensions[0]->id],
            ['name' => 'Agent ' . $extensions[0]->directory_first_name, 'role' => 'agent', 'state' => 'available', 'is_active' => true]
        );

        $agent2 = Agent::firstOrCreate(
            ['tenant_id' => $tenant->id, 'extension_id' => $extensions[1]->id],
            ['name' => 'Agent ' . $extensions[1]->directory_first_name, 'role' => 'agent', 'state' => 'available', 'is_active' => true]
        );

        $agent3 = Agent::firstOrCreate(
            ['tenant_id' => $tenant->id, 'extension_id' => $extensions[2]->id],
            ['name' => 'Supervisor ' . $extensions[2]->directory_first_name, 'role' => 'supervisor', 'state' => 'available', 'is_active' => true]
        );


        // Create Queues - ADD service_level_threshold
        $salesQueue = Queue::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Inbound Sales Queue'],
            [
                'strategy' => 'ring_all',
                'max_wait_time' => 300,
                'overflow_action' => 'voicemail',
                'service_level_threshold' => 30,
                'is_active' => true
            ]
        );

        $supportQueue = Queue::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Tier 1 Support Queue'],
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
