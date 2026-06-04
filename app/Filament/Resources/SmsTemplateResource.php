<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsTemplateResource\Pages;
use App\Models\SmsTemplate;
use App\Models\VoiceTemplate;
use App\Services\Sms\SmsTemplateService;
use App\Support\TenantResolver;
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
                            ->label('SMS reminder')
                            ->helperText('চালু = শুধু SMS। বন্ধ = SMS যাবে না, নিচের ভয়েস কল (যদি সেট করা থাকে)।')
                            ->default(true)
                            ->live(),
                        Forms\Components\Toggle::make('voice_enabled')
                            ->label('Voice when SMS is off')
                            ->helperText('SMS বন্ধ থাকলে এই টেমপ্লেটের ভয়েস কল যাবে। SMS চালু থাকলে ভয়েস যাবে না।')
                            ->default(true)
                            ->live(),
                        Forms\Components\Select::make('voice_template_id')
                            ->label('Voice template (SMS off হলে)')
                            ->options(fn (): array => VoiceTemplate::query()
                                ->where('tenant_id', TenantResolver::requiredTenantId())
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => ! (bool) $get('is_enabled') || (bool) $get('voice_enabled'))
                            ->required(fn (Forms\Get $get): bool => ! (bool) $get('is_enabled') && (bool) $get('voice_enabled'))
                            ->helperText('কলে যে বার্তা বলা হবে (transcript / audio)।'),
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
                    ->label('SMS')
                    ->onColor('success'),
                Tables\Columns\ToggleColumn::make('voice_enabled')
                    ->label('Voice if SMS off')
                    ->onColor('warning'),
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
