<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\CustomerPortalNotificationService;
use Illuminate\View\View;

class PortalNotificationController extends Controller
{
    public function index(CustomerPortalNotificationService $notifications): View
    {
        $customer = auth('customer')->user();

        return view('portal.notifications', [
            'customer' => $customer,
            'items' => $notifications->feed($customer, 50),
        ]);
    }
}
