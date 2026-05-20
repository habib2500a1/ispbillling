<?php

namespace App\Filament\Pages;

use App\Services\Dashboard\DashboardPreferencesService;
use App\Support\Rbac\StaffCapability;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Log;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = -10;

    protected static string $view = 'filament.pages.dashboard';

    /** @var list<class-string> */
    public array $layoutOrder = [];

    public bool $layoutCompact = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $capability = StaffCapability::for($user);

        return $capability->allowedDashboardWidgets() !== []
            || $capability->preferredHomeUrl() !== null;
    }

    public function mount(): void
    {
        $user = auth()->user();
        $capability = StaffCapability::for($user);
        $home = $capability->preferredHomeUrl();

        if ($home !== null) {
            $this->redirect($home);

            return;
        }

        $service = app(DashboardPreferencesService::class);
        $service->repairUserPreferences($user);
        $this->layoutOrder = $service->widgetsFor($user);
        $this->layoutCompact = $service->isCompact($user);
    }

    public function getWidgets(): array
    {
        return app(DashboardPreferencesService::class)->widgetsFor(auth()->user());
    }

    public function getColumns(): int|string|array
    {
        $compact = app(DashboardPreferencesService::class)->isCompact(auth()->user());

        return $compact
            ? ['default' => 1, 'sm' => 2, 'xl' => 3]
            : ['default' => 1, 'sm' => 2, 'lg' => 4];
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'data-isp-dashboard' => '1',
            'data-dashboard-stream' => route('admin.dashboard-stream'),
            'data-tenant-id' => (string) auth()->user()?->tenant_id,
            'class' => app(DashboardPreferencesService::class)->isCompact(auth()->user())
                ? 'isp-dashboard-compact'
                : '',
        ];
    }

    /**
     * @return array<class-string, string>
     */
    public function layoutWidgetLabels(): array
    {
        $capability = StaffCapability::for(auth()->user());

        return array_filter(
            DashboardPreferencesService::layoutWidgetLabels(),
            fn (string $class): bool => $capability->canSeeWidget($class),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return list<array{class: class-string, label: string, enabled: bool}>
     */
    public function layoutRows(): array
    {
        $labels = $this->layoutWidgetLabels();
        $rows = [];

        foreach ($this->layoutOrder as $class) {
            if (isset($labels[$class])) {
                $rows[] = ['class' => $class, 'label' => $labels[$class], 'enabled' => true];
            }
        }

        foreach ($labels as $class => $label) {
            if (! in_array($class, $this->layoutOrder, true)) {
                $rows[] = ['class' => $class, 'label' => $label, 'enabled' => false];
            }
        }

        return $rows;
    }

    public function toggleLayoutWidget(string $class): void
    {
        if (! array_key_exists($class, $this->layoutWidgetLabels())) {
            return;
        }

        $index = array_search($class, $this->layoutOrder, true);

        if ($index !== false) {
            array_splice($this->layoutOrder, $index, 1);
        } else {
            $this->layoutOrder[] = $class;
        }
    }

    public function moveLayoutWidgetUp(string $class): void
    {
        $index = array_search($class, $this->layoutOrder, true);

        if ($index === false || $index === 0) {
            return;
        }

        $tmp = $this->layoutOrder[$index - 1];
        $this->layoutOrder[$index - 1] = $this->layoutOrder[$index];
        $this->layoutOrder[$index] = $tmp;
    }

    public function moveLayoutWidgetDown(string $class): void
    {
        $index = array_search($class, $this->layoutOrder, true);

        if ($index === false || $index >= count($this->layoutOrder) - 1) {
            return;
        }

        $tmp = $this->layoutOrder[$index + 1];
        $this->layoutOrder[$index + 1] = $this->layoutOrder[$index];
        $this->layoutOrder[$index] = $tmp;
    }

    public function saveDashboardLayout(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        try {
            app(DashboardPreferencesService::class)->savePreferences(
                $user,
                $this->layoutOrder,
                $this->layoutCompact,
            );

            Notification::make()
                ->title('Dashboard layout saved')
                ->body('Your widget order and spacing are stored safely.')
                ->success()
                ->send();

            $this->redirect(static::getUrl(), navigate: false);
        } catch (\Throwable $e) {
            Log::error('dashboard_layout_save_failed', [
                'user_id' => $user->getKey(),
                'message' => $e->getMessage(),
            ]);

            $service = app(DashboardPreferencesService::class);
            $this->layoutOrder = $service->widgetsFor($user);
            $this->layoutCompact = $service->isCompact($user);

            Notification::make()
                ->title('Could not save layout')
                ->body('Your previous dashboard settings were kept. Please try again.')
                ->danger()
                ->send();
        }
    }
}
