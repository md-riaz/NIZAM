<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\Organization\OrganizationBootstrapService;
use App\Services\OrganizationEntrypointProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API controller for managing organizations.
 */
class OrganizationController extends Controller
{
    public function __construct(
        protected OrganizationBootstrapService $organizationBootstrapService,
        protected OrganizationEntrypointProvisioningService $organizationEntrypointProvisioningService,
    ) {}

    /**
     * List all organizations (paginated).
     */
    public function index()
    {
        $this->authorize('viewAny', Organization::class);

        $user = request()->user();
        $query = Organization::query()
            ->with([
                'defaultSchedule',
                'defaultHolidayCalendar',
                'dids',
                'teams',
                'flows.activeVersion',
                'extensions',
                'activeInboundRoutingManifest',
            ])
            ->orderByDesc('id');

        if ($user->isSuperadmin()) {
            return OrganizationResource::collection($query->paginate(15));
        }

        return OrganizationResource::collection(
            $query->where('id', $user->organization_id)->paginate(15)
        );
    }

    /**
     * Create a new organization.
     */
    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $organization = DB::transaction(function () use ($request) {
            $organization = Organization::create($request->validated());
            $organization = $this->organizationBootstrapService->provisionDefaults($organization);
            $organization = $this->organizationEntrypointProvisioningService->provision($organization);

            return $organization->fresh();
        });

        return (new OrganizationResource($organization->loadMissing([
            'defaultSchedule',
            'defaultHolidayCalendar',
            'dids',
            'teams',
            'flows.activeVersion',
            'extensions',
            'activeInboundRoutingManifest',
        ])))->response()->setStatusCode(201);
    }

    /**
     * Show a single organization.
     */
    public function show(Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        return new OrganizationResource($organization->loadMissing([
            'defaultSchedule',
            'defaultHolidayCalendar',
            'dids',
            'teams',
            'flows.activeVersion',
            'extensions',
            'activeInboundRoutingManifest',
        ]));
    }

    /**
     * Update an existing organization.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): OrganizationResource
    {
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return new OrganizationResource($organization->loadMissing([
            'defaultSchedule',
            'defaultHolidayCalendar',
            'dids',
            'teams',
            'flows.activeVersion',
            'extensions',
            'activeInboundRoutingManifest',
        ]));
    }

    /**
     * Delete an organization.
     */
    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->json(null, 204);
    }

    /**
     * Get organization settings.
     */
    public function settings(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'data' => $organization->settings ?? [],
        ]);
    }

    /**
     * Merge-update organization settings.
     */
    public function updateSettings(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $organization->update([
            'settings' => array_merge($organization->settings ?? [], $validated['settings']),
        ]);

        return response()->json([
            'data' => $organization->settings,
        ]);
    }

}
