<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\AttendanceRecordResource\Pages;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceRecordResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = AttendanceRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'HR & Payroll';

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')
                ->options(fn () => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->native(false),
            Forms\Components\DatePicker::make('work_date')->required()->default(now()),
            Forms\Components\TimePicker::make('check_in'),
            Forms\Components\TimePicker::make('check_out'),
            Forms\Components\Select::make('status')
                ->options(['present' => 'Present', 'absent' => 'Absent', 'leave' => 'Leave', 'holiday' => 'Holiday'])
                ->default('present')
                ->native(false),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('work_date')->date()->sortable(),
            Tables\Columns\TextColumn::make('employee.name')->searchable(),
            Tables\Columns\TextColumn::make('check_in'),
            Tables\Columns\TextColumn::make('check_out'),
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->defaultSort('work_date', 'desc')
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceRecords::route('/'),
            'create' => Pages\CreateAttendanceRecord::route('/create'),
            'edit' => Pages\EditAttendanceRecord::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'payroll';
    }
}
