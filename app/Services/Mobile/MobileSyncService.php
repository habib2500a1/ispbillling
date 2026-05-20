<?php

namespace App\Services\Mobile;

use App\Http\Controllers\Api\V1\Collector\CollectorController;
use App\Models\MobileSyncQueue;
use App\Models\User;
use Illuminate\Http\Request;

final class MobileSyncService
{
    /**
     * @param  list<array{action: string, payload: array<string, mixed>, idempotency_key: string}>  $items
     * @return array{synced: int, failed: int, results: list<array<string, mixed>>}
     */
    public function processBatch(User $user, string $deviceUuid, array $items): array
    {
        $synced = 0;
        $failed = 0;
        $results = [];

        foreach ($items as $item) {
            $key = (string) ($item['idempotency_key'] ?? '');
            $action = (string) ($item['action'] ?? '');
            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];

            if ($key === '' || $action === '') {
                $failed++;
                $results[] = ['idempotency_key' => $key, 'status' => 'failed', 'error' => 'Invalid item'];

                continue;
            }

            $existing = MobileSyncQueue::query()->where('idempotency_key', $key)->first();
            if ($existing?->status === 'synced') {
                $synced++;
                $results[] = ['idempotency_key' => $key, 'status' => 'synced', 'duplicate' => true];

                continue;
            }

            $row = MobileSyncQueue::query()->updateOrCreate(
                ['idempotency_key' => $key],
                [
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'device_uuid' => $deviceUuid,
                    'action' => $action,
                    'payload' => $payload,
                    'status' => 'pending',
                    'error' => null,
                ],
            );

            try {
                $response = $this->dispatch($user, $action, $payload);
                $row->update(['status' => 'synced', 'synced_at' => now(), 'error' => null]);
                $synced++;
                $results[] = ['idempotency_key' => $key, 'status' => 'synced', 'response' => $response];
            } catch (\Throwable $e) {
                $row->update(['status' => 'failed', 'error' => $e->getMessage()]);
                $failed++;
                $results[] = ['idempotency_key' => $key, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return ['synced' => $synced, 'failed' => $failed, 'results' => $results];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function dispatch(User $user, string $action, array $payload): ?array
    {
        return match ($action) {
            'collector.collection' => $this->runCollection($user, $payload),
            'collector.expense' => $this->runExpense($user, $payload),
            'technician.visit_update' => $this->runVisitUpdate($user, $payload),
            default => throw new \InvalidArgumentException("Unknown sync action: {$action}"),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function runCollection(User $user, array $payload): array
    {
        $request = Request::create('/api/v1/collector/collections', 'POST', $payload);
        $request->setUserResolver(fn () => $user);
        $response = app(CollectorController::class)->storeCollection($request, app(\App\Services\Collector\CollectorVisitService::class));

        return json_decode($response->getContent(), true) ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function runExpense(User $user, array $payload): array
    {
        $request = Request::create('/api/v1/collector/expenses', 'POST', $payload);
        $request->setUserResolver(fn () => $user);
        $response = app(CollectorController::class)->storeExpense(
            $request,
            app(\App\Services\Collector\CollectorWalletService::class),
        );

        return json_decode($response->getContent(), true) ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function runVisitUpdate(User $user, array $payload): array
    {
        $visitId = (int) ($payload['visit_id'] ?? 0);
        unset($payload['visit_id']);
        $visit = \App\Models\FieldVisit::query()->findOrFail($visitId);
        $request = Request::create("/api/v1/technician/field-visits/{$visitId}", 'PATCH', $payload);
        $request->setUserResolver(fn () => $user);
        $response = app(\App\Http\Controllers\Api\V1\Technician\FieldVisitController::class)
            ->update($request, $visit, app(PushNotificationService::class));

        return json_decode($response->getContent(), true) ?? [];
    }
}
