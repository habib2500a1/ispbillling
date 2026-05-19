<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\BandwidthClientResource\Pages;
use App\Models\BandwidthClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BandwidthClientResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = BandwidthClient::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Bandwidth clients';

    protected static ?string $modelLabel = 'bandwidth client';

    protected static ?string $pluralModelLabel = 'bandwidth clients';

    protected static ?string $navigationGroup = 'BW Client';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Client')
                ->schema([
                    Forms\Components\TextInput::make('client_code')->label('Client ID')->maxLength(32),
                    Forms\Components\TextInput::make('name')->required()->maxLength(160),
                    Forms\Components\TextInput::make('contact_person')->maxLength(120),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
                    Forms\Components\TextInput::make('email')->email(),
                    Forms\Components\Textarea::make('address')->rows(2)->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Billing profile')
                ->schema([
                    Forms\Components\TextInput::make('profile_total')
                        ->label('Profile total (BDT / month)')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->minValue(0),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'suspended' => 'Suspended',
                        ])
                        ->default('active')
                        ->native(false),
                    Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_code')
                    ->label('ID')
                    ->fontFamily('mono')
                    ->searchable()
                    ->placeholder(fn (BandwidthClient $record): string => 'BW-'.$record->id),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('semibold'),
                Tables\Columns\TextColumn::make('contact')
                    ->label('Contact')
                    ->state(fn (BandwidthClient $record): string => $record->contactLabel()),
                Tables\Columns\TextColumn::make('profile_total')
                    ->label('Profile total')
                    ->money('BDT')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_total')
                    ->label('Paid amount')
                    ->money('BDT')
                    ->state(fn (BandwidthClient $record): float => $record->paidAmount()),
                Tables\Columns\TextColumn::make('due_total')
                    ->label('Total due')
                    ->money('BDT')
                    ->color(fn (BandwidthClient $record): string => $record->totalDue() > 0 ? 'danger' : 'success')
                    ->state(fn (BandwidthClient $record): float => $record->totalDue()),
                Tables\Columns\TextColumn::make('due_invoices')
                    ->label('Due invoices')
                    ->state(fn (BandwidthClient $record): int => $record->dueInvoicesCount())
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->defaultPaginationPageOption(20)
            ->paginated([10, 20, 50, 100])
            ->defaultSort('name')
            ->striped()
            ->emptyStateHeading('No bandwidth clients found')
            ->emptyStateDescription('Add wholesale / upstream bandwidth buyers you invoice separately from PPP subscribers.')
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBandwidthClients::route('/'),
            'create' => Pages\CreateBandwidthClient::route('/create'),
            'edit' => Pages\EditBandwidthClient::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'billing';
    }
}
