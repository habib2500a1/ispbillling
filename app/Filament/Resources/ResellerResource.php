<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\ResellerResource\Pages;
use App\Filament\Resources\ResellerResource\RelationManagers;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Resellers\ResellerPortalAccessService;
use App\Support\ResellerBranding;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;

class ResellerResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = Reseller::class;

    protected static function permissionPrefix(): string
    {
        return 'resellers';
    }

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Resellers';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Reseller account')
                    ->description('Core identity and hierarchy for this partner.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->maxLength(64)
                            ->helperText('Leave blank to auto-generate.'),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_id_prefix')
                            ->label('Client ID prefix')
                            ->maxLength(32)
                            ->placeholder('e.g. ABC')
                            ->helperText('Prefix for subscriber IDs created under this reseller.'),
                        Forms\Components\Select::make('franchise_type')
                            ->label('Partner type')
                            ->options(ResellerType::labels())
                            ->default(ResellerType::RESELLER)
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('is_active')
                            ->label('Status')
                            ->options(['1' => 'Active', '0' => 'Inactive'])
                            ->default('1')
                            ->required()
                            ->dehydrateStateUsing(fn (string $state): bool => $state === '1')
                            ->formatStateUsing(fn (?bool $state): string => ($state ?? true) ? '1' : '0'),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent reseller')
                            ->options(fn (): array => Reseller::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->placeholder('Top-level reseller')
                            ->helperText('Select only if creating a sub-reseller.')
                            ->nullable(),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Contact & address')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
                        Forms\Components\TextInput::make('contact_person')->label('Contact person')->maxLength(255),
                        Forms\Components\TextInput::make('address')->maxLength(255)->columnSpanFull(),
                        Forms\Components\TextInput::make('city')->maxLength(64),
                        Forms\Components\TextInput::make('district')->maxLength(64),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Commission & wallet')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\ToggleButtons::make('commission_type')
                            ->label('Commission type')
                            ->options(['percent' => 'Percentage', 'fixed' => 'Fixed amount'])
                            ->default('percent')
                            ->required()
                            ->live()
                            ->inline()
                            ->grouped()
                            ->helperText('Fixed amount = flat BDT per payment. Percentage = share of payment amount.'),
                        Forms\Components\TextInput::make('commission_value')
                            ->label(fn (Get $get): string => $get('commission_type') === 'fixed' ? 'Fixed commission (BDT)' : 'Default commission %')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->minValue(0)
                            ->suffix(fn (Get $get): ?string => $get('commission_type') === 'fixed' ? 'BDT' : '%')
                            ->helperText(fn (Get $get): string => $get('commission_type') === 'fixed'
                                ? 'Flat BDT credited per completed payment (capped at payment amount).'
                                : 'Percent of each completed payment (e.g. 10 = 10%).'),
                        Forms\Components\TextInput::make('revenue_share_percent')
                            ->label('Parent revenue share %')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Get $get, ?Reseller $record): bool => filled($get('parent_id')) || $record?->parent_id !== null),
                        Forms\Components\TextInput::make('opening_balance')
                            ->label('Opening balance')
                            ->numeric()
                            ->default(0)
                            ->prefix('BDT')
                            ->visibleOn('create')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('wallet_balance')
                            ->label('Wallet balance')
                            ->numeric()
                            ->disabled()
                            ->visibleOn('edit'),
                        Forms\Components\TextInput::make('max_clients')
                            ->label('Max clients')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Leave empty for unlimited.'),
                        Forms\Components\TextInput::make('max_active_clients')
                            ->label('Max active clients')
                            ->numeric()
                            ->minValue(1)
                            ->nullable(),
                        Forms\Components\Toggle::make('wallet_frozen')
                            ->label('Freeze wallet')
                            ->helperText('Blocks settlements and wallet debits.'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Automation')
                    ->schema([
                        Forms\Components\Toggle::make('auto_invoice_enabled')
                            ->label('Auto first invoice on new subscriber')
                            ->default(true),
                        Forms\Components\Toggle::make('auto_suspend_enabled')
                            ->label('Auto suspend on due (via system jobs)')
                            ->default(true)
                            ->helperText('Uses global billing automation; flagged on subscriber meta.'),
                    ])
                    ->columns(2)
                    ->collapsed(),
                Forms\Components\Section::make('Portal permissions')
                    ->description('Only checked capabilities are available in the partner portal (/reseller). Leave empty to use defaults for partner type.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\CheckboxList::make('portal_permissions')
                            ->label('Allowed portal actions')
                            ->options(ResellerPortalPermission::labels())
                            ->columns(2)
                            ->bulkToggleable()
                            ->helperText('Unchecked list with no selection = automatic defaults by partner type.'),
                    ])
                    ->collapsed(),
                Forms\Components\Section::make('Portal integrations')
                    ->description('Own SMS gateway and personal bKash/Nagad in partner portal (/reseller/settings).')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Toggle::make('own_integrations_enabled')
                            ->label('Allow own SMS & payment integrations')
                            ->helperText('Partner configures own API keys in portal. Also grant the "SMS & payment integrations" permission.'),
                    ])
                    ->collapsed(),
                Forms\Components\Section::make('Portal login')
                    ->description('Credentials for /reseller/login partner portal.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Forms\Components\TextInput::make('portal_login')
                            ->label('Reseller user ID')
                            ->maxLength(64)
                            ->helperText('Login username. Defaults to partner code if empty.'),
                        Forms\Components\TextInput::make('portal_password')
                            ->label('Login password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Leave blank when editing to keep the current password.'),
                        Forms\Components\Select::make('primary_user_id')
                            ->label('Primary user')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->placeholder('Select staff user')
                            ->helperText('Optional HQ staff owner for this partner account.'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Documents')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\TextInput::make('trade_license')->label('Trade license no.')->maxLength(64),
                        Forms\Components\TextInput::make('nid_number')->label('NID / representative ID')->maxLength(32),
                        Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(),
                Forms\Components\Section::make('White-label branding')
                    ->description('Partner logo and name on /pay, customer portal, and payment pages for their subscribers. Optional subdomain when ISP_TENANT_BASE_DOMAIN is set.')
                    ->schema([
                        Forms\Components\Toggle::make('white_label_enabled')
                            ->label('Enable white-label')
                            ->live(),
                        Forms\Components\TextInput::make('brand_name')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled')),
                        Forms\Components\TextInput::make('portal_subdomain')
                            ->label('Portal subdomain')
                            ->placeholder('partner1')
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled')),
                        Forms\Components\ColorPicker::make('brand_primary_color')
                            ->label('Brand color')
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled')),
                        Forms\Components\FileUpload::make('brand_logo_path')
                            ->label('Logo')
                            ->image()
                            ->directory('reseller-brands')
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled')),
                        Forms\Components\Placeholder::make('ssl_subdomain_guide')
                            ->label('Subdomain & SSL setup')
                            ->content(fn (?Reseller $record): string => ResellerBranding::sslSetupGuide($record))
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled'))
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('partner_customer_links')
                            ->label('Customer-facing links')
                            ->content(function (?Reseller $record): string {
                                if ($record === null || ! $record->white_label_enabled) {
                                    return 'Save with white-label enabled to generate shareable customer links.';
                                }

                                $links = ResellerBranding::shareableLinks($record);
                                $lines = [
                                    'Bill pay: '.$links['pay'],
                                    'Customer login: '.$links['portal_login'],
                                ];
                                if (isset($links['subdomain_pay'])) {
                                    $lines[] = 'Subdomain pay: '.$links['subdomain_pay'];
                                    $lines[] = 'Subdomain portal: '.$links['subdomain_portal'];
                                }

                                return implode("\n", $lines);
                            })
                            ->visible(fn (Get $get, ?Reseller $record): bool => (bool) $get('white_label_enabled') && $record !== null)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\Grid::make(['default' => 2, 'md' => 4])
                            ->schema([
                                Infolists\Components\TextEntry::make('customers_count')
                                    ->label('Subscribers')
                                    ->state(fn (Reseller $record): int => $record->customers()->count())
                                    ->icon('heroicon-o-users')
                                    ->color('info')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                Infolists\Components\TextEntry::make('children_count')
                                    ->label('Sub-resellers')
                                    ->state(fn (Reseller $record): int => $record->children()->count())
                                    ->icon('heroicon-o-user-group')
                                    ->color('warning')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                Infolists\Components\TextEntry::make('wallet_balance')
                                    ->label('Wallet balance')
                                    ->money('BDT')
                                    ->icon('heroicon-o-wallet')
                                    ->color(fn (Reseller $record): string => (float) $record->wallet_balance < 0 ? 'danger' : 'success')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                Infolists\Components\TextEntry::make('pending_commission')
                                    ->label('Pending commission')
                                    ->state(fn (Reseller $record): string => number_format((float) $record->commissions()->where('status', \App\Models\ResellerCommission::STATUS_PENDING)->sum('commission_amount'), 2).' BDT')
                                    ->icon('heroicon-o-clock')
                                    ->color('danger')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                            ]),
                    ]),
                Infolists\Components\Section::make('Overview')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')->size(Infolists\Components\TextEntry\TextEntrySize::Large)->weight(\Filament\Support\Enums\FontWeight::Bold),
                        Infolists\Components\TextEntry::make('code')->fontFamily('mono')->copyable(),
                        Infolists\Components\TextEntry::make('client_id_prefix')->label('Client prefix')->placeholder('—'),
                        Infolists\Components\TextEntry::make('franchise_type')
                            ->formatStateUsing(fn (Reseller $record): string => $record->franchiseTypeLabel())
                            ->badge(),
                        Infolists\Components\TextEntry::make('parent.name')->label('Parent')->placeholder('—'),
                        Infolists\Components\IconEntry::make('is_active')->boolean()->label('Active'),
                        Infolists\Components\TextEntry::make('wallet_balance')->money('BDT'),
                        Infolists\Components\TextEntry::make('commission_value')
                            ->label('Commission')
                            ->formatStateUsing(fn (Reseller $record): string => $record->commissionLabel()),
                        Infolists\Components\TextEntry::make('portal_login')
                            ->label('Portal login')
                            ->formatStateUsing(fn (Reseller $record): string => $record->portalLoginId()),
                        Infolists\Components\TextEntry::make('primaryUser.name')->label('Primary user')->placeholder('—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Partner portal access')
                    ->description('Log in as this partner or share credentials.')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->schema([
                        Infolists\Components\TextEntry::make('portal_login')
                            ->label('Login ID')
                            ->formatStateUsing(fn (Reseller $record): string => $record->portalLoginId())
                            ->copyable()
                            ->fontFamily('mono'),
                        Infolists\Components\TextEntry::make('portal_admin_login')
                            ->label('Admin portal login')
                            ->formatStateUsing(fn (): string => 'Open partner portal')
                            ->url(fn (Reseller $record): string => route('staff.resellers.portal-login', ['reseller' => $record->getKey()]))
                            ->openUrlInNewTab()
                            ->color('success')
                            ->icon('heroicon-o-arrow-right-on-rectangle'),
                        Infolists\Components\TextEntry::make('portal_token_url')
                            ->label('Token login URL')
                            ->formatStateUsing(function (Reseller $record): string {
                                $portal = app(ResellerPortalAccessService::class);
                                $portal->ensurePortalPassword($record);

                                return $portal->accessTokenUrl($record->fresh() ?? $record);
                            })
                            ->copyable()
                            ->columnSpanFull()
                            ->fontFamily('mono')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Customer links')
                    ->visible(fn (Reseller $record): bool => $record->white_label_enabled)
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label('Bill pay URL')
                            ->formatStateUsing(fn (Reseller $record): string => ResellerBranding::shareableLinks($record)['pay'])
                            ->copyable()
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('portal_subdomain')
                            ->label('Customer portal login')
                            ->formatStateUsing(fn (Reseller $record): string => ResellerBranding::shareableLinks($record)['portal_login'])
                            ->copyable()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
                Infolists\Components\Section::make('Contact')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Infolists\Components\TextEntry::make('contact_person')->placeholder('—'),
                        Infolists\Components\TextEntry::make('email')->placeholder('—')->copyable(),
                        Infolists\Components\TextEntry::make('phone')->placeholder('—')->copyable(),
                        Infolists\Components\TextEntry::make('address')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible(),
                Infolists\Components\Section::make('Automation & access')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Infolists\Components\IconEntry::make('auto_invoice_enabled')
                            ->label('Auto first invoice')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('auto_suspend_enabled')
                            ->label('Auto suspend on due')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('own_integrations_enabled')
                            ->label('Own integrations')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('white_label_enabled')
                            ->label('White-label')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('portal_permissions')
                            ->label('Portal permissions')
                            ->state(fn (Reseller $record): string => count($record->portalPermissions()).' enabled')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(3)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->fontFamily('mono')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('id')
                    ->label('Portal')
                    ->formatStateUsing(fn (): string => 'Portal login')
                    ->url(fn (Reseller $record): string => route('staff.resellers.portal-login', ['reseller' => $record->getKey()]))
                    ->openUrlInNewTab()
                    ->color('success')
                    ->weight('bold')
                    ->extraAttributes(['class' => 'isp-portal-login-col']),
                Tables\Columns\TextColumn::make('client_id_prefix')->label('Prefix')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('franchise_type')
                    ->label('Type')
                    ->formatStateUsing(fn (Reseller $record): string => $record->franchiseTypeLabel())
                    ->badge(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent')->placeholder('—'),
                Tables\Columns\TextColumn::make('customers_count')->counts('customers')->label('Customers'),
                Tables\Columns\TextColumn::make('reseller_packages_count')
                    ->counts('resellerPackages')
                    ->label('Packages')
                    ->placeholder('All'),
                Tables\Columns\TextColumn::make('commission_value')
                    ->label('Commission')
                    ->formatStateUsing(fn (Reseller $record): string => $record->commissionLabel()),
                Tables\Columns\TextColumn::make('wallet_balance')->money('BDT')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->defaultSort('id', 'desc')
            ->actionsPosition(ActionsPosition::AfterColumns)
            ->actionsColumnLabel('Actions')
            ->filters([
                Tables\Filters\SelectFilter::make('franchise_type')->options(ResellerType::labels()),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    static::resellerPortalCredentialsAction(),
                    static::resellerPortalResetPasswordAction(),
                    static::resellerPortalRegenerateTokenAction(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ChildrenRelationManager::class,
            RelationManagers\StaffRelationManager::class,
            RelationManagers\PackagesRelationManager::class,
            RelationManagers\TerritoriesRelationManager::class,
            RelationManagers\CustomersRelationManager::class,
            RelationManagers\CommissionsRelationManager::class,
            RelationManagers\SettlementsRelationManager::class,
            RelationManagers\WalletRechargesRelationManager::class,
            RelationManagers\BalanceTransfersRelationManager::class,
            RelationManagers\ActivityLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResellers::route('/'),
            'create' => Pages\CreateReseller::route('/create'),
            'view' => Pages\ViewReseller::route('/{record}'),
            'edit' => Pages\EditReseller::route('/{record}/edit'),
        ];
    }

    protected static function resellerPortalCredentialsAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reseller_portal_credentials')
            ->label('Portal ID & password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->modalHeading(fn (Reseller $record): string => 'Portal access — '.$record->name)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (Reseller $record): \Illuminate\Contracts\View\View {
                $portal = app(ResellerPortalAccessService::class);
                $portal->ensurePortalPassword($record);
                $fresh = $record->fresh() ?? $record;
                $token = $portal->ensureAccessToken($fresh);
                $login = $portal->portalLoginId($fresh);
                $passwordPlain = $portal->portalPasswordPlain($fresh);
                $link = $portal->accessTokenUrl($fresh);

                return view('filament.resources.reseller-resource.portal-access-modal', [
                    'login' => $login,
                    'passwordPlain' => $passwordPlain,
                    'token' => $token,
                    'link' => $link,
                ]);
            });
    }

    protected static function resellerPortalResetPasswordAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reseller_portal_reset_password')
            ->label('Reset portal password')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->modalDescription(fn (): string => 'Set portal password to default: '.config('reseller_portal.default_password', '123456'))
            ->action(function (Reseller $record): void {
                $portal = app(ResellerPortalAccessService::class);
                $plain = $portal->resetPortalPassword($record);
                Notification::make()
                    ->title('Portal password reset')
                    ->body("Login: ".$portal->portalLoginId($record)."\nPassword: {$plain}")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    protected static function resellerPortalRegenerateTokenAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reseller_portal_regenerate_token')
            ->label('New portal token')
            ->icon('heroicon-o-link')
            ->requiresConfirmation()
            ->action(function (Reseller $record): void {
                $portal = app(ResellerPortalAccessService::class);
                $token = $portal->regenerateAccessToken($record);
                $link = $portal->accessTokenUrl($record->fresh() ?? $record);
                Notification::make()
                    ->title('Portal token regenerated')
                    ->body("Token: {$token}\nLink: {$link}")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
