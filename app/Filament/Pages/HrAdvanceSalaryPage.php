<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksHrAccess;
use App\Models\Employee;
use App\Models\EmployeeAdvanceSalaryRequest;
use App\Services\Hr\EmployeeAdvanceSalaryService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class HrAdvanceSalaryPage extends Page implements HasTable
{
    use ChecksHrAccess;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.hr-advance-salary';

    protected static ?string $navigationLabel = 'Advance Salary';

    protected static ?string $title = 'Advance Salary';

    protected static ?string $slug = 'hr-advance-salary';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    /**
     * @return array{total_advance: float, pending_count: int, active_employees: int}
     */
    public function getAdvanceStats(): array
    {
        return [
            'total_advance' => round((float) Employee::query()->sum('wallet_balance'), 2),
            'pending_count' => EmployeeAdvanceSalaryRequest::query()
                ->where('status', EmployeeAdvanceSalaryRequest::STATUS_APPROVED)
                ->count(),
            'active_employees' => Employee::query()->where('is_active', true)->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        if (! static::canManagePayroll()) {
            return [];
        }

        return [
            Action::make('advanceSalaryRequest')
                ->label('Advance Salary Request')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Advance Salary Request')
                ->modalWidth('lg')
                ->form(static::advanceRequestFormSchema())
                ->action(function (array $data): void {
                    $request = app(EmployeeAdvanceSalaryService::class)->createRequest(
                        $data,
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Advance salary request saved')
                        ->body($request->employee->name.' — '.number_format((float) $request->amount, 2).' BDT')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function advanceRequestFormSchema(): array
    {
        return [
            Forms\Components\Select::make('employee_id')
                ->label('Employee')
                ->options(fn (): array => Employee::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (Employee $e): array => [
                        $e->id => trim($e->name.' ('.($e->employee_code ?: 'EMP-'.$e->id).')'),
                    ])
                    ->all())
                ->searchable()
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('amount')
                ->label('Advance Amount (৳)')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->default(0)
                ->prefix('৳'),
            Forms\Components\DatePicker::make('request_date')
                ->label('Request Date')
                ->required()
                ->default(now())
                ->native(false),
            Forms\Components\Textarea::make('purpose')
                ->label('Purpose')
                ->placeholder('Why is the advance requested?')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Section::make('Return Policy')
                ->schema([
                    Forms\Components\Select::make('return_type')
                        ->label('Return Type')
                        ->options([
                            EmployeeAdvanceSalaryRequest::RETURN_NEXT_SALARY => '1-Time Deduction (Next Salary)',
                            EmployeeAdvanceSalaryRequest::RETURN_INSTALLMENT => 'Installment (Multiple Months)',
                            EmployeeAdvanceSalaryRequest::RETURN_MANUAL => 'Manual Recovery',
                        ])
                        ->default(EmployeeAdvanceSalaryRequest::RETURN_NEXT_SALARY)
                        ->required()
                        ->native(false)
                        ->live(),
                    Forms\Components\DatePicker::make('deduction_month')
                        ->label('Deduction Month')
                        ->required()
                        ->default(now()->startOfMonth())
                        ->displayFormat('F Y')
                        ->format('Y-m-d')
                        ->native(false)
                        ->helperText('Salary month when advance will be deducted (for next-salary policy).'),
                ])
                ->columns(2),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EmployeeAdvanceSalaryRequest::query()
                    ->with(['employee', 'creator'])
                    ->orderByDesc('request_date')
                    ->orderByDesc('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_date')->label('Request date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Employee')
                    ->description(fn (EmployeeAdvanceSalaryRequest $record): string => $record->employee?->employee_code ?? '')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('Amount')->money('BDT')->sortable(),
                Tables\Columns\TextColumn::make('purpose')->limit(40)->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('return_type')
                    ->label('Return policy')
                    ->formatStateUsing(fn (EmployeeAdvanceSalaryRequest $record): string => $record->returnTypeLabel()),
                Tables\Columns\TextColumn::make('deduction_month')
                    ->label('Deduction month')
                    ->formatStateUsing(fn (EmployeeAdvanceSalaryRequest $record): string => $record->deductionMonthLabel()),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (EmployeeAdvanceSalaryRequest $record): string => $record->statusLabel())
                    ->color(fn (EmployeeAdvanceSalaryRequest $record): string => match ($record->status) {
                        EmployeeAdvanceSalaryRequest::STATUS_DEDUCTED => 'success',
                        EmployeeAdvanceSalaryRequest::STATUS_RECOVERED => 'gray',
                        EmployeeAdvanceSalaryRequest::STATUS_CANCELLED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('request_date', 'desc')
            ->actions([
                Tables\Actions\Action::make('recover')
                    ->label('Recover')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (EmployeeAdvanceSalaryRequest $record): bool => static::canManagePayroll()
                        && $record->status === EmployeeAdvanceSalaryRequest::STATUS_APPROVED)
                    ->requiresConfirmation()
                    ->action(function (EmployeeAdvanceSalaryRequest $record): void {
                        app(EmployeeAdvanceSalaryService::class)->recover($record, (float) $record->amount);
                        Notification::make()->title('Advance recovered')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No advance requests yet')
            ->emptyStateDescription('Use the «Advance Salary Request» button at the top right to add one.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
