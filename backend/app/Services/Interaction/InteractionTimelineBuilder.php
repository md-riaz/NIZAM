<?php

namespace App\Services\Interaction;

use Carbon\CarbonInterface;

class InteractionTimelineBuilder
{
    /**
     * @param  array<int, array{type: string, occurred_at: CarbonInterface, details?: array<string, mixed>}>  $events
     * @return array<int, array{type: string, occurred_at: string, details: array<string, mixed>, duration_seconds: int, duration_label: string}>
     */
    public function build(array $events): array
    {
        $indexedEvents = array_map(
            fn (array $event, int $index): array => $event + ['__input_index' => $index],
            $events,
            array_keys($events),
        );

        usort($indexedEvents, function (array $left, array $right): int {
            $timestampComparison = $left['occurred_at'] <=> $right['occurred_at'];

            if ($timestampComparison !== 0) {
                return $timestampComparison;
            }

            return ($left['__input_index'] ?? 0) <=> ($right['__input_index'] ?? 0);
        });

        $timeline = [];
        $count = count($indexedEvents);

        foreach ($indexedEvents as $index => $event) {
            $nextEvent = $indexedEvents[$index + 1] ?? null;
            $durationSeconds = $nextEvent
                ? max(0, $event['occurred_at']->diffInSeconds($nextEvent['occurred_at'], false))
                : 0;

            $timeline[] = [
                'type' => $event['type'],
                'occurred_at' => $event['occurred_at']->toIso8601String(),
                'details' => $event['details'] ?? [],
                'duration_seconds' => $durationSeconds,
                'duration_label' => $this->formatDurationLabel($durationSeconds, $index === $count - 1),
            ];
        }

        return $timeline;
    }

    private function formatDurationLabel(int $durationSeconds, bool $isLastEvent): string
    {
        if ($isLastEvent) {
            return 'Completed';
        }

        return sprintf('%02dm %02ds', intdiv($durationSeconds, 60), $durationSeconds % 60);
    }
}
