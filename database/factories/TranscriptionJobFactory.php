<?php

namespace Database\Factories;

use App\Models\Recording;
use App\Models\Tenant;
use App\Models\TranscriptionJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TranscriptionJob>
 */
class TranscriptionJobFactory extends Factory
{
    protected $model = TranscriptionJob::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'recording_id' => Recording::factory(),
            'status' => TranscriptionJob::STATUS_PENDING,
            'provider' => null,
            'transcript_text' => null,
            'transcript_timing' => null,
            'language' => 'en',
            'attempts' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TranscriptionJob::STATUS_COMPLETED,
            'transcript_text' => fake()->paragraphs(3, true),
            'completed_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => TranscriptionJob::STATUS_FAILED,
            'error_message' => 'Provider unavailable',
            'attempts' => 1,
        ]);
    }
}
