<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicRequest;
use App\Http\Requests\UpdateClinicRequest;
use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClinicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Clinic::query();

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $p = $query->paginate($perPage);

        return response()->json([
            'data' => $p->items(),
            'links' => ['self' => url()->current()],
            'meta' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
            ],
        ]);
    }

    public function store(StoreClinicRequest $request): JsonResponse
    {
        $clinic = Clinic::create($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($clinic)->causedBy($request->user())->withProperties($clinic->toArray())->log('created clinic');
        }

        return response()->json($clinic, 201);
    }

    public function show(Clinic $clinic): JsonResponse
    {
        return response()->json($clinic);
    }

    public function update(UpdateClinicRequest $request, Clinic $clinic): JsonResponse
    {
        $clinic->update($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($clinic)->causedBy($request->user())->withProperties($clinic->getChanges())->log('updated clinic');
        }

        return response()->json($clinic);
    }

    // Related resources
    public function doctors(Clinic $clinic): JsonResponse
    {
        $perPage = request()->query('per_page', 15);

        $p = $clinic->doctors()->paginate($perPage);

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
            ],
        ]);
    }

    public function services(Clinic $clinic): JsonResponse
    {
        $perPage = request()->query('per_page', 15);

        $p = $clinic->services()->paginate($perPage);

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
            ],
        ]);
    }

    public function destroy(Request $request, Clinic $clinic): JsonResponse
    {
        $clinic->delete();

        if (function_exists('activity')) {
            activity()->performedOn($clinic)->causedBy($request->user())->withProperties($clinic->toArray())->log('deleted clinic');
        }

        return response()->json(null, 204);
    }

    public function restore(Request $request, $id): JsonResponse
    {
        $clinic = Clinic::withTrashed()->findOrFail($id);
        $clinic->restore();

        if (function_exists('activity')) {
            activity()->performedOn($clinic)->causedBy($request->user())->withProperties($clinic->toArray())->log('restored clinic');
        }

        return response()->json($clinic);
    }
}
