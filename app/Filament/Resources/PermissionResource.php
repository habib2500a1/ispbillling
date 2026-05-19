<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Permission;
use App\Support\Rbac\IspPermissionCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Permissions';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('security.roles'));
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('super-admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Permission')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Permission key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->regex('/^[a-z0-9._-]+$/')
                            ->helperText('Lowercase key used in code, e.g. customers.export')
                            ->disabled(fn (?Permission $record): bool => $record !== null
                                && in_array($record->name, IspPermissionCatalog::all(), true))
                            ->dehydrated(),
                        Forms\Components\TextInput::make('display_name')
                            ->label('Display label')
                            ->maxLength(255)
                            ->helperText('Shown in role matrix and permission list'),
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options(IspPermissionCatalog::categoryLabels())
                            ->searchable()
                            ->native(false),
                        Forms\Components\Hidden::make('guard_name')
                            ->default('web'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Label')
                    ->searchable()
                    ->sortable()
                    ->placeholder(fn (Permission $record): string => $record->resolvedLabel())
                    ->description(fn (Permission $record): string => $record->name),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn (Permission $record): string => $record->resolvedCategory() ?? '—')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Roles')
                    ->badge(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(),
            ])
            ->defaultSort('category')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(IspPermissionCatalog::categoryLabels()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->hasRole('super-admin') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole('super-admin') ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
