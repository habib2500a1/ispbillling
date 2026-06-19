<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Filament\Resources\SupportTicketResource\RelationManagers;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Billing\BillCollectionSearchService;
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

    public static function customerSelectField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('customer_id')
            ->label('Customer')
            ->searchable()
            ->searchingMessage('Searching subscribers…')
            ->noSearchResultsMessage('No subscriber found — try name, code, or phone.')
            ->searchDebounce(350)
            ->getSearchResultsUsing(fn (string $search): array => static::customerSearchOptions($search))
            ->getOptionLabelUsing(function ($value): ?string {
                if (! filled($value)) {
                    return null;
                }

                $customer = Customer::query()->find($value);
                if ($customer === null) {
                    return null;
                }

                return $customer->name.' (#'.($customer->customer_code ?? $customer->id).')';
            })
            ->required()
            ->helperText('Type at least 2 characters — name, customer code, phone, or PPP username.');
    }

    /**
     * @return array<int|string, string>
     */
    public static function customerSearchOptions(string $search): array
    {
        return app(BillCollectionSearchService::class)
            ->search($search, 50)
            ->mapWithKeys(fn (array $row): array => [
                $row['id'] => $row['customer_code'].' — '.$row['name']
                    .($row['phone'] ? ' · '.$row['phone'] : ''),
            ])
            ->all();
    }

    public static function customerIdField(bool $useHiddenPicker = false): Forms\Components\Component
    {
        if ($useHiddenPicker) {
            return Forms\Components\Hidden::make('customer_id')
                ->required()
                ->dehydrated();
        }

        return static::customerSelectField();
    }

    public static function assigneeSelectField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('assigned_to')
            ->label('Assigned technician')
            ->options(function (Get $get, ?SupportTicket $record = null): array {
                $current = $get('assigned_to') ?? $record?->assigned_to;

                return SupportPanelAccess::assignableStaffOptions(
                    filled($current) ? (int) $current : null,
                );
            })
            ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) $state : null)
            ->nullable()
            ->placeholder('Unassigned')
            ->native(false)
            ->helperText('Pick technician — or use Assign staff in the page header on edit.');
    }

    public static function form(Form $form, bool $useSubscriberSearchPicker = false): Form
    {
        $schema = [];

        if ($useSubscriberSearchPicker) {
            $schema[] = static::customerIdField(true);
        } else {
            $schema[] = Forms\Components\Section::make('Subscriber')
                ->schema([
                    static::customerIdField(false)->columnSpanFull(),
                    Forms\Components\Placeholder::make('live_service_status')
                        ->label('Live subscriber status')
                        ->content(function (Get $get, ?SupportTicket $record = null): HtmlString {
                            $customerId = $get('customer_id') ?? $record?->customer_id;

                            return app(SupportTicketWorkspaceService::class)->liveStatusHtml($customerId);
                        })
                        ->columnSpanFull(),
                ])
                ->columns(1);
        }

        $schema[] = Forms\Components\Section::make('Assignment & routing')
            ->description('Who owns this ticket and which team handles it.')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    static::assigneeSelectField(),
                    Forms\Components\Select::make('department')
                        ->options(SupportTicket::DEPARTMENTS)
                        ->required()
                        ->native(false),
                ]),
            ]);

        $schema[] = Forms\Components\Section::make('Ticket details')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('channel')
                        ->options(SupportTicket::CHANNELS)
                        ->required()
                        ->default('call_center')
                        ->native(false),
                    Forms\Components\Select::make('priority')
                        ->options(SupportTicket::PRIORITIES)
                        ->required()
                        ->default('medium')
                        ->live()
                        ->native(false),
                    Forms\Components\Select::make('status')
                        ->options(SupportTicket::STATUSES)
                        ->required()
                        ->default('open')
                        ->native(false),
                    Forms\Components\Select::make('issue_type')
                        ->label('Issue category')
                        ->options(SupportTicket::ISSUE_TYPES)
                        ->searchable()
                        ->nullable()
                        ->native(false),
                ]),
                Forms\Components\TextInput::make('subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->label('Problem details')
                    ->required()
                    ->rows(5)
                    ->placeholder('What did the customer report? Steps tried, error lights, outage area, etc.')
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('sla_preview')
                    ->label('SLA resolve target')
                    ->content(function (Get $get): HtmlString {
                        $priority = (string) ($get('priority') ?: 'medium');
                        $hours = (int) (config('support.sla_resolve_hours.'.$priority) ?? 48);
                        $due = now()->addHours($hours)->format('M j, Y · g:i A');
                        $label = SupportTicket::PRIORITIES[$priority] ?? $priority;

                        return new HtmlString(
                            '<span class="sp-sla-preview"><strong>'.$due.'</strong>'
                            .' <span class="text-gray-500">('.$hours.' hours · '.$label.' priority)</span></span>'
                        );
                    })
                    ->visibleOn('create'),
                Forms\Components\DateTimePicker::make('sla_resolve_due_at')
                    ->label('SLA resolve due')
                    ->helperText('Auto-set on save from priority if left empty.')
                    ->visibleOn('edit'),
                Forms\Components\DateTimePicker::make('first_response_due_at')
                    ->label('First response due')
                    ->visibleOn('edit'),
                Forms\Components\DateTimePicker::make('eta_at')
                    ->label('Customer ETA')
                    ->helperText('Shown on customer portal and mobile app.')
                    ->visibleOn('edit'),
                Forms\Components\TextInput::make('sla_profile')
                    ->label('SLA profile')
                    ->disabled()
                    ->visibleOn('edit'),
                Forms\Components\DateTimePicker::make('resolved_at')
                    ->visibleOn('edit'),
                Forms\Components\DateTimePicker::make('closed_at')
                    ->visibleOn('edit'),
            ])
            ->columns(1);

        $schema[] = Forms\Components\Section::make('RADIUS / network snapshot')
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
            ->visibleOn('edit');

        return $form->schema($schema);
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
                Tables\Columns\TextColumn::make('eta_at')
                    ->label('ETA')
                    ->dateTime('M j, H:i')
                    ->placeholder('—')
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Assignee')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('channel')
                    ->options(SupportTicket::CHANNELS),
                Tables\Filters\SelectFilter::make('issue_type')
                    ->label('Issue category')
                    ->options(SupportTicket::ISSUE_TYPES),
            ])
            ->recordUrl(fn (SupportTicket $record): string => static::getUrl('edit', ['record' => $record]))
            ->actions([
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
                                ->options(fn (): array => SupportPanelAccess::assignableStaffOptions())
                                ->required()
                                ->native(false),
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
