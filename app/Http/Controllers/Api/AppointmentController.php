<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Requests\BulkCancelAppointmentsRequest;
use App\Http\Requests\BulkRescheduleConflictsRequest;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

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

        // default status to pending if not provided
        if (empty($data['status'])) {
            $data['status'] = \App\Enums\AppointmentStatus::PENDING;
        }

        // final availability check (protects against race conditions)
        $appointmentStart = Carbon::parse($data['appointment_time']);
        [$ok, $message] = Appointment::isSlotAvailable($data['doctor_id'], $data['clinic_id'], $appointmentStart);
        if (! $ok) {
            return response()->json(['message' => $message, 'errors' => ['appointment_time' => [$message]]], 422);
        }

        $appointment = Appointment::create($data);

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties($appointment->toArray())->log('created appointment');
        }

        return response()->json($appointment, 201);
    }

    public function show($appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();
        return response()->json($appointment);
    }

    public function update(UpdateAppointmentRequest $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();
        $appointment->update($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties($appointment->getChanges())->log('updated appointment');
        }

        return response()->json($appointment);
    }

    public function cancel(Request $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();

        if (! $appointment->canTransition(\App\Enums\AppointmentStatus::CANCELLED)) {
            return response()->json(['message' => 'Appointment cannot be cancelled from its current status.'], 422);
        }

        [$ok, $msg] = $appointment->transitionTo(\App\Enums\AppointmentStatus::CANCELLED);
        if (! $ok) {
            return response()->json(['message' => $msg], 422);
        }

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties(['status' => 'cancelled'])->log('cancelled appointment');
        }

        return response()->json($appointment);
    }

    public function confirm(Request $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();

        // only allow pending -> confirmed
        if (! $appointment->canTransition(\App\Enums\AppointmentStatus::CONFIRMED)) {
            return response()->json(['message' => 'Appointment cannot be confirmed from its current status.'], 422);
        }

        [$ok, $msg] = $appointment->transitionTo(\App\Enums\AppointmentStatus::CONFIRMED);
        if (! $ok) {
            return response()->json(['message' => $msg], 422);
        }

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties(['status' => 'confirmed'])->log('confirmed appointment');
        }

        return response()->json($appointment);
    }

    public function complete(Request $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();

        // only allow confirmed -> completed
        if (! $appointment->canTransition(\App\Enums\AppointmentStatus::COMPLETED)) {
            return response()->json(['message' => 'Appointment cannot be marked completed from its current status.'], 422);
        }

        [$ok, $msg] = $appointment->transitionTo(\App\Enums\AppointmentStatus::COMPLETED);
        if (! $ok) {
            return response()->json(['message' => $msg], 422);
        }

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties(['status' => 'completed'])->log('completed appointment');
        }

        return response()->json($appointment);
    }

    public function reschedule(\App\Http\Requests\RescheduleAppointmentRequest $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();

        $appointmentDate = $request->input('appointment_date');
        $appointmentTime = $request->input('appointment_time');

        // check availability when rescheduling
        $appointmentStart = Carbon::parse($appointmentTime);
        [$ok, $message] = Appointment::isSlotAvailable($appointment->doctor_id, $appointment->clinic_id, $appointmentStart, $appointment->id);
        if (! $ok) {
            return response()->json(['message' => $message, 'errors' => ['appointment_time' => [$message]]], 422);
        }

        // use DB update to avoid accidental inserts
        Appointment::whereKey($appointment->getKey())->update([
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime,
            'status' => \App\Enums\AppointmentStatus::PENDING,
        ]);

        $appointment->refresh();

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

    /**
     * Bulk cancel multiple appointments
     */
    public function bulkCancel(BulkCancelAppointmentsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = Appointment::bulkCancel($data['appointment_ids'], $data['reason'] ?? null);

        if (function_exists('activity')) {
            activity()->causedBy($request->user())->withProperties($data)->log('bulk cancelled appointments');
        }

        return response()->json([
            'message' => "Successfully cancelled {$result['count']} appointment(s)",
            'data' => $result,
        ], $result['failed'] > 0 ? 207 : 200);
    }

    /**
     * Bulk reschedule conflicting appointments
     */
    public function bulkRescheduleConflicts(BulkRescheduleConflictsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = Appointment::bulkRescheduleConflicts(
            $data['doctor_id'],
            $data['clinic_id'],
            $data['date_from'],
            $data['date_to'],
            $data['new_date'],
            $data['new_time']
        );

        if (function_exists('activity')) {
            activity()->causedBy($request->user())->withProperties($data)->log('bulk rescheduled conflicting appointments');
        }

        return response()->json([
            'message' => "Successfully rescheduled {$result['count']} appointment(s)",
            'data' => $result,
        ], $result['failed'] > 0 ? 207 : 200);
    }
}

