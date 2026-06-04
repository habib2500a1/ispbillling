<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerReportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResellerReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.reseller-report';

    protected static ?string $navigationLabel = 'Commission report';

    protected static ?string $title = 'Commission report';

    protected static ?string $slug = 'reseller-report';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ResellerResource::canViewAny();
    }

    public function mount(): void
    {
        $preset = request('preset', 'this_month');
        [$from, $to] = $this->datesForPreset($preset);

        $this->form->fill([
            'period_preset' => $preset,
            'from' => request('from', $from),
            'to' => request('to', $to),
            'reseller_id' => request('reseller_id') ? (int) request('reseller_id') : null,
            'status_filter' => request('status', 'all'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('period_preset')
                    ->label('Period')
                    ->options([
                        'this_month' => 'This month',
                        'last_month' => 'Last month',
                        'last_3_months' => 'Last 3 months',
                        'last_6_months' => 'Last 6 months',
                        'this_year' => 'This year',
                        'custom' => 'Custom range',
                    ])
                    ->default('this_month')
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        if ($state === null || $state === 'custom') {
                            return;
                        }
                        [$from, $to] = $this->datesForPreset($state);
                        $set('from', $from);
                        $set('to', $to);
                    }),
                DatePicker::make('from')->required()->live(),
                DatePicker::make('to')->required()->live(),
                Select::make('reseller_id')
                    ->label('Reseller')
                    ->options(fn (): array => Reseller::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('All resellers')
                    ->live(),
                Select::make('status_filter')
                    ->label('Status')
                    ->options([
                        'all' => 'All statuses',
                        ResellerCommission::STATUS_PENDING => 'Pending only',
                        ResellerCommission::STATUS_PAID => 'Paid only',
                        ResellerCommission::STATUS_CANCELLED => 'Cancelled only',
                    ])
                    ->default('all')
                    ->live(),
            ])
            ->columns(4)
            ->statePath('data');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function datesForPreset(string $preset): array
    {
        return match ($preset) {
            'last_month' => [
                now()->subMonth()->startOfMonth()->toDateString(),
                now()->subMonth()->endOfMonth()->toDateString(),
            ],
            'last_3_months' => [
                now()->subMonths(2)->startOfMonth()->toDateString(),
                now()->toDateString(),
            ],
            'last_6_months' => [
                now()->subMonths(5)->startOfMonth()->toDateString(),
                now()->toDateString(),
            ],
            'this_year' => [
                now()->startOfYear()->toDateString(),
                now()->toDateString(),
            ],
            default => [
                now()->startOfMonth()->toDateString(),
                now()->toDateString(),
            ],
        };
    }

    private function periodBounds(): array
    {
        $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->data['to'] ?? now())->endOfDay();
        $resellerId = filled($this->data['reseller_id'] ?? null) ? (int) $this->data['reseller_id'] : null;
        $status = $this->data['status_filter'] ?? 'all';

        return [$from, $to, $resellerId, $status === 'all' ? null : (string) $status];
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportProperty(): array
    {
        [$from, $to, $resellerId, $status] = $this->periodBounds();

        return app(ResellerReportService::class)->summary($from, $to, $resellerId, null, $status);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getMonthlyRowsProperty(): array
    {
        [$from, $to, $resellerId, $status] = $this->periodBounds();

        return app(ResellerReportService::class)->monthlyBreakdown($from, $to, $resellerId, null, $status);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStatusRowsProperty(): array
    {
        [$from, $to, $resellerId] = $this->periodBounds();

        return app(ResellerReportService::class)->statusBreakdown($from, $to, $resellerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDetailRowsProperty(): array
    {
        [$from, $to, $resellerId, $status] = $this->periodBounds();

        return app(ResellerReportService::class)->detailRows($from, $to, $resellerId, null, 500, $status);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    protected function exportCsv(): StreamedResponse
    {
        $rows = $this->detailRows;
        $filename = 'commission-report-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Earned at', 'Reseller', 'Customer', 'Gross', 'Commission', 'Status']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['earned_at'],
                    $row['reseller'],
                    $row['customer'],
                    $row['gross'],
                    $row['commission'],
                    $row['status'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
