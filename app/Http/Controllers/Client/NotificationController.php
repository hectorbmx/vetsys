<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\TenantNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;

        $notifications = TenantNotification::query()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate(20);

        return view('client.notifications.index', compact('notifications'));
    }

    public function open(TenantNotification $notification)
    {
        abort_unless($notification->tenant_id === auth()->user()->tenant_id, 404);

        $notification->markAsRead();

        return redirect()->to($notification->url ?: route('client.notifications.index'));
    }

    public function markRead(TenantNotification $notification)
    {
        abort_unless($notification->tenant_id === auth()->user()->tenant_id, 404);

        $notification->markAsRead();

        return back()->with('success', 'Notificacion marcada como leida.');
    }
}
