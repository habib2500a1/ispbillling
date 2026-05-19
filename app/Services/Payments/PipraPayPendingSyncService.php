<?php

namespace App\Services\Payments;

use App\Http\Controllers\PipraPayPaymentController;
use App\Models\PendingGatewayPayment;
use App\Support\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class PipraPayPendingSyncService
{
    /**
     * @return array{checked: int, recorded: int, still_pending: int, errors: int, messages: list<string>}
     */
    public function sync(int $limit = 50): array
    {
        if (! PipraPayCheckoutService::isEnabled()) {
            return [
                'checked' => 0,
                'recorded' => 0,
                'still_pending' => 0,
                'errors' => 0,
                'messages' => ['PipraPay is disabled.'],
            ];
        }

        $service = PipraPayCheckoutService::fromConfig();
        $controller = app(PipraPayPaymentController::class);

        $rows = PendingGatewayPayment::query()
            ->withoutGlobalScopes()
            ->where('gateway', PaymentGateway::PIPRAPAY)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->whereNotNull('transaction_id')
            ->where('transaction_id', '!=', '')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $result = [
            'checked' => $rows->count(),
            'recorded' => 0,
            'still_pending' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        foreach ($rows as $row) {
            $ppId = (string) $row->transaction_id;

            try {
                $verified = $service->verifyPayment($ppId);
            } catch (\Throwable $e) {
                $result['errors']++;
                $result['messages'][] = "{$ppId}: verify failed — {$e->getMessage()}";
                Log::info('piprapay.sync.verify_failed', ['pp_id' => $ppId, 'error' => $e->getMessage()]);

                continue;
            }

            if (! $service->isPaymentSuccessful($verified)) {
                $result['still_pending']++;

                continue;
            }

            $orderId = $service->orderIdFromVerify($verified, $ppId)
                ?? $row->checkout_order_id;

            try {
                $request = Request::create('/piprapay/success', 'GET', [
                    'pp_id' => $ppId,
                    'order_id' => $orderId,
                ]);
                $controller->success($request);
                $result['recorded']++;
                $result['messages'][] = "{$ppId}: recorded";
            } catch (\Throwable $e) {
                $result['errors']++;
                $result['messages'][] = "{$ppId}: record failed — {$e->getMessage()}";
                Log::warning('piprapay.sync.record_failed', ['pp_id' => $ppId, 'error' => $e->getMessage()]);
            }
        }

        return $result;
    }
}
