<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\AutomaticProcessResource\Pages;
use App\Models\AutomaticProcess;
use App\Models\Branch;
use App\Services\Automation\AutomaticProcessRunCsvExporter;
use App\Services\Automation\AutomaticProcessScheduler;
use App\Support\IspTimezone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class AutomaticProcessResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = AutomaticProcess::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Automatic process';

    protected static ?string $modelLabel = 'Automatic process';

    protected static ?string $pluralModelLabel = 'Automatic processes';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Process')->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave empty on create to auto-generate.'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?string $operation): void {
                        if ($operation !== 'create') {
                            return;
                        }
                        $set('slug', Str::slug((string) $state));
                    }),
                Forms\Components\Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->native(false)
                    ->helperText('Optional label; most commands run for the whole tenant.'),
                Forms\Components\Toggle::make('enabled')
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Schedule')->schema([
                Forms\Components\Select::make('interval')
                    ->options(AutomaticProcess::INTERVALS)
                    ->required()
                    ->native(false)
                    ->live(),
                Forms\Components\TextInput::make('execute_at')
                    ->label('Execute at (HH:MM, '.IspTimezone::label().')')
                    ->placeholder('03:10')
                    ->maxLength(5)
                    ->helperText(IspTimezone::description().' — e.g. 11:00 = 11 AM Bangladesh.')
                    ->visible(fn (Forms\Get $get): bool => $get('interval') === 'daily'),
                Forms\Components\Select::make('artisan_command')
                    ->label('Artisan command')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => collect(Artisan::all())
                        ->keys()
                        ->filter(fn (string $name): bool => str_starts_with($name, 'isp:'))
                        ->sort()
                        ->mapWithKeys(fn (string $name): array => [$name => $name])
                        ->all())
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('Command name')
                            ->required()
                            ->placeholder('isp:my-command'),
                    ])
                    ->createOptionUsing(fn (array $data): string => (string) $data['name']),
                Forms\Components\KeyValue::make('command_options')
                    ->label('Command options')
                    ->keyLabel('Option')
                    ->valueLabel('Value')
                    ->addActionLabel('Add option')
                    ->helperText('Example: --cycle → daily'),
            ])->columns(2),

            Forms\Components\Section::make('Advanced')->schema([
                Forms\Components\TextInput::make('when_config_key')
                    ->label('Run only when config is true')
                    ->placeholder('network.auto_suspend_enabled')
                    ->maxLength(128),
                Forms\Components\TextInput::make('without_overlapping_minutes')
                    ->label('Prevent overlap (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(1440)
                    ->nullable(),
            ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('All branches')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('execute_at_display')
                    ->label('Execute at ('.IspTimezone::label().')')
                    ->state(fn (AutomaticProcess $record): string => $record->executeAtLabel()),
                Tables\Columns\TextColumn::make('next_run_at')
                    ->label('Next run ('.IspTimezone::label().')')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('interval')
                    ->label('Interval')
                    ->formatStateUsing(fn (string $state): string => AutomaticProcess::INTERVALS[$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('execution_day')
                    ->label('Execution day')
                    ->state(fn (AutomaticProcess $record): string => $record->executionDayLabel()),
                Tables\Columns\ToggleColumn::make('enabled')
                    ->afterStateUpdated(function (AutomaticProcess $record): void {
                        $record->forceFill([
                            'next_run_at' => app(AutomaticProcessScheduler::class)->computeNextRunAt($record),
                        ])->save();
                    }),
                Tables\Columns\TextColumn::make('last_status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        'skipped' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('enabled'),
            ])
            ->actions([
                Tables\Actions\Action::make('run')
                    ->label('Run')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Run process now?')
                    ->modalDescription(fn (AutomaticProcess $record): string => "Execute «{$record->name}» immediately.")
                    ->action(function (AutomaticProcess $record): void {
                        $ok = app(AutomaticProcessScheduler::class)->run($record, force: true, triggeredBy: 'manual');

                        Notification::make()
                            ->title($ok ? 'Process completed' : 'Process finished with issues')
                            ->body(mb_substr((string) $record->fresh()?->last_output, 0, 500))
                            ->color($ok ? 'success' : 'warning')
                            ->send();
                    }),
                Tables\Actions\Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-queue-list')
                    ->color('gray')
                    ->modalHeading(fn (AutomaticProcess $record): string => 'Run history — '.$record->name)
                    ->modalContent(fn (AutomaticProcess $record) => view('filament.automatic-process-runs', [
                        'runs' => $record->runs()->orderByDesc('id')->limit(25)->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('info')
                    ->label('Info')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading(fn (AutomaticProcess $record): string => $record->name)
                    ->modalContent(fn (AutomaticProcess $record) => view('filament.automatic-process-info', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('refresh_schedule')
                    ->label('Sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function (AutomaticProcess $record): void {
                        $scheduler = app(AutomaticProcessScheduler::class);
                        $record->forceFill([
                            'next_run_at' => $scheduler->computeNextRunAt($record),
                        ])->save();

                        Notification::make()
                            ->title('Next run updated')
                            ->body('Next: '.$record->fresh()?->next_run_at?->format('Y-m-d H:i'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AutomaticProcess $record): bool => ! $record->isBuiltIn()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('run_all_due')
                    ->label('Run due now')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        Artisan::call('isp:run-automatic-processes');
                        Notification::make()
                            ->title('Due processes executed')
                            ->body(trim(Artisan::output()))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reseed_defaults')
                    ->label('Restore defaults')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Re-imports built-in process definitions (does not delete custom rows).')
                    ->visible(fn (): bool => auth()->user()?->hasRole(['super-admin', 'isp-admin', 'admin']) ?? false)
                    ->action(function (): void {
                        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\AutomaticProcessSeeder', '--force' => true]);
                        Notification::make()->title('Default processes restored')->success()->send();
                    }),
                Tables\Actions\Action::make('export_run_history')
                    ->label('Export run history (CSV)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(fn () => app(AutomaticProcessRunCsvExporter::class)->download(null, 30)),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutomaticProcesses::route('/'),
            'create' => Pages\CreateAutomaticProcess::route('/create'),
            'edit' => Pages\EditAutomaticProcess::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::checkPermission('system.automations');
    }

    public static function canCreate(): bool
    {
        return static::checkPermission('system.automations');
    }

    public static function canEdit($record): bool
    {
        return static::checkPermission('system.automations');
    }

    public static function canDelete($record): bool
    {
        return static::checkPermission('system.automations')
            && $record instanceof AutomaticProcess
            && ! $record->isBuiltIn();
    }

    protected static function permissionPrefix(): string
    {
        return 'system.automations';
    }
}
