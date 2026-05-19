<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntegrationSettingsAuditResource\Pages;
use App\Models\IntegrationSettingsAudit;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class IntegrationSettingsAuditResource extends Resource
{
    protected static ?string $model = IntegrationSettingsAudit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Integration audits';

    protected static ?string $modelLabel = 'audit entry';

    protected static ?string $pluralModelLabel = 'integration audits';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('summary')
                    ->wrap(),
            ])
            ->filters([])
            ->actions([
                ViewAction::make()
                    ->modalHeading('Audit context')
                    ->modalContent(fn (IntegrationSettingsAudit $record): HtmlString => new HtmlString(
                        '<pre class="max-h-96 overflow-auto whitespace-pre-wrap break-all text-xs">'
                        .e(json_encode($record->context ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        .'</pre>'
                    )),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegrationSettingsAudits::route('/'),
        ];
    }
}
