<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Services\CallCenter\CallCenterIngestService;
use App\Support\SupportPanelAccess;
use App\Support\TenantResolver;
use App\Support\WebSipFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class WebSipCallLogController extends Controller
{
    public function __invoke(Request $request, CallCenterIngestService $ingest): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && SupportPanelAccess::viewTickets($user), 403);
        abort_unless(WebSipFeature::isEnabledForUser($user), 403);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'status' => ['required', 'string', Rule::in([
                'answered', 'completed', 'failed', 'missed', 'no_answer', 'busy', 'cancelled',
            ])],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'started_at' => ['nullable', 'date'],
            'external_id' => ['nullable', 'string', 'max:128'],
            'cause' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', Rule::in([CallLog::DIRECTION_INBOUND, CallLog::DIRECTION_OUTBOUND])],
        ]);

        $tenantId = TenantResolver::requiredTenantId();
        $externalId = filled($data['external_id'] ?? null)
            ? (string) $data['external_id']
            : null;

        if ($externalId !== null && ! str_starts_with($externalId, 'websip-')) {
            $externalId = 'websip-'.$externalId;
        }

        $log = $ingest->ingest([
            'phone' => $data['phone'],
            'status' => $data['status'],
            'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
            'started_at' => $data['started_at'] ?? now()->toIso8601String(),
            'staff_user_id' => $user->id,
            'staff_extension' => null,
            'direction' => $data['direction'] ?? CallLog::DIRECTION_OUTBOUND,
            'external_id' => $externalId,
            'remarks' => filled($data['cause'] ?? null)
                ? 'WebSIP: '.(string) $data['cause']
                : 'WebSIP browser dialer',
            'meta' => [
                'source' => 'websip',
                'cause' => $data['cause'] ?? null,
            ],
        ], $tenantId);

        return response()->json([
            'ok' => true,
            'call_log_id' => $log->id,
        ]);
    }
}
