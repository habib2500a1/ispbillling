<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BandwidthClientInvoiceResource;
use App\Filament\Resources\BandwidthClientResource;
use App\Models\BandwidthClient;
use App\Services\Bandwidth\BandwidthClientBillingService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GenerateBandwidthInvoice extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    protected static string $view = 'filament.pages.generate-bandwidth-invoice';

    protected static ?string $navigationLabel = 'Generate BW invoice';

    protected static ?string $title = 'Generate bandwidth invoice';

    protected static ?string $slug = 'generate-bandwidth-invoice';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return BandwidthClientResource::canViewAny();
    }

    public function mount(): void
    {
        $this->form->fill([
            'bandwidth_client_id' => request()->integer('client') ?: null,
            'period_month' => (int) now()->month,
            'period_year' => (int) now()->year,
            'amount' => null,
            'notes' => '',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('New bandwidth invoice')
                    ->description('Create a monthly invoice for a wholesale / upstream bandwidth client.')
                    ->schema([
                        Select::make('bandwidth_client_id')
                            ->label('Client')
                            ->options(fn () => BandwidthClient::query()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(function (?string $state, callable $set): void {
                                if (! $state) {
                                    return;
                                }
                                $client = BandwidthClient::query()->find($state);
                                if ($client) {
                                    $set('amount', (string) $client->profile_total);
                                }
                            }),
                        Select::make('period_month')
                            ->label('Month')
                            ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))]))
                            ->required()
                            ->native(false),
                        TextInput::make('period_year')
                            ->label('Year')
                            ->numeric()
                            ->required()
                            ->default(now()->year),
                        TextInput::make('amount')
                            ->label('Amount (BDT)')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        Textarea::make('notes')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $data = $this->form->getState();
        $client = BandwidthClient::query()->findOrFail($data['bandwidth_client_id']);

        $invoice = app(BandwidthClientBillingService::class)->generateInvoice(
            $client,
            (int) $data['period_month'],
            (int) $data['period_year'],
            (float) $data['amount'],
            filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
        );

        Notification::make()
            ->title('Invoice generated')
            ->body($invoice->invoice_number.' · '.number_format((float) $invoice->amount, 2).' BDT')
            ->success()
            ->send();

        $this->redirect(BandwidthClientInvoiceResource::getUrl('edit', ['record' => $invoice]));
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
