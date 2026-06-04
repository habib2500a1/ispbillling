<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Services\Resellers\ResellerBulkDueReminderService;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResellerDueReminderController extends Controller
{
    public function bulk(Request $request, ResellerBulkDueReminderService $bulk): RedirectResponse
    {
        $reseller = auth('reseller')->user();

        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::BILLING_VIEW)) {
            throw ValidationException::withMessages(['permission' => 'Not allowed.']);
        }

        if (! config('reseller_billing.due_reminders.reseller_portal_enabled', true)) {
            throw ValidationException::withMessages(['reminders' => 'Due reminders are disabled.']);
        }

        $validated = $request->validate([
            'min_days_overdue' => ['nullable', 'integer', 'min:0', 'max:90'],
        ]);

        $minDays = isset($validated['min_days_overdue'])
            ? (int) $validated['min_days_overdue']
            : (int) config('reseller_billing.due_reminders.bulk_min_days_overdue', 0);

        $result = $bulk->runForReseller($reseller, false, $minDays);

        return back()->with(
            'status',
            sprintf(
                'Reminders sent: %d. Skipped (cooldown / no contact): %d. Bills scanned: %d.',
                $result['sent'],
                $result['skipped'],
                $result['invoices'],
            ),
        );
    }
}
