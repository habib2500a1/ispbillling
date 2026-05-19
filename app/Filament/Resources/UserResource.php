<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Branch;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Staff users';

    protected static ?string $modelLabel = 'staff user';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('staff.view'));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('staff.manage'));
    }

    public static function form(Form $form): Form
    {
        $isSuper = auth()->user()?->hasRole('super-admin') ?? false;

        return $form->schema([
            Forms\Components\Section::make('Account')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
            Forms\Components\Section::make('Organization')->schema([
                Forms\Components\Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->visible($isSuper)
                    ->required($isSuper),
                Forms\Components\Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Forms\Components\Select::make('roles')
                    ->label('Roles')
                    ->multiple()
                    ->options(fn (): array => Role::query()
                        ->when(! $isSuper, fn ($q) => $q->where('name', '!=', 'super-admin'))
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->all())
                    ->required(),
            ])->columns(2),
            Forms\Components\Section::make('IP allowlist (optional)')->schema([
                Forms\Components\TagsInput::make('allowed_ips')
                    ->label('Allowed IPs')
                    ->placeholder('192.168.1.10 or 10.0.0.0/24')
                    ->helperText('Leave empty to inherit branch/tenant rules only.'),
            ]),
            Forms\Components\Section::make('Session activity')
                ->schema([
                    Forms\Components\Placeholder::make('last_login_at')
                        ->label('Last login')
                        ->content(fn (?User $record): string => $record?->last_login_at
                            ? $record->last_login_at->diffForHumans().' ('.$record->last_login_at->format('Y-m-d H:i').')'
                            : '—'),
                    Forms\Components\Placeholder::make('last_logout_at')
                        ->label('Last logout')
                        ->content(fn (?User $record): string => $record?->last_logout_at
                            ? $record->last_logout_at->diffForHumans().' ('.$record->last_logout_at->format('Y-m-d H:i').')'
                            : '—'),
                ])
                ->columns(2)
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->toggleable(),
                Tables\Columns\TextColumn::make('roles.name')->badge()->label('Roles'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('two_factor_confirmed_at')
                    ->label('2FA')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->hasTwoFactorEnabled()),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->dateTimeTooltip()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_login_ip')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_logout_at')
                    ->label('Last logout')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_logout_ip')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('branch_id')->relationship('branch', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['branch', 'roles']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
