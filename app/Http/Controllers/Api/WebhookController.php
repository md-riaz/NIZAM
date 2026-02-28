<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebhookRequest;
use App\Http\Requests\UpdateWebhookRequest;
use App\Http\Resources\WebhookDeliveryAttemptResource;
use App\Http\Resources\WebhookResource;
use App\Models\Tenant;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * API controller for managing webhooks scoped to a tenant.
 */
class WebhookController extends Controller
{
    /**
     * List webhooks for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', Webhook::class);

        return WebhookResource::collection($tenant->webhooks()->paginate(15));
    }

    /**
     * Create a new webhook for a tenant.
     */
    public function store(StoreWebhookRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', Webhook::class);

        $data = $request->validated();

        if (empty($data['secret'])) {
            $data['secret'] = Str::random(32);
        }

        $webhook = $tenant->webhooks()->create($data);

        return (new WebhookResource($webhook))
            ->additional(['secret' => $webhook->secret])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single webhook.
     */
    public function show(Tenant $tenant, Webhook $webhook): JsonResponse|WebhookResource
    {
        if ($webhook->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $this->authorize('view', $webhook);

        return new WebhookResource($webhook);
    }

    /**
     * Update an existing webhook.
     */
    public function update(UpdateWebhookRequest $request, Tenant $tenant, Webhook $webhook): JsonResponse|WebhookResource
    {
        if ($webhook->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $this->authorize('update', $webhook);

        $webhook->update($request->validated());

        return new WebhookResource($webhook);
    }

    /**
     * Delete a webhook.
     */
    public function destroy(Tenant $tenant, Webhook $webhook): JsonResponse
    {
        if ($webhook->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $this->authorize('delete', $webhook);

        $webhook->delete();

        return response()->json(null, 204);
    }

    /**
     * List delivery attempts for a webhook (paginated).
     */
    public function deliveryAttempts(Tenant $tenant, Webhook $webhook)
    {
        if ($webhook->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $this->authorize('view', $webhook);

        return WebhookDeliveryAttemptResource::collection(
            $webhook->deliveryAttempts()->orderByDesc('created_at')->paginate(15)
        );
    }

    /**
     * Get delivery statistics for a webhook.
     */
    public function deliveryStats(Tenant $tenant, Webhook $webhook): JsonResponse
    {
        if ($webhook->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $this->authorize('view', $webhook);

        $attempts = $webhook->deliveryAttempts();
        $total = $attempts->count();
        $successful = $webhook->deliveryAttempts()->where('success', true)->count();
        $failed = $total - $successful;
        $successRate = $total > 0 ? round(($successful / $total) * 100, 2) : 0;
        $avgLatency = $webhook->deliveryAttempts()->where('success', true)->avg('latency_ms');

        $recentFailures = $webhook->deliveryAttempts()
            ->where('success', false)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['event_type', 'error_message', 'response_status', 'created_at']);

        return response()->json([
            'data' => [
                'total_attempts' => $total,
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => $successRate,
                'avg_latency_ms' => $avgLatency ? round((float) $avgLatency, 2) : null,
                'recent_failures' => $recentFailures,
            ],
        ]);
    }
}
