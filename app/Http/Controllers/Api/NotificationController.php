<?php
// ============================================
// app/Http/Controllers/Api/NotificationController.php
// ============================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    /**
     * Get all notifications
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $filter = $request->query('filter', 'all');

        $query = Notification::where('user_id', $userId);

        if ($filter === 'unread') {
            $query->unread();
        } elseif ($filter === 'read') {
            $query->read();
        }

        $notifications = $query->latest()->paginate(15);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'unread_count' => $this->notificationService->getUnreadCount($userId),
            ],
        ]);
    }

    /**
     * Mark as read
     */
    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        $notification = Notification::findOrFail($notificationId);

        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->notificationService->markAsRead($notificationId);

        return response()->json(['message' => 'Marked as read']);
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user()->id);
        return response()->json(['message' => 'All marked as read']);
    }

    /**
     * Get preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $preferences = $this->notificationService->getOrCreatePreference($request->user()->id);
        return response()->json(['data' => $preferences]);
    }

    /**
     * Update preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_confirmation' => 'boolean',
            'appointment_reminder_24h' => 'boolean',
            'appointment_reminder_1h' => 'boolean',
            'appointment_completed' => 'boolean',
            'medical_record_shared' => 'boolean',
            'prescription_added' => 'boolean',
            'clinic_announcements' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'reminder_24h_time' => 'nullable|date_format:H:i',
        ]);

        $prefs = $this->notificationService->getOrCreatePreference($request->user()->id);
        $prefs->update($validated);

        return response()->json(['data' => $prefs, 'message' => 'Preferences updated']);
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, int $notificationId): JsonResponse
    {
        $notification = Notification::findOrFail($notificationId);

        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();
        return response()->json([], 204);
    }
}
