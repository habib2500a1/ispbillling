<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MfsSmsRecordResource\Pages;
use App\Filament\Support\AssignSubscriberPaymentAction;
use App\Filament\Support\TransferMfsPaymentAction;
use App\Models\MfsSmsRecord;
use App\Support\MfsSmsBillPaymentState;
use App\Services\Payments\MfsSmsIngestService;
use App\Services\Payments\MfsUnmatchedPaymentQueue;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MfsSmsRecordResource extends Resource
{
    protected static ?string $model = MfsSmsRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'MFS SMS ledger';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 6;

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return \App\Support\PaymentAdminAccess::canViewPaymentOps();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('RCL SMS ledger')
            ->description('ভুল subscriber: **Transfer** কলাম (Amount-এর পরে) — ক্লিক করলে সঠিক ID-তে সরান। Payments → All payments-তেও Transfer আছে।')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('gateway')->badge(),
                Tables\Columns\TextColumn::make('transaction_id')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\ViewColumn::make('transfer_col')
                    ->label('Transfer')
                    ->toggleable(false)
                    ->alignCenter()
                    ->view('filament.tables.columns.mfs-transfer'),
                Tables\Columns\TextColumn::make('bill_payment')
                    ->label('Bill payment')
                    ->toggleable(false)
                    ->badge()
                    ->getStateUsing(fn (MfsSmsRecord $r): string => MfsSmsBillPaymentState::label(
                        MfsSmsBillPaymentState::resolve($r),
                    ))
                    ->color(fn (MfsSmsRecord $r): string => MfsSmsBillPaymentState::color(
                        MfsSmsBillPaymentState::resolve($r),
                    ))
                    ->description(fn (MfsSmsRecord $r): ?string => match (MfsSmsBillPaymentState::resolve($r)) {
                        MfsSmsBillPaymentState::LINKED => $r->payment_id ? 'Payment #'.$r->payment_id : 'Bill updated',
                        MfsSmsBillPaymentState::PENDING_MATCH => ($r->meta['reference_match'] ?? '') === 'needs_assignment'
                            ? 'Assign subscriber ID in admin'
                            : 'SMS OK — bill not linked yet',
                        MfsSmsBillPaymentState::DUPLICATE_TRX => 'TrxID already on another payment',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('subscriber_id')
                    ->label('ID')
                    ->toggleable(false)
                    ->placeholder('—')
                    ->getStateUsing(fn (MfsSmsRecord $r): ?string => self::subscriberField($r, 'code'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchSubscriber($query, $search, 'customer_code')),
                Tables\Columns\TextColumn::make('subscriber_name')
                    ->label('Name')
                    ->toggleable(false)
                    ->placeholder('—')
                    ->getStateUsing(fn (MfsSmsRecord $r): ?string => self::subscriberField($r, 'name'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchSubscriber($query, $search, 'name')),
                Tables\Columns\TextColumn::make('subscriber_phone')
                    ->label('Phone')
                    ->toggleable(false)
                    ->placeholder('—')
                    ->getStateUsing(fn (MfsSmsRecord $r): ?string => self::subscriberField($r, 'phone')),
                Tables\Columns\TextColumn::make('subscriber_pppoe')
                    ->label('PPPoE')
                    ->toggleable(false)
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->getStateUsing(fn (MfsSmsRecord $r): ?string => self::subscriberField($r, 'pppoe'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchSubscriber($query, $search, 'pppoe')),
                Tables\Columns\TextColumn::make('device_name')
                    ->label('Device')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('meta.reference_token')
                    ->label('Ref scanned')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('meta.reference_match')
                    ->label('Auto match')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'auto_approved' => 'success',
                        'ambiguous_or_none', 'needs_assignment' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'auto_approved' => 'Auto approved',
                        'ambiguous_or_none' => 'No match',
                        'needs_assignment' => 'Pending · assign ID',
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('sender_phone')
                    ->label('From')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('SMS status')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        MfsSmsRecord::STATUS_APPROVED => 'SMS accepted',
                        MfsSmsRecord::STATUS_AWAITING => 'Awaiting SMS',
                        MfsSmsRecord::STATUS_USED => 'Consumed',
                        MfsSmsRecord::STATUS_REJECTED => 'Rejected',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        MfsSmsRecord::STATUS_APPROVED => 'info',
                        MfsSmsRecord::STATUS_AWAITING => 'warning',
                        MfsSmsRecord::STATUS_USED => 'gray',
                        MfsSmsRecord::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_id')
                    ->label('Payment')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    MfsSmsRecord::STATUS_AWAITING => 'Awaiting review',
                    MfsSmsRecord::STATUS_APPROVED => 'Approved',
                    MfsSmsRecord::STATUS_USED => 'Used',
                    MfsSmsRecord::STATUS_REJECTED => 'Rejected',
                ]),
                Tables\Filters\SelectFilter::make('gateway')->options([
                    'bkash' => 'bKash',
                    'nagad' => 'Nagad',
                    'rocket' => 'Rocket',
                ]),
                Tables\Filters\SelectFilter::make('bill_payment_state')
                    ->label('Bill payment')
                    ->options([
                        MfsSmsBillPaymentState::LINKED => 'Linked',
                        MfsSmsBillPaymentState::PENDING_MATCH => 'Pending match',
                        MfsSmsBillPaymentState::DUPLICATE_TRX => 'Duplicate Trx',
                        MfsSmsBillPaymentState::AWAITING_SMS => 'Awaiting SMS',
                        MfsSmsBillPaymentState::REJECTED => 'Rejected',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! filled($value)) {
                            return $query;
                        }

                        return match ($value) {
                            MfsSmsBillPaymentState::LINKED => $query->where(function (Builder $q): void {
                                $q->whereNotNull('payment_id')->orWhere('status', MfsSmsRecord::STATUS_USED);
                            }),
                            MfsSmsBillPaymentState::REJECTED => $query->where('status', MfsSmsRecord::STATUS_REJECTED),
                            MfsSmsBillPaymentState::AWAITING_SMS => $query->where('status', MfsSmsRecord::STATUS_AWAITING),
                            MfsSmsBillPaymentState::DUPLICATE_TRX => $query
                                ->whereNull('payment_id')
                                ->where('status', '!=', MfsSmsRecord::STATUS_USED)
                                ->where('meta->bill_payment_state', MfsSmsBillPaymentState::DUPLICATE_TRX),
                            MfsSmsBillPaymentState::PENDING_MATCH => $query
                                ->whereNull('payment_id')
                                ->whereIn('status', [MfsSmsRecord::STATUS_APPROVED, MfsSmsRecord::STATUS_AWAITING])
                                ->where(function (Builder $q): void {
                                    $q->where('meta->bill_payment_state', MfsSmsBillPaymentState::PENDING_MATCH)
                                        ->orWhereNull('meta->bill_payment_state');
                                }),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                TransferMfsPaymentAction::make('transferFromColumn'),
                Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('assignFromSms')
                    ->label('Assign subscriber ID')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->visible(fn (MfsSmsRecord $record): bool => ($record->meta['reference_match'] ?? '') === 'needs_assignment'
                        && $record->status === MfsSmsRecord::STATUS_APPROVED
                        && $record->payment_id === null)
                    ->modalHeading('Link SMS payment to subscriber')
                    ->modalDescription(function (MfsSmsRecord $record): string {
                        $pending = app(MfsUnmatchedPaymentQueue::class)->findPendingForSms($record);

                        return $pending !== null
                            ? AssignSubscriberPaymentAction::hintText($pending)
                            : 'Trx '.$record->transaction_id.' · '.number_format((float) $record->amount, 2).' BDT';
                    })
                    ->form(AssignSubscriberPaymentAction::formSchema())
                    ->action(function (MfsSmsRecord $record, array $data): void {
                        $queue = app(MfsUnmatchedPaymentQueue::class);
                        $pending = $queue->findPendingForSms($record) ?? $queue->queueFromSms($record, [
                            'customer' => null,
                            'customers' => [],
                            'token' => $record->meta['reference_token'] ?? null,
                            'matched_by' => null,
                            'candidates' => $record->meta['reference_tokens'] ?? [],
                        ]);

                        try {
                            app(\App\Services\Payments\GatewayPaymentVerificationService::class)->assignAndApprove(
                                $pending,
                                (int) $data['customer_id'],
                                isset($data['invoice_id']) ? (int) $data['invoice_id'] : null,
                                auth()->id(),
                            );
                            Notification::make()->title('Payment applied to subscriber')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Could not assign')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (MfsSmsRecord $r): bool => $r->status === MfsSmsRecord::STATUS_AWAITING)
                    ->requiresConfirmation()
                    ->action(function (MfsSmsRecord $record): void {
                        try {
                            app(MfsSmsIngestService::class)->approve($record);
                            Notification::make()->title('SMS approved for matching')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (MfsSmsRecord $r): bool => in_array($r->status, [MfsSmsRecord::STATUS_AWAITING, MfsSmsRecord::STATUS_APPROVED], true))
                    ->requiresConfirmation()
                    ->action(function (MfsSmsRecord $record): void {
                        app(MfsSmsIngestService::class)->reject($record, 'manual');
                        Notification::make()->title('SMS rejected')->warning()->send();
                    }),
                ])
                    ->label('More')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->button()
                    ->outlined(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMfsSmsRecords::route('/'),
        ];
    }

    public static function subscriberField(MfsSmsRecord $record, string $field): ?string
    {
        if (! self::showsSubscriber($record)) {
            return null;
        }

        $customer = $record->resolveMatchedCustomer();
        $meta = $record->meta ?? [];

        return match ($field) {
            'code' => $customer?->customer_code ?? $meta['matched_customer_code'] ?? null,
            'name' => $customer?->name ?? $meta['matched_customer_name'] ?? null,
            'phone' => $customer?->phone ?? $meta['matched_customer_phone'] ?? null,
            'pppoe' => $customer?->pppLoginName() ?? $meta['matched_customer_pppoe'] ?? null,
            default => null,
        };
    }

    public static function transferColumnLabel(MfsSmsRecord $record): ?string
    {
        if ($record->payment_id === null) {
            return null;
        }

        if (! in_array($record->status, [MfsSmsRecord::STATUS_USED, MfsSmsRecord::STATUS_APPROVED], true)) {
            return null;
        }

        return 'Transfer';
    }

    public static function transferColumnTooltip(MfsSmsRecord $record): ?string
    {
        if (self::transferColumnLabel($record) === null) {
            return 'No linked payment to transfer';
        }

        $payment = \App\Models\Payment::query()->withoutGlobalScopes()->find($record->payment_id);
        if ($payment === null) {
            return 'Payment not found';
        }

        return app(\App\Services\Payments\MfsPaymentTransferService::class)->canTransfer($payment)
            ?? 'Click to move payment to correct subscriber ID';
    }

    public static function showsSubscriber(MfsSmsRecord $record): bool
    {
        if (in_array($record->status, [MfsSmsRecord::STATUS_USED, MfsSmsRecord::STATUS_APPROVED], true)) {
            return ($record->meta['reference_match'] ?? '') === 'auto_approved'
                || $record->payment_id !== null
                || filled($record->meta['matched_customer_id'] ?? null);
        }

        return false;
    }

    public static function searchSubscriber(Builder $query, string $search, string $column): Builder
    {
        $like = '%'.$search.'%';

        return $query->where(function (Builder $q) use ($like, $column): void {
            $q->whereHas('payment.customer', function (Builder $cq) use ($like, $column): void {
                match ($column) {
                    'customer_code' => $cq->where('customer_code', 'like', $like),
                    'name' => $cq->where('name', 'like', $like),
                    'pppoe' => $cq->where(function (Builder $pq) use ($like): void {
                        $pq->where('mikrotik_secret_name', 'like', $like)
                            ->orWhere('radius_username', 'like', $like);
                    }),
                    default => null,
                };
            })->orWhere(function (Builder $mq) use ($like, $column): void {
                match ($column) {
                    'customer_code' => $mq->where('meta->matched_customer_code', 'like', $like),
                    'name' => $mq->where('meta->matched_customer_name', 'like', $like),
                    'pppoe' => $mq->where('meta->matched_customer_pppoe', 'like', $like),
                    default => null,
                };
            });
        });
    }
}
