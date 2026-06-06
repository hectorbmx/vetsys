<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = TenantNotification::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'data' => $notifications->map(fn (TenantNotification $notification) => $this->serialize($notification)),
            'meta' => [
                'unread_count' => $notifications->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function markRead(Request $request, TenantNotification $notification)
    {
        abort_unless($notification->tenant_id === $request->user()->tenant_id, 404);
        $notification->markAsRead();

        return response()->json([
            'data' => $this->serialize($notification->fresh()),
        ]);
    }

    private function serialize(TenantNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $notification->url,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
