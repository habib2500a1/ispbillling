<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerPortalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerNotificationController extends Controller
{
    public function index(): View
    {
        $reseller = auth('reseller')->user();

        $notifications = ResellerPortalNotification::query()
            ->where('reseller_id', $reseller->id)
            ->latest('id')
            ->paginate(25);

        return view('reseller.notifications.index', [
            'reseller' => $reseller,
            'notifications' => $notifications,
        ]);
    }

    public function markRead(ResellerPortalNotification $notification): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        abort_unless((int) $notification->reseller_id === (int) $reseller->id, 404);
        $notification->markRead();

        return back()->with('status', 'Notification marked read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $reseller = auth('reseller')->user();

        ResellerPortalNotification::query()
            ->where('reseller_id', $reseller->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked read.');
    }
}
