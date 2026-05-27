<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\AttendanceRecordResource\Pages;
use App\Models\AttendanceOfficeLocation;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Attendance')
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->options(fn () => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->native(false),
                    Forms\Components\DatePicker::make('work_date')->required()->default(now()),
                    Forms\Components\Select::make('status')
                        ->options([
                            'present' => 'Present',
                            'absent' => 'Absent',
                            'leave' => 'Leave',
                            'holiday' => 'Holiday',
                        ])
                        ->default('present')
                        ->live()
                        ->native(false),
                    Forms\Components\Select::make('attendance_office_location_id')
                        ->label('Office location')
                        ->options(fn () => AttendanceOfficeLocation::query()
                            ->where('is_active', true)
                            ->orderByDesc('is_default')
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->default(fn () => AttendanceOfficeLocation::query()
                            ->where('is_active', true)
                            ->where('is_default', true)
                            ->value('id'))
                        ->searchable()
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('status') === 'present')
                        ->visible(fn (Get $get): bool => $get('status') === 'present')
                        ->helperText(fn (): string => 'No office? Add one under HRM → Office locations.'),
                    Forms\Components\TimePicker::make('check_in'),
                    Forms\Components\TimePicker::make('check_out'),
                    Forms\Components\Toggle::make('geofence_override')
                        ->label('HR override (skip GPS / IP check)')
                        ->visible(fn (): bool => static::userCanOverrideGeofence())
                        ->helperText('Only for on-site issues or manual correction.'),
                    Forms\Components\Textarea::make('notes')->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Hidden::make('latitude'),
            Forms\Components\Hidden::make('longitude'),
            Forms\Components\Hidden::make('accuracy_meters'),
            Forms\Components\Hidden::make('client_ip')
                ->default(fn (): ?string => request()->ip()),
            Forms\Components\View::make('filament.forms.attendance-geofence')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('status') === 'present'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('work_date')->date()->sortable(),
            Tables\Columns\TextColumn::make('employee.name')->searchable(),
            Tables\Columns\TextColumn::make('officeLocation.name')
                ->label('Office')
                ->placeholder('—')
                ->toggleable(),
            Tables\Columns\TextColumn::make('check_in'),
            Tables\Columns\TextColumn::make('check_out'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('distance_meters')
                ->label('Distance')
                ->formatStateUsing(fn (?int $state): string => $state !== null ? number_format($state).' m' : '—')
                ->color(fn (AttendanceRecord $record): string => match (true) {
                    $record->geofence_override => 'gray',
                    $record->location_verified => 'success',
                    $record->distance_meters !== null => 'danger',
                    default => 'gray',
                })
                ->toggleable(),
            Tables\Columns\TextColumn::make('client_ip')
                ->label('IP')
                ->fontFamily('mono')
                ->size('xs')
                ->toggleable(),
            Tables\Columns\IconColumn::make('location_verified')
                ->label('GPS OK')
                ->boolean()
                ->toggleable(),
        ])
            ->defaultSort('work_date', 'desc')
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

    public static function userCanOverrideGeofence(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('payroll.manage'));
    }
}
