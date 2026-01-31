<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicDocumentRequest;
use App\Models\Clinic;
use App\Models\ClinicDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ClinicDocumentController extends Controller
{
    /**
     * Ensure the current user can access the given clinic.
     * Returns the resolved Clinic instance (may be the same as injected) for caller to use.
     *
     * @return Clinic
     */
    protected function ensureClinicAccess(Clinic $clinic, $user): Clinic
    {
        if (! $user) {
            Log::warning('ensureClinicAccess: no authenticated user', ['clinic_raw' => request()->route('clinic')]);
            abort(401, 'Unauthenticated');
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            Log::info('ensureClinicAccess: user is admin', ['user_id' => $user->id, 'clinic_raw' => request()->route('clinic')]);
            return $clinic;
        }

        // If route-model injected Clinic is empty (implicit binding didn't resolve), attempt to resolve manually from raw route parameter
        if (empty($clinic->id)) {
            $raw = request()->route('clinic');
            Log::info('ensureClinicAccess: clinic injected empty, attempting to resolve from route', ['raw' => $raw]);
            $resolved = Clinic::where('uuid', $raw)->orWhere('id', $raw)->first();
            if ($resolved) {
                $clinic = $resolved;
                Log::info('ensureClinicAccess: clinic resolved manually', ['id' => $clinic->id, 'uuid' => $clinic->uuid]);
            } else {
                Log::warning('ensureClinicAccess: could not resolve clinic from route param', ['raw' => $raw]);
            }
        }

        // fallback to checking clinic relation directly via the ClinicStaff table (avoid relation caching issues)
        $isStaffViaClinic = $clinic->staff()->where('user_id', $user->id)->exists();
        $isStaffViaUser = \App\Models\ClinicStaff::where('clinic_id', $clinic->id)->where('user_id', $user->id)->exists();
        $isStaffViaUserRelation = method_exists($user, 'clinicStaff') ? $user->clinicStaff()->where('clinic_id', $clinic->id)->exists() : false;

        if (is_object($clinic) && method_exists($clinic, 'toArray')) {
            Log::info('ensureClinicAccess clinic attrs', ['attrs' => $clinic->toArray()]);
        } else {
            Log::info('ensureClinicAccess clinic not object', ['type' => gettype($clinic)]);
        }

        Log::info('ensureClinicAccess check', [
            'user_id' => $user->id,
            'clinic_present' => isset($clinic),
            'clinic_is_object' => is_object($clinic),
            'clinic_class' => is_object($clinic) ? get_class($clinic) : gettype($clinic),
            'clinic_id' => $clinic->id ?? null,
            'clinic_uuid' => $clinic->uuid ?? null,
            'auth_guard_default' => app('auth')->getDefaultDriver(),
            'is_staff_via_clinic_relation' => $isStaffViaClinic,
            'is_staff_via_direct_query' => $isStaffViaUser,
            'is_staff_via_user_relation' => $isStaffViaUserRelation,
        ]);

        if (! $isStaffViaClinic && ! $isStaffViaUser && ! $isStaffViaUserRelation) {
            abort(403, 'Unauthorized - must be clinic staff or admin');
        }

        return $clinic;
    }

    public function index(Clinic $clinic)
    {
        $clinic = $this->ensureClinicAccess($clinic, request()->user());

        $documents = ClinicDocument::where('clinic_id', $clinic->id)->orderByDesc('created_at')->get();

        return response()->json(['data' => $documents]);
    }

    public function store(StoreClinicDocumentRequest $request, Clinic $clinic)
    {
        $clinic = $this->ensureClinicAccess($clinic, $request->user());

        $file = $request->file('file');
        $type = $request->input('type');

        $filename = now()->format('YmdHis')."_".uniqid().".".$file->getClientOriginalExtension();
        $path = "clinic-documents/{$clinic->id}";

        // Store on local (private) disk by default
        $disk = config('filesystems.default', 'local');

        $storedPath = Storage::disk($disk)->putFileAs($path, $file, $filename);

        $doc = ClinicDocument::create([
            'clinic_id' => $clinic->id,
            'type' => $type,
            'file_path' => $storedPath,
            'file_disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        activity()->performedOn($doc)->causedBy($request->user())->withProperties(['action' => 'uploaded'])->log('clinic document uploaded');

        return response()->json(['data' => $doc], 201);
    }

    public function show(Clinic $clinic, ClinicDocument $document)
    {
        $clinic = $this->ensureClinicAccess($clinic, request()->user());

        $this->authorize('view', $document);

        if ($document->clinic_id !== $clinic->id) {
            abort(404);
        }

        return response()->json(['data' => $document]);
    }

    public function download(Clinic $clinic, ClinicDocument $document)
    {
        $clinic = $this->ensureClinicAccess($clinic, request()->user());

        $this->authorize('download', $document);

        if ($document->clinic_id !== $clinic->id) {
            abort(404);
        }

        return Storage::disk($document->file_disk)->download($document->file_path, $document->original_name);
    }
}
