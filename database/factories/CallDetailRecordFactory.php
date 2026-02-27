<?php

namespace Database\Factories;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CallDetailRecord>
 */
class CallDetailRecordFactory extends Factory
{
    protected $model = CallDetailRecord::class;

    public function definition(): array
    {
        $startStamp = fake()->dateTimeBetween('-30 days', 'now');
        $duration = fake()->numberBetween(0, 600);
        $billsec = fake()->numberBetween(0, $duration);
        $answered = fake()->boolean(70);

        $answerStamp = $answered
            ? (clone $startStamp)->modify('+' . fake()->numberBetween(1, 10) . ' seconds')
            : null;

        $endStamp = (clone ($answerStamp ?? $startStamp))
            ->modify('+' . $duration . ' seconds');

        return [
            'tenant_id' => Tenant::factory(),
            'uuid' => fake()->unique()->uuid(),
            'caller_id_name' => fake()->optional(0.7)->name(),
            'caller_id_number' => '+1' . fake()->numerify('##########'),
            'destination_number' => fake()->randomElement([
                (string) fake()->numberBetween(1000, 9999),
                '+1' . fake()->numerify('##########'),
            ]),
            'context' => fake()->optional(0.5)->word(),
            'start_stamp' => $startStamp,
            'answer_stamp' => $answerStamp,
            'end_stamp' => $endStamp,
            'duration' => $duration,
            'billsec' => $billsec,
            'hangup_cause' => fake()->randomElement([
                'NORMAL_CLEARING', 'USER_BUSY', 'NO_ANSWER', 'ORIGINATOR_CANCEL',
            ]),
            'direction' => fake()->randomElement(['inbound', 'outbound', 'local']),
            'recording_path' => fake()->optional(0.3)->filePath(),
        ];
    }
}
