<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Doctor::paginate($request->query('per_page', 15)));
    }

    public function store(StoreDoctorRequest $request): JsonResponse
    {
        $doctor = Doctor::create($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($doctor)->causedBy($request->user())->withProperties($doctor->toArray())->log('created doctor');
        }

        return response()->json($doctor, 201);
    }

    public function show(Doctor $doctor): JsonResponse
    {
        return response()->json($doctor);
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): JsonResponse
    {
        $doctor->update($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($doctor)->causedBy($request->user())->withProperties($doctor->getChanges())->log('updated doctor');
        }

        return response()->json($doctor);
    }

    // Related resources
    public function clinics(Doctor $doctor): JsonResponse
    {
        $p = $doctor->clinics()->paginate(request()->query('per_page', 15));

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
            ],
        ]);
    }

    public function schedules(Doctor $doctor): JsonResponse
    {
        $p = $doctor->schedules()->paginate(request()->query('per_page', 15));

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
            ],
        ]);
    }

    public function destroy(Request $request, Doctor $doctor): JsonResponse
    {
        $doctor->delete();

        if (function_exists('activity')) {
            activity()->performedOn($doctor)->causedBy($request->user())->withProperties($doctor->toArray())->log('deleted doctor');
        }

        return response()->json(null, 204);
    }

    public function restore(Request $request, $id): JsonResponse
    {
        $doctor = Doctor::withTrashed()->findOrFail($id);
        $doctor->restore();

        if (function_exists('activity')) {
            activity()->performedOn($doctor)->causedBy($request->user())->withProperties($doctor->toArray())->log('restored doctor');
        }

        return response()->json($doctor);
    }
}
