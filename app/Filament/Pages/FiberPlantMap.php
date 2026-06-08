<?php

namespace App\Filament\Pages;

use App\Services\Network\FiberPlantMapService;
use App\Support\Rbac\StaffCapability;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class FiberPlantMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static string $view = 'filament.pages.fiber-plant-map';

    protected static ?string $navigationLabel = 'Network operations map';

    protected static ?string $title = 'Network operations map';

    protected static ?string $navigationGroup = 'Network';

    protected static ?string $slug = 'fiber-plant-map';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return array<string, mixed>
     */
    public function getMapPayload(): array
    {
        $customerId = request()->integer('customer');

        return app(FiberPlantMapService::class)->buildPayload(
            $customerId > 0 ? $customerId : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveNode(?int $id, array $data): array
    {
        $this->authorizeMap();
        SafeCache::forget('network_ops_map:'.TenantResolver::requiredTenantId());

        try {
            $node = app(FiberPlantMapService::class)->upsertNode($id, $data);

            return [
                'ok' => true,
                'payload' => app(FiberPlantMapService::class)->buildPayload(),
                'message' => $id ? 'Node updated' : 'Node added',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveEdge(?int $id, array $data): array
    {
        $this->authorizeMap();
        SafeCache::forget('network_ops_map:'.TenantResolver::requiredTenantId());

        try {
            app(FiberPlantMapService::class)->upsertEdge($id, $data);

            return [
                'ok' => true,
                'payload' => app(FiberPlantMapService::class)->buildPayload(),
                'message' => $id ? 'Cable updated' : 'Cable added',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteNode(int $id): array
    {
        $this->authorizeMap();
        app(FiberPlantMapService::class)->deleteNode($id);

        return [
            'ok' => true,
            'payload' => app(FiberPlantMapService::class)->buildPayload(),
            'message' => 'Node removed',
        ];
    }

    public function deleteEdge(int $id): array
    {
        $this->authorizeMap();
        app(FiberPlantMapService::class)->deleteEdge($id);

        return [
            'ok' => true,
            'payload' => app(FiberPlantMapService::class)->buildPayload(),
            'message' => 'Cable removed',
        ];
    }

    public function refreshLiveStatus(): array
    {
        $this->authorizeMap();
        SafeCache::forget('network_ops_map:'.TenantResolver::requiredTenantId());

        $payload = app(FiberPlantMapService::class)->buildPayload(
            request()->integer('customer') > 0 ? request()->integer('customer') : null,
        );

        $this->dispatch('isp-fiber-map-refresh', payload: $payload);

        return ['ok' => true, 'payload' => $payload];
    }

    public function importInfrastructure(): array
    {
        $this->authorizeMap();
        SafeCache::forget('network_ops_map:'.TenantResolver::requiredTenantId());

        try {
            $service = app(FiberPlantMapService::class);
            $pops = $service->importPopBoxes();
            $olts = $service->importOlts();
            $subs = $service->importCustomerNodes();

            $payload = $service->buildPayload();

            Notification::make()
                ->title('Import complete')
                ->body("POP: {$pops}, OLT: {$olts}, Customers: {$subs}")
                ->success()
                ->send();

            $this->dispatch('isp-fiber-map-refresh', payload: $payload);

            return [
                'ok' => true,
                'payload' => $payload,
                'message' => "Imported POP {$pops}, OLT {$olts}, customers {$subs}",
            ];
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canMikrotik();
    }

    private function authorizeMap(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
