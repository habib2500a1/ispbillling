<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\Reseller;
use App\Models\ResellerStaff;
use App\Services\Resellers\ResellerStaffService;
use App\Support\ResellerPortalPermission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StaffRelationManager extends RelationManager
{
    protected static string $relationship = 'staff';

    protected static ?string $title = 'Portal staff';

    protected static ?string $icon = 'heroicon-o-users';

    public function form(Form $form): Form
    {
        /** @var Reseller $reseller */
        $reseller = $this->getOwnerRecord();
        $permissionOptions = app(ResellerStaffService::class)->permissionOptions($reseller);

        return $form
            ->schema([
                Forms\Components\Section::make('Staff account')
                    ->description('Staff log in at /reseller/login with their Login ID and password. Permissions cannot exceed this partner\'s portal permissions.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('login')
                            ->label('Login ID')
                            ->required()
                            ->maxLength(64)
                            ->alphaDash()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', $reseller->tenant_id))
                            ->helperText('Used at /reseller/login — letters, numbers, dash, underscore.'),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(32),
                        Forms\Components\TextInput::make('password')
                            ->label(fn (?Model $record): string => $record instanceof ResellerStaff ? 'New password' : 'Password')
                            ->password()
                            ->revealable()
                            ->required(fn (?Model $record): bool => ! $record instanceof ResellerStaff)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (?Model $record): ?string => $record instanceof ResellerStaff && $record->passwordPlain()
                                ? 'Current: '.$record->passwordPlain().' — leave blank to keep.'
                                : null),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Portal permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('portal_permissions')
                            ->label('Allowed actions')
                            ->options($permissionOptions)
                            ->columns(2)
                            ->bulkToggleable()
                            ->default(array_values(array_intersect([
                                ResellerPortalPermission::CUSTOMER_VIEW,
                                ResellerPortalPermission::BILLING_VIEW,
                                ResellerPortalPermission::PAYMENT_COLLECT,
                            ], array_keys($permissionOptions))))
                            ->helperText('Staff cannot manage other staff accounts.'),
                    ])
                    ->collapsed(fn (?Model $record): bool => $record instanceof ResellerStaff),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('login')
                    ->label('Login ID')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('portal_permissions')
                    ->label('Permissions')
                    ->formatStateUsing(fn (ResellerStaff $record): string => (string) count($record->portalPermissions()).' enabled')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->defaultSort('name')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add staff')
                    ->using(function (array $data): ResellerStaff {
                        /** @var Reseller $reseller */
                        $reseller = $this->getOwnerRecord();

                        return app(ResellerStaffService::class)->create($reseller, $data);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('credentials')
                    ->label('Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->modalHeading(fn (ResellerStaff $record): string => 'Portal login — '.$record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (ResellerStaff $record): \Illuminate\Contracts\View\View => view('filament.resources.reseller-resource.staff-credentials-modal', [
                        'login' => $record->login,
                        'passwordPlain' => $record->passwordPlain(),
                        'portalUrl' => url('/reseller/login'),
                    ])),
                Tables\Actions\EditAction::make()
                    ->using(function (ResellerStaff $record, array $data): ResellerStaff {
                        /** @var Reseller $reseller */
                        $reseller = $this->getOwnerRecord();

                        return app(ResellerStaffService::class)->update($record, $reseller, $data);
                    }),
                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ResellerStaff $record): bool => $record->is_active)
                    ->action(function (ResellerStaff $record): void {
                        $record->forceFill(['is_active' => false])->save();
                        Notification::make()->title('Staff deactivated')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Delete permanently'),
                ]),
            ])
            ->emptyStateHeading('No portal staff')
            ->emptyStateDescription('Add staff accounts so this partner\'s team can log in with limited permissions.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
