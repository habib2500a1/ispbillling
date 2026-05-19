<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class EmployeeResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Employees';

    protected static ?string $modelLabel = 'employee';

    protected static ?string $pluralModelLabel = 'employees';

    protected static ?string $navigationGroup = 'HRM';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Employee profile')
                ->schema([
                    Forms\Components\TextInput::make('employee_code')
                        ->label('Employee ID')
                        ->maxLength(32)
                        ->placeholder('EMP-001'),
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\TextInput::make('designation')->maxLength(120),
                    Forms\Components\TextInput::make('department')
                        ->maxLength(80)
                        ->datalist(array_values(static::departmentOptions())),
                    Forms\Components\DatePicker::make('join_date')->native(false),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                ])
                ->columns(2),
            Forms\Components\Section::make('Compensation')
                ->schema([
                    Forms\Components\TextInput::make('base_salary')
                        ->label('Monthly salary (BDT)')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->minValue(0),
                    Forms\Components\TextInput::make('wallet_balance')
                        ->label('Wallet balance (BDT)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Advance / petty cash balance for this employee.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active employee')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')
                    ->label('ID')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('designation')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('department')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('join_date')
                    ->label('Join date')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Salary')
                    ->money('BDT')
                    ->sortable(),
                Tables\Columns\TextColumn::make('wallet_balance')
                    ->label('Wallet')
                    ->money('BDT')
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->options(fn (): array => static::departmentFilterOptions()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->defaultSort('name')
            ->striped()
            ->emptyStateHeading('No employees found')
            ->emptyStateDescription('Add your first team member to start tracking salary and attendance.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function departmentOptions(): array
    {
        $defaults = [
            'Admin' => 'Admin',
            'Billing' => 'Billing',
            'Support' => 'Support',
            'NOC' => 'NOC',
            'Field' => 'Field',
            'Sales' => 'Sales',
            'Accounts' => 'Accounts',
        ];

        $fromDb = Employee::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department', 'department')
            ->all();

        return array_merge($defaults, $fromDb);
    }

    /**
     * @return array<string, string>
     */
    public static function departmentFilterOptions(): array
    {
        return ['' => 'All'] + static::departmentOptions();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'payroll';
    }
}
