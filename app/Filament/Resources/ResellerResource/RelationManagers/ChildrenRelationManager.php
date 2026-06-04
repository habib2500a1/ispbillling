<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Support\ResellerType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Sub-resellers';

    protected static ?string $icon = 'heroicon-o-user-group';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic info')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('code')
                            ->maxLength(64)
                            ->helperText('Leave blank for auto code.')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),
                        Forms\Components\Select::make('franchise_type')
                            ->label('Partner type')
                            ->options(ResellerType::labels())
                            ->default(ResellerType::SUB_RESELLER)
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->columnSpan(2),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Contact')
                    ->schema([
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                        Forms\Components\TextInput::make('contact_person')->maxLength(255),
                        Forms\Components\TextInput::make('address')->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsed(),
                Forms\Components\Section::make('Commission & wallet')
                    ->schema([
                        Forms\Components\ToggleButtons::make('commission_type')
                            ->options(['percent' => 'Percentage', 'fixed' => 'Fixed amount'])
                            ->default('percent')
                            ->required()
                            ->inline()
                            ->grouped()
                            ->live(),
                        Forms\Components\TextInput::make('commission_value')
                            ->label(fn (Get $get): string => $get('commission_type') === 'fixed' ? 'Fixed commission (BDT)' : 'Commission %')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->minValue(0)
                            ->suffix(fn (Get $get): ?string => $get('commission_type') === 'fixed' ? 'BDT' : '%'),
                        Forms\Components\TextInput::make('revenue_share_percent')
                            ->label('Parent revenue share %')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Percentage of this sub-reseller\'s commission that goes to the parent.'),
                        Forms\Components\TextInput::make('opening_balance')
                            ->label('Opening wallet balance')
                            ->numeric()
                            ->default(0)
                            ->prefix('BDT')
                            ->visibleOn('create')
                            ->dehydrated(false),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Portal login')
                    ->schema([
                        Forms\Components\TextInput::make('portal_login')
                            ->label('Portal user ID')
                            ->maxLength(64)
                            ->helperText('Defaults to code if empty.'),
                        Forms\Components\TextInput::make('portal_password')
                            ->label('Portal password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Leave blank to keep current or auto-generate.'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->fontFamily('mono')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('franchise_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (Reseller $record): string => $record->franchiseTypeLabel()),
                Tables\Columns\TextColumn::make('customers_count')
                    ->counts('customers')
                    ->label('Customers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_value')
                    ->label('Commission')
                    ->formatStateUsing(fn (Reseller $record): string => $record->commissionLabel()),
                Tables\Columns\TextColumn::make('wallet_balance')
                    ->money('BDT')
                    ->sortable()
                    ->color(fn (Reseller $record): string => (float) $record->wallet_balance < 0 ? 'danger' : 'success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->defaultSort('name')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add sub-reseller')
                    ->mutateFormDataUsing(function (array $data): array {
                        $parent = $this->getOwnerRecord();
                        $data['parent_id'] = $parent->getKey();
                        // BUG FIX: tenant_id was missing, causing constraint violations
                        $data['tenant_id'] = $parent->tenant_id;

                        // Handle opening balance
                        if (isset($data['opening_balance']) && (float) $data['opening_balance'] > 0) {
                            $data['wallet_balance'] = (float) $data['opening_balance'];
                        } else {
                            $data['wallet_balance'] = 0;
                        }
                        unset($data['opening_balance']);

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Reseller $record): string => ResellerResource::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Reseller $record): void {
                        // Prevent deletion if has customers or sub-resellers
                        if ($record->customers()->exists()) {
                            throw new \Exception('Cannot delete: this sub-reseller has assigned customers.');
                        }
                        if ($record->children()->exists()) {
                            throw new \Exception('Cannot delete: this sub-reseller has its own sub-resellers.');
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No sub-resellers')
            ->emptyStateDescription('Create sub-resellers under this partner to build a hierarchy.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
