<?php

namespace App\Services;

use App\Events\ContactCenterEvent;
use App\Models\Agent;
use App\Models\Queue;
use App\Models\QueueEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class QueueService
{
    /**
     * Add a call to a queue.
     */
    public function addToQueue(Queue $queue, string $callUuid, array $callerData = []): QueueEntry
    {
        $entry = QueueEntry::create([
            'tenant_id' => $queue->tenant_id,
            'queue_id' => $queue->id,
            'call_uuid' => $callUuid,
            'caller_id_number' => $callerData['caller_id_number'] ?? null,
            'caller_id_name' => $callerData['caller_id_name'] ?? null,
            'status' => QueueEntry::STATUS_WAITING,
            'join_time' => now(),
        ]);

        ContactCenterEvent::dispatch($queue->tenant_id, 'queue.call_joined', [
            'queue_id' => $queue->id,
            'queue_name' => $queue->name,
            'call_uuid' => $callUuid,
            'position' => $queue->waitingEntries()->count(),
        ]);

        Log::debug('Call added to queue', ['queue' => $queue->name, 'call_uuid' => $callUuid]);

        return $entry;
    }

    /**
     * Select the next available agent for a queue based on its strategy.
     */
    public function selectAgent(Queue $queue): ?Agent
    {
        $availableAgents = $this->getAvailableAgents($queue);

        if ($availableAgents->isEmpty()) {
            return null;
        }

        return match ($queue->strategy) {
            Queue::STRATEGY_RING_ALL => $availableAgents->first(),
            Queue::STRATEGY_ROUND_ROBIN => $this->roundRobinSelect($queue, $availableAgents),
            Queue::STRATEGY_LEAST_RECENT => $this->leastRecentSelect($queue, $availableAgents),
            default => $availableAgents->first(),
        };
    }

    /**
     * Get all available agents for ring-all strategy.
     */
    public function getAgentsForRingAll(Queue $queue): Collection
    {
        return $this->getAvailableAgents($queue);
    }

    /**
     * Answer a queue entry — assign agent and mark answered.
     */
    public function answerEntry(QueueEntry $entry, Agent $agent): void
    {
        $now = now();
        $waitDuration = $entry->join_time->diffInSeconds($now);

        $entry->update([
            'status' => QueueEntry::STATUS_ANSWERED,
            'agent_id' => $agent->id,
            'answer_time' => $now,
            'wait_duration' => $waitDuration,
        ]);

        $agent->transitionState(Agent::STATE_BUSY);

        ContactCenterEvent::dispatch($entry->tenant_id, 'queue.call_answered', [
            'queue_id' => $entry->queue_id,
            'call_uuid' => $entry->call_uuid,
            'agent_id' => $agent->id,
            'wait_duration' => $waitDuration,
        ]);

        Log::debug('Queue call answered', [
            'queue_id' => $entry->queue_id,
            'agent_id' => $agent->id,
            'wait_duration' => $waitDuration,
        ]);
    }

    /**
     * Mark a queue entry as abandoned.
     */
    public function abandonEntry(QueueEntry $entry, string $reason = 'caller_hangup'): void
    {
        $now = now();
        $waitDuration = $entry->join_time->diffInSeconds($now);

        $entry->update([
            'status' => QueueEntry::STATUS_ABANDONED,
            'abandon_time' => $now,
            'wait_duration' => $waitDuration,
            'abandon_reason' => $reason,
        ]);

        ContactCenterEvent::dispatch($entry->tenant_id, 'queue.call_abandoned', [
            'queue_id' => $entry->queue_id,
            'call_uuid' => $entry->call_uuid,
            'wait_duration' => $waitDuration,
            'reason' => $reason,
        ]);

        Log::debug('Queue call abandoned', [
            'queue_id' => $entry->queue_id,
            'call_uuid' => $entry->call_uuid,
            'reason' => $reason,
        ]);
    }

    /**
     * Handle overflow for a queue entry.
     */
    public function overflowEntry(QueueEntry $entry): void
    {
        $now = now();
        $waitDuration = $entry->join_time->diffInSeconds($now);

        $entry->update([
            'status' => QueueEntry::STATUS_OVERFLOWED,
            'abandon_time' => $now,
            'wait_duration' => $waitDuration,
            'abandon_reason' => 'max_wait_exceeded',
        ]);

        ContactCenterEvent::dispatch($entry->tenant_id, 'queue.call_overflowed', [
            'queue_id' => $entry->queue_id,
            'call_uuid' => $entry->call_uuid,
            'wait_duration' => $waitDuration,
        ]);

        Log::debug('Queue call overflowed', [
            'queue_id' => $entry->queue_id,
            'call_uuid' => $entry->call_uuid,
        ]);
    }

    /**
     * Get entries that have exceeded their queue's max wait time.
     */
    public function getOverflowCandidates(Queue $queue): Collection
    {
        $maxWait = $queue->max_wait_time;

        return $queue->waitingEntries()
            ->where('join_time', '<=', now()->subSeconds($maxWait))
            ->orderBy('join_time')
            ->get();
    }

    /**
     * Get available agents for a queue.
     */
    protected function getAvailableAgents(Queue $queue): Collection
    {
        return $queue->members()
            ->where('state', Agent::STATE_AVAILABLE)
            ->where('is_active', true)
            ->orderBy('queue_members.priority')
            ->get();
    }

    /**
     * Round-robin agent selection.
     */
    protected function roundRobinSelect(Queue $queue, Collection $agents): Agent
    {
        $lastAnswered = QueueEntry::where('queue_id', $queue->id)
            ->where('status', QueueEntry::STATUS_ANSWERED)
            ->whereNotNull('agent_id')
            ->orderByDesc('answer_time')
            ->first();

        if (! $lastAnswered) {
            return $agents->first();
        }

        $lastIndex = $agents->search(fn (Agent $a) => $a->id === $lastAnswered->agent_id);

        if ($lastIndex === false || $lastIndex >= $agents->count() - 1) {
            return $agents->first();
        }

        return $agents->values()->get($lastIndex + 1);
    }

    /**
     * Least-recent agent selection — pick agent who hasn't answered a call the longest.
     */
    protected function leastRecentSelect(Queue $queue, Collection $agents): Agent
    {
        $agentIds = $agents->pluck('id');

        $lastAnswerTimes = QueueEntry::where('queue_id', $queue->id)
            ->where('status', QueueEntry::STATUS_ANSWERED)
            ->whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, MAX(answer_time) as last_answer')
            ->groupBy('agent_id')
            ->pluck('last_answer', 'agent_id');

        return $agents->sortBy(function (Agent $agent) use ($lastAnswerTimes) {
            return $lastAnswerTimes->get($agent->id, '1970-01-01');
        })->first();
    }
}
