<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsTemplateResource\Pages;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsTemplateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmsTemplateResource extends Resource
{
    protected static ?string $model = SmsTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?string $navigationLabel = 'SMS templates';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return NotificationLogResource::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('key')
                            ->label('Template key')
                            ->required()
                            ->maxLength(64)
                            ->disabledOn('edit')
                            ->dehydrated(),
                        Forms\Components\TextInput::make('event_key')
                            ->label('Automation event')
                            ->helperText('Links to payment, due, OTP, client created, etc.')
                            ->maxLength(64),
                        Forms\Components\Select::make('template_type')
                            ->options(['default' => 'Default', 'custom' => 'Custom'])
                            ->default('default')
                            ->native(false),
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('SMS enabled')
                            ->helperText('When off, this automated SMS will not be sent.')
                            ->default(true),
                        Forms\Components\Textarea::make('body')
                            ->label('Message')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),
                        Forms\Components\TagsInput::make('placeholders')
                            ->label('Placeholders')
                            ->helperText('Use {PlaceholderName} in the message body.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('template_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'default' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('body')
                    ->label('Template')
                    ->limit(60)
                    ->tooltip(fn (SmsTemplate $record): string => $record->body),
                Tables\Columns\ToggleColumn::make('is_enabled')
                    ->label('On')
                    ->onColor('success'),
                Tables\Columns\TextColumn::make('event_key')
                    ->label('Event')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')->label('Enabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('seed_defaults')
                    ->label('Restore defaults')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $count = app(SmsTemplateService::class)->seedDefaults();
                        Notification::make()
                            ->title("Restored {$count} templates")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsTemplates::route('/'),
            'edit' => Pages\EditSmsTemplate::route('/{record}/edit'),
        ];
    }
}
