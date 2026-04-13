<?php

namespace Tests\Unit\Services\Interaction;

use App\Services\Interaction\InteractionTimelineBuilder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InteractionTimelineBuilderTest extends TestCase
{
    public function test_it_builds_ordered_timeline_segments_from_mixed_events(): void
    {
        $builder = new InteractionTimelineBuilder;

        $timeline = $builder->build([
            ['type' => 'call.started', 'occurred_at' => Carbon::parse('2026-04-12 10:00:00'), 'details' => ['label' => 'Call started']],
            ['type' => 'dialplan.entered', 'occurred_at' => Carbon::parse('2026-04-12 10:00:05'), 'details' => ['label' => 'Dial plan initiated']],
            ['type' => 'call.connected', 'occurred_at' => Carbon::parse('2026-04-12 10:01:00'), 'details' => ['label' => 'Connected']],
        ]);

        $this->assertCount(3, $timeline);
        $this->assertSame('call.started', $timeline[0]['type']);
        $this->assertSame('dialplan.entered', $timeline[1]['type']);
        $this->assertSame('call.connected', $timeline[2]['type']);
        $this->assertSame('00m 55s', $timeline[1]['duration_label']);
    }

    public function test_it_preserves_original_input_order_for_equal_timestamps(): void
    {
        $builder = new InteractionTimelineBuilder;

        $timeline = $builder->build([
            ['type' => 'call.started', 'occurred_at' => Carbon::parse('2026-04-12 10:00:00'), 'details' => ['label' => 'Call started']],
            ['type' => 'dialplan.entered', 'occurred_at' => Carbon::parse('2026-04-12 10:00:05'), 'details' => ['label' => 'Dial plan initiated']],
            ['type' => 'ai.receptionist', 'occurred_at' => Carbon::parse('2026-04-12 10:00:05'), 'details' => ['label' => 'AI receptionist']],
            ['type' => 'call.connected', 'occurred_at' => Carbon::parse('2026-04-12 10:01:00'), 'details' => ['label' => 'Connected']],
        ]);

        $this->assertSame('dialplan.entered', $timeline[1]['type']);
        $this->assertSame('ai.receptionist', $timeline[2]['type']);
        $this->assertSame('00m 00s', $timeline[1]['duration_label']);
        $this->assertSame('00m 55s', $timeline[2]['duration_label']);
    }

}
