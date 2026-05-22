<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffExpenseResource\Pages;
use App\Models\StaffExpense;
use App\Models\StaffExpenseCategory;
use App\Models\Vendor;
use App\Services\Expenses\StaffExpenseService;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffExpenseResource extends Resource
{
    protected static ?string $model = StaffExpense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationLabel = 'Staff expenses';

    protected static ?string $modelLabel = 'Expense';

    protected static ?string $pluralModelLabel = 'Staff expenses';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 9;

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return StaffExpenseService::userCanSubmit(auth()->user())
            || StaffExpenseService::userCanApprove(auth()->user());
    }

    public static function canCreate(): bool
    {
        return StaffExpenseService::userCanSubmit(auth()->user());
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Expense details')
                ->description('Staff submits · Admin approves. Vendor, office, or other costs.')
                ->schema([
                    Forms\Components\Select::make('expense_source')
                        ->label('Cost type')
                        ->options(config('staff_expenses.sources', []))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('category_id', null))
                        ->native(false)
                        ->default(StaffExpense::SOURCE_VENDOR),
                    Forms\Components\Select::make('vendor_id')
                        ->label('Vendor / supplier')
                        ->options(fn (): array => Vendor::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('expense_source') === StaffExpense::SOURCE_VENDOR)
                        ->visible(fn (Get $get): bool => $get('expense_source') === StaffExpense::SOURCE_VENDOR)
                        ->helperText('No vendor in list? Use + New vendor to add one here.')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Vendor name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
                            Forms\Components\TextInput::make('email')->email()->maxLength(255),
                            Forms\Components\Textarea::make('address')->rows(2),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $vendor = Vendor::query()->create([
                                'name' => $data['name'],
                                'phone' => $data['phone'] ?? null,
                                'email' => $data['email'] ?? null,
                                'address' => $data['address'] ?? null,
                                'is_active' => true,
                            ]);

                            return (int) $vendor->id;
                        }),
                    Forms\Components\Select::make('category_id')
                        ->label('Category')
                        ->options(function (Get $get): array {
                            app(StaffExpenseService::class)->ensureDefaultCategories(
                                TenantResolver::requiredTenantId(),
                            );
                            $source = $get('expense_source');
                            $query = StaffExpenseCategory::query()
                                ->where('is_active', true)
                                ->orderBy('sort_order');
                            if (filled($source)) {
                                $query->where(function ($q) use ($source): void {
                                    $q->where('expense_source', $source)->orWhereNull('expense_source');
                                });
                            }

                            return $query->pluck('name', 'id')->all();
                        })
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->helperText(fn (Get $get): ?string => filled($get('expense_source'))
                            ? null
                            : 'Select cost type first'),
                    Forms\Components\DatePicker::make('expense_date')
                        ->required()
                        ->default(now())
                        ->maxDate(now()),
                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix('BDT'),
                    Forms\Components\Select::make('payment_method')
                        ->options(config('staff_expenses.payment_methods', []))
                        ->default('cash')
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('proof_path')
                        ->label('Receipt / proof')
                        ->directory('staff-expenses')
                        ->visibility('private')
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/*', 'application/pdf'])
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): void {
                $user = auth()->user();
                if ($user && ! StaffExpenseService::userCanApprove($user)) {
                    $query->where('submitted_by', $user->id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('expense_number')->label('No.')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('expense_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('expense_source')
                    ->label('Type')
                    ->formatStateUsing(fn (StaffExpense $record): string => $record->sourceLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        StaffExpense::SOURCE_VENDOR => 'info',
                        StaffExpense::SOURCE_OFFICE => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('category.name')->label('Category'),
                Tables\Columns\TextColumn::make('vendor.name')->label('Vendor')->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')->money('BDT')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('payment_method')->badge(),
                Tables\Columns\TextColumn::make('submitter.name')->label('Submitted by'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        StaffExpense::STATUS_APPROVED => 'success',
                        StaffExpense::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('approver.name')->label('Approved by')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        StaffExpense::STATUS_PENDING => 'Pending approval',
                        StaffExpense::STATUS_APPROVED => 'Approved',
                        StaffExpense::STATUS_REJECTED => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('expense_source')
                    ->label('Type')
                    ->options(config('staff_expenses.sources', [])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (StaffExpense $record): bool => $record->status === StaffExpense::STATUS_PENDING
                        && StaffExpenseService::userCanApprove(auth()->user()))
                    ->action(function (StaffExpense $record): void {
                        app(StaffExpenseService::class)->approve($record);
                        Notification::make()->title('Expense approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (StaffExpense $record): bool => $record->status === StaffExpense::STATUS_PENDING
                        && StaffExpenseService::userCanApprove(auth()->user()))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (StaffExpense $record, array $data): void {
                        app(StaffExpenseService::class)->reject($record, $data['reason']);
                        Notification::make()->title('Expense rejected')->warning()->send();
                    }),
            ])
            ->emptyStateHeading('No expenses yet')
            ->emptyStateDescription('Staff can submit vendor, office, or other costs for admin approval.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffExpenses::route('/'),
            'create' => Pages\CreateStaffExpense::route('/create'),
            'view' => Pages\ViewStaffExpense::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'vendor', 'submitter', 'approver']);
    }
}
