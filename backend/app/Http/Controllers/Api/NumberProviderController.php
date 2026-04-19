<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NumberProviderUpsertRequest;
use App\Http\Resources\DidResource;
use App\Models\Bridge;
use App\Models\Did;
use App\Models\Gateway;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NumberProviderController extends Controller
{
    public function store(NumberProviderUpsertRequest $request, Organization $organization, Did $did): JsonResponse|DidResource
    {
        if ($did->organization_id !== $organization->id) {
            return response()->json(['message' => 'Number not found.'], 404);
        }

        $this->authorize('update', $did);
        $this->authorize('create', Gateway::class);

        if ($did->gateway_id !== null) {
            return response()->json(['message' => 'Provider already exists for this number.'], 422);
        }

        $did = DB::transaction(function () use ($request, $organization, $did) {
            $gateway = $organization->gateways()->create([
                ...$request->validated(),
                'profile' => 'external',
            ]);

            $did->forceFill(['gateway_id' => $gateway->id])->save();

            return $did->fresh()->load('gateway');
        });

        return (new DidResource($did))->response()->setStatusCode(201);
    }

    public function update(NumberProviderUpsertRequest $request, Organization $organization, Did $did): JsonResponse|DidResource
    {
        if ($did->organization_id !== $organization->id) {
            return response()->json(['message' => 'Number not found.'], 404);
        }

        $this->authorize('update', $did);

        $gateway = $did->gateway;

        if (! $gateway || $gateway->organization_id !== $organization->id) {
            return response()->json(['message' => 'Provider not found.'], 404);
        }

        $this->authorize('update', $gateway);

        $did = DB::transaction(function () use ($request, $did, $gateway) {
            $gateway->update($request->validated());

            if ($did->gateway_id !== $gateway->id) {
                $did->forceFill(['gateway_id' => $gateway->id])->save();
            }

            return $did->fresh()->load('gateway');
        });

        return new DidResource($did);
    }

    public function destroy(Organization $organization, Did $did): JsonResponse|DidResource
    {
        if ($did->organization_id !== $organization->id) {
            return response()->json(['message' => 'Number not found.'], 404);
        }

        $this->authorize('update', $did);

        $gateway = $did->gateway;

        $did = DB::transaction(function () use ($did, $gateway) {
            $did->forceFill(['gateway_id' => null])->save();

            if ($gateway && $gateway->organization_id === $did->organization_id) {
                $gatewayStillInUse = Did::query()
                    ->where('gateway_id', $gateway->id)
                    ->exists()
                    || Bridge::query()->where('gateway_id', $gateway->id)->exists();

                if (! $gatewayStillInUse) {
                    $this->authorize('delete', $gateway);
                    $gateway->delete();
                }
            }

            return $did->fresh()->load('gateway');
        });

        return new DidResource($did);
    }
}
