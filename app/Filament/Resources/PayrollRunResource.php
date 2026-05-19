<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\PayrollRunResource\Pages;
use App\Models\PayrollRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollRunResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = PayrollRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'HR & Payroll';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('period_month')
                ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))]))
                ->required(),
            Forms\Components\TextInput::make('period_year')->numeric()->required()->default(now()->year),
            Forms\Components\TextInput::make('total_gross')->disabled(),
            Forms\Components\TextInput::make('total_net')->disabled(),
            Forms\Components\Select::make('status')
                ->options(['draft' => 'Draft', 'paid' => 'Paid'])
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_year')->sortable(),
                Tables\Columns\TextColumn::make('period_month')
                    ->formatStateUsing(fn ($state) => date('F', mktime(0, 0, 0, (int) $state, 1))),
                Tables\Columns\TextColumn::make('total_net')->money('BDT'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime(),
            ])
            ->defaultSort('period_year', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'view' => Pages\ViewPayrollRun::route('/{record}'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'payroll';
    }
}
