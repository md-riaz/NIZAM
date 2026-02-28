<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebhookRequest;
use App\Http\Requests\UpdateWebhookRequest;
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
        return WebhookResource::collection($tenant->webhooks()->paginate(15));
    }

    /**
     * Create a new webhook for a tenant.
     */
    public function store(StoreWebhookRequest $request, Tenant $tenant): JsonResponse
    {
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

        $webhook->delete();

        return response()->json(null, 204);
    }
}
