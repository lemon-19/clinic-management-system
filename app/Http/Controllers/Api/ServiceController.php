<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Service::paginate($request->query('per_page', 15)));
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($service)->causedBy($request->user())->withProperties($service->toArray())->log('created service');
        }

        return response()->json($service, 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json($service);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($service)->causedBy($request->user())->withProperties($service->getChanges())->log('updated service');
        }

        return response()->json($service);
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        $service->delete();

        if (function_exists('activity')) {
            activity()->performedOn($service)->causedBy($request->user())->withProperties($service->toArray())->log('deleted service');
        }

        return response()->json(null, 204);
    }

    public function restore(Request $request, $id): JsonResponse
    {
        $service = Service::withTrashed()->findOrFail($id);
        $service->restore();

        if (function_exists('activity')) {
            activity()->performedOn($service)->causedBy($request->user())->withProperties($service->toArray())->log('restored service');
        }

        return response()->json($service);
    }
}
