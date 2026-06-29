<?php

namespace App\Services\Ai;

use App\Models\AiActionRequest;
use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;

final class AiActionApprovalService
{
    public function __construct(
        private readonly AiSettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function proposeSuspendChronicDefaulters(int $tenantId, ?User $requester, int $minOverdueInvoices = 2): array
    {
        abort_unless($this->settings->actionsEnabled($tenantId), 403);

        $preview = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->whereHas('invoices', function ($q) use ($minOverdueInvoices): void {
                $q->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
                    ->where('due_date', '<', now()->toDateString());
            }, '>=', $minOverdueInvoices)
            ->limit(50)
            ->get(['id', 'customer_code', 'name', 'phone'])
            ->map(fn (Customer $c): array => [
                'id' => $c->id,
                'customer_code' => $c->customer_code,
                'name' => $c->name,
            ])
            ->all();

        $request = AiActionRequest::createTrusted([
            'tenant_id' => $tenantId,
            'action_type' => 'suspend_chronic_defaulters',
            'status' => AiActionRequest::STATUS_PENDING,
            'requested_by' => $requester?->id,
            'summary' => count($preview).' subscriber(s) proposed for suspend (2+ overdue invoices).',
            'payload' => ['customer_ids' => array_column($preview, 'id'), 'min_overdue_invoices' => $minOverdueInvoices],
            'preview' => ['customers' => $preview],
        ]);

        return [
            'action_request_id' => $request->id,
            'status' => $request->status,
            'summary' => $request->summary,
            'preview' => $request->preview,
            'message' => 'Action queued for admin approval. No changes were made yet.',
        ];
    }

    public function approve(int $actionId, User $approver): AiActionRequest
    {
        return DB::transaction(function () use ($actionId, $approver): AiActionRequest {
            $request = AiActionRequest::withoutGlobalScopes()
                ->where('tenant_id', $approver->tenant_id)
                ->whereKey($actionId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($request->status === AiActionRequest::STATUS_PENDING, 422, 'Action is not pending.');
            abort_unless($approver->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager']), 403);

            $request->forceFill([
                'status' => AiActionRequest::STATUS_APPROVED,
                'approved_by' => $approver->id,
            ])->save();

            $this->execute($request->fresh(), $approver);

            return $request->fresh();
        });
    }

    public function reject(int $actionId, User $approver, ?string $reason = null): AiActionRequest
    {
        $request = AiActionRequest::withoutGlobalScopes()
            ->where('tenant_id', $approver->tenant_id)
            ->whereKey($actionId)
            ->firstOrFail();

        abort_unless($request->status === AiActionRequest::STATUS_PENDING, 422);

        $request->forceFill([
            'status' => AiActionRequest::STATUS_REJECTED,
            'rejected_by' => $approver->id,
            'rejection_reason' => $reason,
        ])->save();

        return $request->fresh();
    }

    private function execute(AiActionRequest $request, User $approver): void
    {
        try {
            if ($request->action_type === 'suspend_chronic_defaulters') {
                $ids = $request->payload['customer_ids'] ?? [];
                if (is_array($ids) && $ids !== []) {
                    Customer::withoutGlobalScopes()
                        ->where('tenant_id', $request->tenant_id)
                        ->whereIn('id', $ids)
                        ->update([
                            'status' => CustomerStatus::SUSPENDED,
                            'network_access_state' => 'suspended',
                        ]);
                }
            }

            $request->forceFill([
                'status' => AiActionRequest::STATUS_EXECUTED,
                'executed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $request->forceFill(['status' => AiActionRequest::STATUS_FAILED])->save();
            throw $e;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, AiActionRequest>
     */
    public function pendingForTenant(?int $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return AiActionRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', AiActionRequest::STATUS_PENDING)
            ->with('requester:id,name')
            ->latest('id')
            ->limit(20)
            ->get();
    }
}
