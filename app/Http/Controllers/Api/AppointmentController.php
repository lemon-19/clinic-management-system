<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::query();

        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->query('clinic_id'));
        }

        if ($request->has('doctor_id')) {
            $query->where('doctor_id', $request->query('doctor_id'));
        }

        return response()->json($query->paginate($request->query('per_page', 15)));
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        // if patient_id not provided, and an authenticated user exists, set as patient
        if (empty($data['patient_id']) && $request->user()) {
            $data['patient_id'] = $request->user()->id;
        }

        $appointment = Appointment::create($data);

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties($appointment->toArray())->log('created appointment');
        }

        return response()->json($appointment, 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        return response()->json($appointment);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $appointment->update($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties($appointment->getChanges())->log('updated appointment');
        }

        return response()->json($appointment);
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $appointment->status = \App\Enums\AppointmentStatus::CANCELLED;
        $appointment->save();

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties(['status' => 'cancelled'])->log('cancelled appointment');
        }

        return response()->json($appointment);
    }

    public function confirm(Request $request, Appointment $appointment): JsonResponse
    {
        $appointment->status = \App\Enums\AppointmentStatus::CONFIRMED;
        $appointment->save();

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties(['status' => 'confirmed'])->log('confirmed appointment');
        }

        return response()->json($appointment);
    }

    public function reschedule(\App\Http\Requests\RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $appointment->appointment_date = $request->input('appointment_date');
        $appointment->appointment_time = $request->input('appointment_time');
        $appointment->status = \App\Enums\AppointmentStatus::PENDING;
        $appointment->save();

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties(['appointment_date' => $appointment->appointment_date, 'appointment_time' => $appointment->appointment_time])->log('rescheduled appointment');
        }

        return response()->json($appointment);
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties($appointment->toArray())->log('deleted appointment');
        }

        return response()->json(null, 204);
    }

    public function restore(Request $request, $id): JsonResponse
    {
        $appointment = Appointment::withTrashed()->findOrFail($id);
        $appointment->restore();

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties($appointment->toArray())->log('restored appointment');
        }

        return response()->json($appointment);
    }
}
