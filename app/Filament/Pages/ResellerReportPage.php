<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
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

    protected static ?string $navigationLabel = 'Report';

    protected static ?string $title = 'Reseller report';

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
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'reseller_id' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->required()->live(),
                DatePicker::make('to')->required()->live(),
                Select::make('reseller_id')
                    ->label('Reseller')
                    ->options(fn (): array => Reseller::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('All resellers')
                    ->live(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportProperty(): array
    {
        $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->data['to'] ?? now())->endOfDay();
        $resellerId = filled($this->data['reseller_id'] ?? null) ? (int) $this->data['reseller_id'] : null;

        return app(ResellerReportService::class)->summary($from, $to, $resellerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDetailRowsProperty(): array
    {
        $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->data['to'] ?? now())->endOfDay();
        $resellerId = filled($this->data['reseller_id'] ?? null) ? (int) $this->data['reseller_id'] : null;

        return app(ResellerReportService::class)->detailRows($from, $to, $resellerId);
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
        $filename = 'reseller-report-'.now()->format('Y-m-d-His').'.csv';

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
