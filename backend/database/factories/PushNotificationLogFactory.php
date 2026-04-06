<?php

namespace Database\Factories;

use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PushNotificationLog>
 */
class PushNotificationLogFactory extends Factory
{
    protected $model = PushNotificationLog::class;

    public function definition(): array
    {
        return [
            'call_session_id' => CallSession::factory(),
            'endpoint_binding_id' => EndpointBinding::factory(),
            'push_type' => 'wake',
            'provider_message_id' => fake()->uuid(),
            'status' => 'sent',
            'sent_at' => now(),
            'response_payload' => ['source' => 'factory'],
        ];
    }
}
