<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VoiceTemplateResource\Pages;
use App\Models\VoiceTemplate;
use App\Support\SupportPanelAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VoiceTemplateResource extends Resource
{
    protected static ?string $model = VoiceTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-speaker-wave';

    protected static ?string $navigationLabel = 'Voice templates';

    protected static ?string $slug = 'voice-templates';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Select::make('language')
                ->options(['bn' => 'Bengali', 'en' => 'English'])
                ->default('bn'),
            Forms\Components\Select::make('type')
                ->options(['announcement' => 'Announcement', 'ivr' => 'IVR', 'reminder' => 'Reminder'])
                ->default('announcement'),
            Forms\Components\Textarea::make('transcript')->rows(4)->columnSpanFull(),
            Forms\Components\TextInput::make('audio_url')->url()->label('Audio URL'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('language')->badge(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoiceTemplates::route('/'),
            'create' => Pages\CreateVoiceTemplate::route('/create'),
            'edit' => Pages\EditVoiceTemplate::route('/{record}/edit'),
        ];
    }
}
