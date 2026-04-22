<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\DirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function __construct(
        protected DirectoryService $directoryService,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $extensions = $this->directoryService->search(
            $organization,
            $request->query('search'),
            (int) $request->integer('limit', 50),
        );

        return response()->json([
            'data' => $extensions->map(fn ($extension) => [
                'id' => $extension->id,
                'extension' => $extension->extension,
                'first_name' => $extension->first_name,
                'last_name' => $extension->last_name,
            ])->values(),
        ]);
    }
}
