<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksHrAccess;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Services\Hr\EmployeeLeaveRequestService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class HrLeaveManagementPage extends Page implements HasTable
{
    use ChecksHrAccess;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-sun';

    protected static string $view = 'filament.pages.hr-leave-management';

    protected static ?string $navigationLabel = 'Leave Management';

    protected static ?string $title = 'Leave Management';

    protected static ?string $slug = 'hr-leave-management';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    protected function getHeaderActions(): array
    {
        if (! static::canManagePayroll()) {
            return [];
        }

        return [
            $this->newLeaveRequestAction(),
        ];
    }

    public static function newLeaveRequestAction(): Action
    {
        return Action::make('newLeaveRequest')
            ->label('New Leave Request')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalHeading('Apply for Leave')
            ->modalWidth('lg')
            ->form(static::leaveRequestFormSchema())
            ->action(function (array $data): void {
                $request = app(EmployeeLeaveRequestService::class)->create($data, auth()->id());
                Notification::make()
                    ->title('Leave request submitted')
                    ->body($request->employee->name.' — '.$request->leaveTypeLabel())
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function leaveRequestFormSchema(): array
    {
        $types = (array) config('hr.leave_types', []);

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
            Forms\Components\Select::make('leave_type')
                ->label('Leave Type')
                ->options($types)
                ->required()
                ->native(false),
            Forms\Components\DatePicker::make('start_date')
                ->label('Start Date')
                ->required()
                ->default(now())
                ->native(false),
            Forms\Components\DatePicker::make('end_date')
                ->label('End Date')
                ->required()
                ->default(now())
                ->native(false)
                ->afterOrEqual('start_date'),
            Forms\Components\Textarea::make('reason')
                ->label('Reason / Note')
                ->placeholder('Explain the reason for taking leave...')
                ->rows(4)
                ->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(EmployeeLeaveRequest::query()->with(['employee'])->orderByDesc('start_date'))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')->label('Employee')->searchable()->weight('semibold'),
                Tables\Columns\TextColumn::make('leave_type')->label('Leave type')->formatStateUsing(
                    fn (EmployeeLeaveRequest $record): string => $record->leaveTypeLabel()
                ),
                Tables\Columns\TextColumn::make('start_date')->date('d M Y')->label('Start'),
                Tables\Columns\TextColumn::make('end_date')->date('d M Y')->label('End'),
                Tables\Columns\TextColumn::make('reason')->limit(35)->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (EmployeeLeaveRequest $record): string => $record->statusLabel())
                    ->color(fn (EmployeeLeaveRequest $record): string => match ($record->status) {
                        EmployeeLeaveRequest::STATUS_PENDING => 'warning',
                        EmployeeLeaveRequest::STATUS_REJECTED => 'danger',
                        default => 'success',
                    }),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No leave requests yet')
            ->emptyStateDescription('Use the «New Leave Request» button at the top right to apply for leave.')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (EmployeeLeaveRequest $record): bool => static::canManagePayroll()
                        && $record->status === EmployeeLeaveRequest::STATUS_PENDING)
                    ->action(function (EmployeeLeaveRequest $record): void {
                        app(EmployeeLeaveRequestService::class)->approve($record, auth()->id());
                        Notification::make()->title('Leave approved')->success()->send();
                    }),
            ]);
    }
}
