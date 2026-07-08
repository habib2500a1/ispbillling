<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Support\PlatformSuperAdmin;
use App\Support\PrimaryTenant;
use App\Support\TenantSaasControls;
use App\Support\Rbac\IspModuleCatalog;
use App\Support\TenantSubscriptionCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 0;

    public static function canViewAny(): bool
    {
        return PlatformSuperAdmin::allows(auth()->user());
    }

    public static function canCreate(): bool
    {
        return PlatformSuperAdmin::allows(auth()->user());
    }

    public static function canEdit($record): bool
    {
        return PlatformSuperAdmin::allows(auth()->user());
    }

    public static function canDelete($record): bool
    {
        return PlatformSuperAdmin::allows(auth()->user())
            && ! PrimaryTenant::isPrimary($record->getKey());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return PlatformSuperAdmin::allows(auth()->user());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identity')->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('organization_type')
                        ->label('Organization type')
                        ->options([
                            'single_isp' => 'Single ISP',
                            'multi_isp' => 'Multi ISP',
                            'multi_branch' => 'Multi Branch',
                            'franchise' => 'Franchise ISP',
                            'reseller_isp' => 'Reseller ISP',
                        ])
                        ->default('single_isp')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->required()
                        ->disabled(fn (?Tenant $record): bool => $record !== null && PrimaryTenant::isPrimary($record->getKey()))
                        ->helperText(fn (?Tenant $record): ?string => $record !== null && PrimaryTenant::isPrimary($record->getKey())
                            ? 'Primary ISP stays active — cannot be turned off.'
                            : null),
                ])->columns(2),
                Forms\Components\Section::make('Contact & domain')->schema([
                    Forms\Components\TextInput::make('domain')
                        ->placeholder('bill.example.com')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('address')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('contact_phone')
                        ->tel()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('contact_email')
                        ->email()
                        ->maxLength(255),
                ])->columns(2),
                Forms\Components\Section::make('SaaS package (sell plan)')
                    ->description('Set customer cap, your monthly platform fee (BDT), and bill date when onboarding a new ISP.')
                    ->schema([
                        Forms\Components\Select::make('settings.subscription.plan_key')
                            ->label('Package')
                            ->options(TenantSubscriptionCatalog::selectOptions())
                            ->default(TenantSubscriptionCatalog::PLAN_STARTER_100)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                                $plan = TenantSubscriptionCatalog::plans()[$state ?? ''] ?? null;
                                if ($plan === null) {
                                    return;
                                }
                                $set('settings.subscription.plan_name', $plan['label']);
                                $set('settings.subscription.max_customers', $plan['max_customers']);
                                $set('settings.subscription.monthly_fee_bdt', $plan['monthly_fee_bdt']);
                            }),
                        Forms\Components\TextInput::make('settings.subscription.max_customers')
                            ->label('Max customers')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Unlimited')
                            ->helperText('Leave empty for unlimited (Enterprise / Custom).')
                            ->visible(fn (Forms\Get $get): bool => $get('settings.subscription.plan_key') === TenantSubscriptionCatalog::PLAN_CUSTOM),
                        Forms\Components\TextInput::make('settings.subscription.monthly_fee_bdt')
                            ->label('Platform fee (BDT / month)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix('BDT'),
                        Forms\Components\TextInput::make('settings.subscription.billing_day')
                            ->label('Platform bill day')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(28)
                            ->default(1)
                            ->required()
                            ->helperText('Day of month you invoice this ISP for the software (1–28).'),
                        Forms\Components\Select::make('settings.subscription.status')
                            ->label('Subscription status')
                            ->options([
                                'active' => 'Active',
                                'trial' => 'Trial',
                                'suspended' => 'Suspended',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\Textarea::make('settings.subscription.notes')
                            ->label('Internal notes')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('subscription_auto_bill')
                            ->label('Auto invoice')
                            ->content('Software bill auto-generates at 06:00 on bill day via Automatic process → Generate platform SaaS invoices.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('ISP modules (tenant-wide)')
                    ->description('Disable OLT, Map, Billing, etc. for this entire ISP. Main admin on this tenant also loses access.')
                    ->schema(collect(IspModuleCatalog::modules())->map(
                        fn (array $meta, string $key): Forms\Components\Toggle => Forms\Components\Toggle::make('settings.enabled_modules.'.$key)
                            ->label($meta['label'])
                            ->helperText($meta['hint'])
                            ->default(true),
                    )->values()->all())
                    ->columns(2)
                    ->collapsed(),
                Forms\Components\Section::make('Platform resale controls')
                    ->description('Rented / sub ISPs cannot create partners or grant Admin roles unless you enable it here.')
                    ->schema([
                        Forms\Components\Toggle::make('settings.platform_controls.allow_reseller_creation')
                            ->label('Allow reseller / partner creation')
                            ->default(fn (?Tenant $record): bool => $record === null
                                ? false
                                : TenantSaasControls::allowsResellerCreation($record))
                            ->helperText('When off, this tenant cannot add new resellers in admin.'),
                        Forms\Components\Toggle::make('settings.platform_controls.allow_staff_admin_roles')
                            ->label('Allow Admin / ISP Admin staff roles')
                            ->default(fn (?Tenant $record): bool => $record === null
                                ? false
                                : TenantSaasControls::allowsStaffAdminRoles($record))
                            ->helperText('When off, staff users cannot be promoted to full Admin.'),
                    ])
                    ->columns(2)
                    ->visible(fn (): bool => PlatformSuperAdmin::allows(auth()->user())),
                Forms\Components\Section::make('Branding')->schema([
                    Forms\Components\TextInput::make('branding.app_name')
                        ->label('App name')
                        ->maxLength(255),
                    Forms\Components\ColorPicker::make('branding.primary_color')
                        ->label('Primary color'),
                    Forms\Components\ColorPicker::make('branding.accent_color')
                        ->label('Accent color'),
                    Forms\Components\Select::make('branding.theme')
                        ->label('Theme')
                        ->options([
                            'default' => 'Default',
                            'dark' => 'Dark',
                            'light' => 'Light',
                        ])
                        ->default('default'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Tenant $record): ?string => PrimaryTenant::isPrimary($record->getKey())
                        ? 'Primary ISP (protected)'
                        : null),
                Tables\Columns\TextColumn::make('organization_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'multi_isp' => 'Multi ISP',
                        'multi_branch' => 'Multi Branch',
                        'franchise' => 'Franchise',
                        'reseller_isp' => 'Reseller ISP',
                        default => 'Single ISP',
                    }),
                Tables\Columns\TextColumn::make('subscription_plan')
                    ->label('Package')
                    ->state(function (Tenant $record): string {
                        $sub = is_array($record->settings['subscription'] ?? null) ? $record->settings['subscription'] : [];

                        return (string) ($sub['plan_name'] ?? 'Starter');
                    }),
                Tables\Columns\TextColumn::make('subscription_usage')
                    ->label('Customers')
                    ->state(function (Tenant $record): string {
                        $sub = app(\App\Services\Tenant\TenantSubscriptionService::class)->forTenant($record->id);
                        $max = $sub['max_customers'];

                        return $max === null
                            ? $sub['customers_used'].' / ∞'
                            : $sub['customers_used'].' / '.$max;
                    }),
                Tables\Columns\TextColumn::make('subscription_fee')
                    ->label('Fee/mo')
                    ->state(function (Tenant $record): string {
                        $sub = is_array($record->settings['subscription'] ?? null) ? $record->settings['subscription'] : [];
                        $fee = (float) ($sub['monthly_fee_bdt'] ?? 0);

                        return $fee > 0 ? number_format($fee, 0).' BDT' : '—';
                    }),
                Tables\Columns\TextColumn::make('domain')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $deletable = $records->reject(
                                fn (Tenant $tenant): bool => PrimaryTenant::isPrimary($tenant->getKey()),
                            );

                            if ($deletable->isEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Primary ISP cannot be deleted')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $deletable->each->delete();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
