<?php

namespace App\Filament\Resources;

use App\Filament\Pages\PermissionMatrix;
use App\Filament\Resources\RoleResource\Pages;
use App\Forms\Components\PermissionMatrixPicker;
use App\Support\Rbac\IspPermissionCatalog;
use App\Support\Rbac\IspRoleTemplates;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Roles & permissions';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 25;

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasRole('super-admin')
            || $user->hasRole('isp-admin')
            || $user->can('security.roles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Role')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Role key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Lowercase slug, e.g. branch-manager'),
                        Forms\Components\Select::make('role_template')
                            ->label('Apply template (new role only)')
                            ->options(IspRoleTemplates::options())
                            ->searchable()
                            ->live()
                            ->visibleOn('create')
                            ->helperText('Pre-select permissions from a built-in role template'),
                        Forms\Components\Placeholder::make('template_info')
                            ->label('Template description')
                            ->content(function (Forms\Get $get): string {
                                $slug = $get('role_template');
                                if (! is_string($slug) || $slug === '') {
                                    return '—';
                                }

                                return IspRoleTemplates::get($slug)['description'] ?? '—';
                            })
                            ->visibleOn('create')
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('guard_name')
                            ->default('web'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Permission matrix')
                    ->description('Grouped categories with labels and keys — same layout as the full matrix page.')
                    ->headerActions([
                        FormAction::make('open_full_matrix')
                            ->label('Open full matrix')
                            ->icon('heroicon-o-table-cells')
                            ->url(fn (?Role $record): string => $record
                                ? PermissionMatrix::getUrl().'?role='.urlencode($record->name)
                                : PermissionMatrix::getUrl())
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        PermissionMatrixPicker::make('permission_keys')
                            ->label('')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\View::make('filament.resources.role-audit-timeline')
                    ->visibleOn('edit')
                    ->viewData(fn (?Role $record): array => [
                        'auditTimeline' => $record
                            ? app(\App\Services\Rbac\RolePermissionService::class)->auditTimelineForRole($record)
                            : collect(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Role')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => IspRoleTemplates::get($state)['label'] ?? $state)
                    ->description(fn (Role $record): string => ($record->name).' · '.(IspRoleTemplates::get($record->name)['description'] ?? '')),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated')
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('clone')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('New role key')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Role $record, array $data): void {
                        app(\App\Services\Rbac\RolePermissionService::class)
                            ->cloneRole($record, $data['name']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records): void {
                            foreach ($records as $role) {
                                if (in_array($role->name, ['super-admin', 'isp-admin'], true)) {
                                    throw new \RuntimeException('Cannot delete system roles.');
                                }
                            }
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
