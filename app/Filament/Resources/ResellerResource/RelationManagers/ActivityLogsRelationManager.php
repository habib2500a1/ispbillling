<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\ResellerPortalActivityLog;
use App\Support\ResellerPortalActivityLabels;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'portalActivityLogs';

    protected static ?string $title = 'Portal activity';

    protected static ?string $icon = 'heroicon-o-clipboard-document-list';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->formatStateUsing(fn (string $state): string => ResellerPortalActivityLabels::label($state))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Actor')
                    ->placeholder('Owner')
                    ->formatStateUsing(fn (ResellerPortalActivityLog $record): string => $record->actorLabel()),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (ResellerPortalActivityLog $record): string => $record->subjectLabel() ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->fontFamily('mono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('meta')
                    ->label('Details')
                    ->formatStateUsing(function (?array $state): string {
                        if (! is_array($state) || $state === []) {
                            return '—';
                        }

                        return collect($state)
                            ->map(fn ($v, $k) => $k.': '.$v)
                            ->take(3)
                            ->implode(' · ');
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No portal activity yet')
            ->emptyStateDescription('Actions from the partner portal and mobile API appear here.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
