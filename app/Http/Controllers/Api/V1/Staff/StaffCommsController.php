<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;
use App\Support\StaffTenantScope;
use App\Support\NotificationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffCommsController extends Controller
{
    public function smsReminder(Request $request, int $customer, NotificationDispatcher $dispatcher): JsonResponse
    {
        $user = $this->staff($request);

        $model = StaffTenantScope::customerForStaff($user, $customer);

        $due = $model->openInvoiceBalance();
        if ($due <= 0) {
            return response()->json(['message' => 'Customer has no due balance.'], 422);
        }

        $dispatcher->notifyCustomer($model, NotificationEvent::INVOICE_DUE, [
            'amount' => number_format($due, 2),
            'customer_code' => $model->customer_code,
        ]);

        return response()->json(['message' => 'Due reminder sent via configured channels.']);
    }

    public function smsBulkDue(Request $request, NotificationDispatcher $dispatcher): JsonResponse
    {
        $user = $this->staff($request);

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $message = trim((string) ($data['message'] ?? ''));
        $count = 0;
        if ($message !== '') {
            $count = $dispatcher->broadcastCustom(StaffTenantScope::tenantIdFor($user), $message, 'due', NotificationChannel::SMS);
        } else {
            Customer::withoutGlobalScopes()
                ->where('tenant_id', StaffTenantScope::tenantIdFor($user))
                ->where('status', 'active')
                ->whereHas('invoices', fn ($q) => $q
                    ->whereIn('status', ['open', 'partial'])
                    ->whereRaw('(total - amount_paid) > 0'))
                ->limit(500)
                ->cursor()
                ->each(function (Customer $c) use ($dispatcher, &$count): void {
                    $due = $c->openInvoiceBalance();
                    if ($due > 0) {
                        $dispatcher->notifyCustomer($c, NotificationEvent::INVOICE_DUE, [
                            'amount' => number_format($due, 2),
                            'customer_code' => $c->customer_code,
                        ]);
                        $count++;
                    }
                });
        }

        return response()->json([
            'message' => "Reminders sent to {$count} customer(s).",
            'count' => $count,
        ]);
    }

    public function broadcastNotice(Request $request, NotificationDispatcher $dispatcher): JsonResponse
    {
        $user = $this->manager($request);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'target' => ['nullable', 'string', 'in:active,due,all,suspended'],
        ]);

        $target = $data['target'] ?? 'active';
        $count = $dispatcher->broadcastCustom(
            StaffTenantScope::tenantIdFor($user),
            $data['message'],
            $target,
            NotificationChannel::SMS,
        );

        return response()->json([
            'message' => "Notice sent to {$count} subscriber(s).",
            'count' => $count,
        ]);
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'branch-manager', 'cashier', 'collector']),
            403,
        );

        return $user;
    }

    private function manager(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'branch-manager']),
            403,
        );

        return $user;
    }
}
