<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Filament\Resources\SupportTicketResource\RelationManagers;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketWorkspaceService;
use App\Support\SupportPanelAccess;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'ticket_number';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship(
                        'customer',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Customer $record): string => $record->name.' (#'.($record->customer_code ?? $record->id).')'
                    )
                    ->searchable(['name', 'customer_code', 'phone', 'mikrotik_secret_name', 'radius_username', 'email'])
                    ->preload()
                    ->required(),
                Forms\Components\Placeholder::make('live_service_status')
                    ->label('Live subscriber status')
                    ->content(fn (Get $get, SupportTicket $record): HtmlString => app(SupportTicketWorkspaceService::class)->liveStatusHtml(
                        $get('customer_id') ?? $record->customer_id
                    ))
                    ->columnSpanFull()
                    ->visibleOn('edit'),
                Forms\Components\Select::make('channel')
                    ->options(SupportTicket::CHANNELS)
                    ->required()
                    ->default('call_center'),
                Forms\Components\Select::make('department')
                    ->options(SupportTicket::DEPARTMENTS)
                    ->required(),
                Forms\Components\Select::make('priority')
                    ->options(SupportTicket::PRIORITIES)
                    ->required()
                    ->default('medium'),
                Forms\Components\Select::make('status')
                    ->options(SupportTicket::STATUSES)
                    ->required()
                    ->default('open'),
                Forms\Components\Select::make('issue_type')
                    ->options(SupportTicket::ISSUE_TYPES)
                    ->searchable()
                    ->nullable(),
                Forms\Components\TextInput::make('subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Select::make('assigned_to')
                    ->label('Assigned to')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\DateTimePicker::make('sla_resolve_due_at')
                    ->label('SLA resolve due'),
                Forms\Components\DateTimePicker::make('resolved_at'),
                Forms\Components\DateTimePicker::make('closed_at'),
                Forms\Components\Section::make('RADIUS / network snapshot')
                    ->description('Subscriber line, PPP, and OLT tools live under Billing → Subscribers and Network.')
                    ->schema([
                        Forms\Components\Placeholder::make('subscriber_net')
                            ->label('')
                            ->content(function (Get $get, SupportTicket $record): HtmlString {
                                $customerId = $get('customer_id') ?? $record->customer_id;
                                $c = $customerId
                                    ? Customer::query()
                                        ->with(['area', 'onuDevice', 'lastEndedPppSession'])
                                        ->find($customerId)
                                    : null;

                                if ($c === null) {
                                    return new HtmlString('<span class="text-gray-500">No subscriber linked.</span>');
                                }

                                $pppOnline = $c->isPppOnline();
                                $onu = $c->primaryOnu();
                                $onuOper = strtolower((string) ($onu?->onu_oper_status ?? ''));
                                $onuOnline = $onu === null
                                    ? null
                                    : in_array($onuOper, ['online', 'active', 'up', 'working'], true);
                                $lastLogout = $c->lastEndedPppSession?->ended_at ?? $c->ppp_last_seen_at;
                                $radius = filled($c->radius_username) ? $c->radius_username : '(defaults to subscriber code)';
                                $lines = [
                                    '<strong>PPP</strong>: <span style="color:'.($pppOnline ? '#16a34a' : '#dc2626').';font-weight:700;">'.($pppOnline ? 'Online' : 'Offline').'</span>',
                                    '<strong>ONU</strong>: '.($onuOnline === null ? 'Not mapped' : ($onuOnline ? '<span style="color:#16a34a;font-weight:700;">Online</span>' : '<span style="color:#dc2626;font-weight:700;">Offline</span>')),
                                    '<strong>Code</strong>: '.e($c->customer_code),
                                    '<strong>RADIUS user</strong>: '.e((string) $radius),
                                    '<strong>Access</strong>: '.e((string) $c->network_access_state),
                                    '<strong>Area</strong>: '.e((string) ($c->area?->name ?? '—')),
                                ];

                                if (! $pppOnline && $lastLogout) {
                                    $lines[] = '<strong>Last logout</strong>: '.e($lastLogout->format('d M Y, h:i A')).' ('.e($lastLogout->diffForHumans()).')';
                                }

                                if ($onu !== null && ! $onuOnline && filled($onu->offline_reason)) {
                                    $lines[] = '<strong>ONU reason</strong>: '.e((string) $onu->offline_reason);
                                }

                                return new HtmlString('<div class="prose prose-sm dark:prose-invert">'.implode('<br>', $lines).'</div>');
                            }),
                    ])
                    ->collapsed()
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department')
                    ->formatStateUsing(fn (?string $state): string => SupportTicket::DEPARTMENTS[$state] ?? (string) $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('priority')
                    ->formatStateUsing(fn (?string $state): string => SupportTicket::PRIORITIES[$state] ?? (string) $state)
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'gray',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn (?string $state): string => SupportTicket::STATUSES[$state] ?? (string) $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sla_resolve_due_at')
                    ->label('SLA')
                    ->formatStateUsing(fn (SupportTicket $record): string => $record->slaRemainingLabel())
                    ->description(fn (SupportTicket $record): ?string => $record->sla_resolve_due_at?->format('M j, H:i'))
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('sla_resolve_due_at', $direction))
                    ->color(fn (SupportTicket $record): ?string => $record->isSlaBreached() ? 'danger' : null),
                Tables\Columns\TextColumn::make('channel')
                    ->formatStateUsing(fn (SupportTicket $record): string => $record->channelLabel())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SupportTicket::STATUSES),
                Tables\Filters\SelectFilter::make('department')
                    ->options(SupportTicket::DEPARTMENTS),
                Tables\Filters\SelectFilter::make('priority')
                    ->options(SupportTicket::PRIORITIES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assignSelected')
                        ->label('Assign to…')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('assigned_to')
                                ->label('Staff user')
                                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->update(['assigned_to' => (int) $data['assigned_to']]);
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => SupportPanelAccess::assignTickets(auth()->user())),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MessagesRelationManager::class,
            RelationManagers\FieldVisitsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'create' => Pages\CreateSupportTicket::route('/create'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    public static function canCreate(): bool
    {
        return SupportPanelAccess::manageTickets(auth()->user());
    }

    public static function canView(Model $record): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return SupportPanelAccess::manageTickets(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return SupportPanelAccess::manageTickets(auth()->user());
    }
}
