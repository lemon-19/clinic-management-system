<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Requests\BulkCancelAppointmentsRequest;
use App\Http\Requests\BulkRescheduleConflictsRequest;
use App\Models\Appointment;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

        // Authorization check: users can only create appointments for themselves unless they're admin/doctor
        if (array_key_exists('patient_id', $data)) {
            if ($data['patient_id'] != $request->user()->id &&
                !in_array($request->user()->user_type, [UserType::ADMIN, UserType::DOCTOR])) {
                return response()->json([
                    'message' => 'You can only create appointments for yourself'
                ], 403);
            }
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
        
        // Authorization check
        if (!$this->canManageAppointment($request->user(), $appointment)) {
            return response()->json(['message' => 'Unauthorized to update this appointment'], 403);
        }
        
        $appointment->update($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties($appointment->getChanges())->log('updated appointment');
        }

        return response()->json($appointment);
    }

    public function cancel(Request $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();

        Log::info('Cancel request', [
            'appointment_id' => $appointment->id,
            'appointment_patient_id' => $appointment->patient_id,
            'requesting_user_id' => $request->user()?->id,
        ]);

        // Authorization check
        if (!$this->canManageAppointment($request->user(), $appointment)) {
            Log::info('Authorization failed in cancel');
            return response()->json(['message' => 'Unauthorized to cancel this appointment'], 403);
        }

        Log::info('Authorization passed in cancel');

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

    // public function complete(Request $request, $appointmentId): JsonResponse
    // {
    //     $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();

    //     // only allow confirmed -> completed
    //     if (! $appointment->canTransition(\App\Enums\AppointmentStatus::COMPLETED)) {
    //         return response()->json(['message' => 'Appointment cannot be marked completed from its current status.'], 422);
    //     }

    //     [$ok, $msg] = $appointment->transitionTo(\App\Enums\AppointmentStatus::COMPLETED);
    //     if (! $ok) {
    //         return response()->json(['message' => $msg], 422);
    //     }

    //     if (function_exists('activity')) {
    //         activity()->performedOn($appointment)->causedBy($request->user())->withProperties(['status' => 'completed'])->log('completed appointment');
    //     }

    //     return response()->json($appointment);
    // }

    public function complete(Request $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();

        if (! $appointment->canTransition(\App\Enums\AppointmentStatus::COMPLETED)) {
            return response()->json(['message' => 'Appointment cannot be marked completed from its current status.'], 422);
        }

        [$ok, $msg] = $appointment->transitionTo(\App\Enums\AppointmentStatus::COMPLETED);
        if (! $ok) {
            return response()->json(['message' => $msg], 422);
        }

        // Auto-create medical record
        $medicalRecord = \App\Http\Controllers\Api\MedicalRecordController::createFromAppointment($appointment);

        if (function_exists('activity')) {
            activity()->performedOn($appointment)->causedBy($request->user())->withProperties(['status' => 'completed'])->log('completed appointment');
        }

        return response()->json([
            'message' => 'Appointment completed successfully',
            'appointment' => $appointment,
            'medical_record' => $medicalRecord,
        ]);
    }

    public function reschedule(\App\Http\Requests\RescheduleAppointmentRequest $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)->orWhere('uuid', $appointmentId)->firstOrFail();

        Log::info('Reschedule request', [
            'appointment_id' => $appointment->id,
            'appointment_patient_id' => $appointment->patient_id,
            'requesting_user_id' => $request->user()?->id,
        ]);

        // Authorization check
        if (!$this->canManageAppointment($request->user(), $appointment)) {
            Log::info('Authorization failed in reschedule');
            return response()->json(['message' => 'Unauthorized to reschedule this appointment'], 403);
        }

        Log::info('Authorization passed in reschedule');

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
        // Authorization check
        if (!$this->canManageAppointment($request->user(), $appointment)) {
            return response()->json(['message' => 'Unauthorized to delete this appointment'], 403);
        }
        
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

    /**
     * Check if user can manage (cancel/reschedule/update/delete) an appointment
     * Users can manage their own appointments
     * Admins and doctors can manage any appointment
     */
    private function canManageAppointment($user, Appointment $appointment): bool
    {
        if (!$user) {
            Log::info('Authorization check: No user');
            return false;
        }

        Log::info('Authorization check details', [
            'user_id' => $user->id,
            'user_type' => $user->user_type?->value ?? 'null',
            'appointment_patient_id' => $appointment->patient_id,
            'ids_match_strict' => $appointment->patient_id === $user->id,
            'ids_match_loose' => $appointment->patient_id == $user->id,
        ]);

        // User is the patient who owns the appointment (loose comparison!)
        if ($appointment->patient_id == $user->id) {
            Log::info('Authorization granted: User is owner');
            return true;
        }

        // User must have user_type defined
        if (!isset($user->user_type)) {
            Log::info('Authorization denied: No user_type set');
            return false;
        }

        // Admin or doctor can manage
        if ($user->user_type === UserType::ADMIN || $user->user_type === UserType::DOCTOR) {
            Log::info('Authorization granted: User is admin/doctor');
            return true;
        }

        Log::info('Authorization denied: Not owner and not admin/doctor');
        return false;
    }

    

}