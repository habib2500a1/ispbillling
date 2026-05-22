<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payments\MfsSmsIngestService;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PipraPay-style SMS forwarder endpoint for personal bKash / Nagad verification.
 *
 * POST /api/v1/mfs/sms/ingest
 * Header: X-MFS-Device-Key: {MFS_SMS_DEVICE_API_KEY}
 */
class MfsSmsIngestController extends Controller
{
    public function ingest(Request $request, MfsSmsIngestService $ingest): JsonResponse
    {
        if (! (bool) config('mfs_personal.sms_ingest.enabled', false)) {
            return response()->json(['message' => 'SMS ingest disabled.'], 503);
        }

        $expected = (string) config('mfs_personal.sms_ingest.api_key', '');
        $provided = (string) ($request->header('X-MFS-Device-Key') ?? $request->header('Authorization'));
        $provided = str_starts_with($provided, 'Bearer ') ? substr($provided, 7) : $provided;

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'gateway' => ['required', 'in:bkash,nagad,rocket'],
            'transaction_id' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'sender_phone' => ['nullable', 'string', 'max:20'],
            'merchant_phone' => ['nullable', 'string', 'max:20'],
            'balance_after' => ['nullable', 'numeric'],
            'raw_message' => ['nullable', 'string', 'max:2000'],
            'customer_reference' => ['nullable', 'string', 'max:64'],
            'received_at' => ['nullable', 'date'],
            'tenant_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = (int) ($validated['tenant_id'] ?? TenantResolver::currentTenantId() ?? 1);

        try {
            [$record, $duplicate, $matchedPending] = $ingest->ingest($tenantId, $validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->ingestResponse($record, $duplicate, $matchedPending));
    }

    /**
     * Same ingest from logged-in staff (unified mobile app — no separate verify APK).
     */
    public function ingestStaff(Request $request, MfsSmsIngestService $ingest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! StaffCapability::for($user)->canPayments()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! (bool) config('mfs_personal.sms_ingest.enabled', false)) {
            return response()->json(['message' => 'SMS ingest disabled.'], 503);
        }

        $validated = $request->validate([
            'gateway' => ['required', 'in:bkash,nagad,rocket'],
            'transaction_id' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'sender_phone' => ['nullable', 'string', 'max:20'],
            'merchant_phone' => ['nullable', 'string', 'max:20'],
            'balance_after' => ['nullable', 'numeric'],
            'raw_message' => ['nullable', 'string', 'max:2000'],
            'customer_reference' => ['nullable', 'string', 'max:64'],
            'received_at' => ['nullable', 'date'],
        ]);

        $validated['device_name'] = $validated['device_name']
            ?? ('Staff app · '.$user->name);

        try {
            [$record, $duplicate, $matchedPending] = $ingest->ingest((int) $user->tenant_id, $validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->ingestResponse($record, $duplicate, $matchedPending));
    }

    /**
     * @return array<string, mixed>
     */
    private function ingestResponse(\App\Models\MfsSmsRecord $record, bool $duplicate, int $matchedPending): array
    {
        $meta = $record->meta ?? [];

        return [
            'ok' => true,
            'duplicate' => $duplicate,
            'matched_pending' => $matchedPending,
            'auto_approved' => $matchedPending > 0,
            'id' => $record->id,
            'status' => $record->status,
            'transaction_id' => $record->transaction_id,
            'matched_customer_id' => $meta['matched_customer_id'] ?? null,
            'matched_customer_code' => $meta['matched_customer_code'] ?? null,
            'reference_match' => $meta['reference_match'] ?? null,
            'reference_token' => $meta['reference_token'] ?? null,
            'matched_by' => $meta['matched_by'] ?? null,
            'bill_payment_state' => \App\Support\MfsSmsBillPaymentState::resolve($record),
            'bill_payment_label' => \App\Support\MfsSmsBillPaymentState::label(
                \App\Support\MfsSmsBillPaymentState::resolve($record),
            ),
        ];
    }
}
