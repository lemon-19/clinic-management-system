<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewClinicDocumentRequest;
use App\Models\ClinicDocument;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ClinicDocumentReviewController extends Controller
{
    public function index(Request $request)
    {
        $this->middleware('role:admin');

        $query = ClinicDocument::with(['clinic', 'uploader', 'reviewer']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $documents = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($documents);
    }

    public function show(ClinicDocument $document)
    {
        $this->middleware('role:admin');

        // If route-model injected document is empty (binding failed), resolve manually
        if (empty($document->id)) {
            $raw = request()->route('document');
            $document = ClinicDocument::where('uuid', $raw)->orWhere('id', $raw)->firstOrFail();
        }

        return response()->json(['data' => $document->load(['clinic', 'uploader', 'reviewer'])]);
    }

    public function review(ReviewClinicDocumentRequest $request, ClinicDocument $document, NotificationService $notificationService)
    {
        $this->middleware('role:admin');

        // If route-model injected document is empty (binding failed), resolve manually
        if (empty($document->id)) {
            $raw = request()->route('document');
            $document = ClinicDocument::where('uuid', $raw)->orWhere('id', $raw)->firstOrFail();
            \Illuminate\Support\Facades\Log::info('Admin review manual resolve', ['document_id' => $document->id]);
        }

        \Illuminate\Support\Facades\Log::info('Admin review called', ['document_id' => $document->id, 'status' => $document->status, 'request' => $request->all()]);

        if (! $document->isPending()) {
            return response()->json(['message' => 'Document already reviewed'], 422);
        }

        $document->status = $request->input('status');
        $document->reviewed_by = $request->user()->id;
        $document->reviewed_at = now();
        $document->rejection_reason = $request->input('rejection_reason');
        $document->save();

        activity()->performedOn($document)->causedBy($request->user())->withProperties([
            'status' => $document->status,
            'rejection_reason' => $document->rejection_reason,
        ])->log('clinic document reviewed');

        // Notify clinic owners/staff
        $clinic = $document->clinic;
        $owners = $clinic->staff()->whereIn('role', ['owner', 'admin'])->with('user')->get()->pluck('user')->filter();

        $title = 'Clinic document '.ucfirst($document->status);
        $message = "Your clinic document ({$document->type}) has been {$document->status}.";
        if ($document->status === 'rejected' && $document->rejection_reason) {
            $message .= " Reason: {$document->rejection_reason}";
        }

        foreach ($owners as $owner) {
            $notificationService->createInAppNotificationIfAllowed($owner->id, 'clinic_document_status', $title, $message, ['document_id' => $document->id, 'status' => $document->status]);

            if ($owner->notificationPreference?->email_enabled ?? true) {
                // Queue an email for owners
                \App\Jobs\SendEmailNotificationJob::dispatch($owner->email, $title, 'emails.generic-notification', ['message' => $message, 'title' => $title]);
            }
        }

        return response()->json(['data' => $document]);
    }
}
