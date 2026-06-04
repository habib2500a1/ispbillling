<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VoiceSmsCampaignResource\Pages;
use App\Models\Package;
use App\Models\VoiceSmsCampaign;
use App\Models\VoiceTemplate;
use App\Services\CallCenter\VoiceSmsCampaignRunner;
use App\Services\CallCenter\VoiceSmsTargetResolver;
use App\Support\SupportPanelAccess;
use App\Support\TenantResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VoiceSmsCampaignResource extends Resource
{
    protected static ?string $model = VoiceSmsCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Voice SMS campaigns';

    protected static ?string $slug = 'voice-sms-campaigns';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    public static function form(Form $form): Form
    {
        $tenantId = TenantResolver::requiredTenantId();

        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Section::make('Delivery')
                ->description('SMS আর ভয়েস কল আলাদা চালু/বন্ধ — দুটোই চালু করলে দুটোই যাবে।')
                ->schema([
                    Forms\Components\Toggle::make('send_sms')
                        ->label('Send SMS (text)')
                        ->default(true)
                        ->live(),
                    Forms\Components\Toggle::make('send_voice')
                        ->label('Voice call (phone — speaks transcript / plays audio)')
                        ->default(false)
                        ->helperText('Requires VOICE_CALL_ENABLED=true and gateway in .env / Call center settings.'),
                ])
                ->columns(2),
            Forms\Components\Select::make('voice_template_id')
                ->label('Voice template')
                ->options(fn (): array => VoiceTemplate::query()->where('is_active', true)->pluck('name', 'id')->all())
                ->required()
                ->searchable(),
            Forms\Components\Select::make('target_filters.preset')
                ->label('Audience')
                ->options([
                    'all_active' => 'All active clients',
                    'due_clients' => 'Clients with due balance',
                    'expired' => 'Expired accounts',
                    'free' => 'Free / complimentary',
                ])
                ->default('all_active')
                ->required(),
            Forms\Components\Select::make('target_filters.package_ids')
                ->label('Packages (optional)')
                ->multiple()
                ->options(fn (): array => Package::query()
                    ->where('tenant_id', $tenantId)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable(),
            Forms\Components\Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'scheduled' => 'Scheduled',
                    'running' => 'Running',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                ])
                ->default('draft')
                ->required(),
            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Schedule for')
                ->helperText('Leave empty to run when processed by scheduler or “Run now”.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('send_sms')->label('SMS')->boolean(),
                Tables\Columns\IconColumn::make('send_voice')->label('Voice')->boolean(),
                Tables\Columns\TextColumn::make('voiceTemplate.name')->label('Template'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('targets_count')->label('Targets'),
                Tables\Columns\TextColumn::make('sent_count')->label('Delivered'),
                Tables\Columns\TextColumn::make('voice_sent_count')->label('Voice'),
                Tables\Columns\TextColumn::make('failed_count')->label('Failed'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview_targets')
                    ->label('Preview')
                    ->icon('heroicon-o-users')
                    ->action(function (VoiceSmsCampaign $record): void {
                        $count = app(VoiceSmsTargetResolver::class)->countTargets($record);
                        Notification::make()
                            ->title('Target audience')
                            ->body($count.' subscriber(s) with a phone number.')
                            ->info()
                            ->send();
                    }),
                Tables\Actions\Action::make('run_now')
                    ->label('Run now')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (VoiceSmsCampaign $record): string => collect([
                        $record->send_sms ? 'SMS: template text via SMS gateway' : null,
                        $record->send_voice ? 'Voice: outbound call per target' : null,
                    ])->filter()->implode(' · '))
                    ->visible(fn (VoiceSmsCampaign $record): bool => ! in_array($record->status, ['running'], true))
                    ->action(function (VoiceSmsCampaign $record): void {
                        $stats = app(VoiceSmsCampaignRunner::class)->run($record);
                        Notification::make()
                            ->title('Campaign finished')
                            ->body(sprintf(
                                'SMS %d · Voice %d · Failed %d · Targets %d',
                                $stats['sms_sent'],
                                $stats['voice_sent'],
                                $stats['failed'],
                                $stats['targets'],
                            ))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoiceSmsCampaigns::route('/'),
            'create' => Pages\CreateVoiceSmsCampaign::route('/create'),
            'edit' => Pages\EditVoiceSmsCampaign::route('/{record}/edit'),
        ];
    }
}
