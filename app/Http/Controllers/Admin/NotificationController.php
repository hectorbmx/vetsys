<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminNotification::query()
            ->latest()
            ->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function open(AdminNotification $notification)
    {
        $notification->markAsRead();

        return redirect()->to($notification->url ?: route('admin.notifications.index'));
    }

    public function markRead(AdminNotification $notification)
    {
        $notification->markAsRead();

        return back()->with('success', 'Notificacion marcada como leida.');
    }
}
