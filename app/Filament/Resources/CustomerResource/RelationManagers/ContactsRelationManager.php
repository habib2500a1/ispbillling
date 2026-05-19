<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\CustomerContact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contact numbers';

    protected static ?string $icon = 'heroicon-o-phone';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('label')
                    ->options([
                        'mobile' => 'Mobile',
                        'home' => 'Home',
                        'office' => 'Office',
                        'whatsapp' => 'WhatsApp',
                        'emergency' => 'Emergency',
                        'other' => 'Other',
                    ])
                    ->required()
                    ->default('mobile'),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->maxLength(32)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? CustomerContact::normalizePhone($state) : null),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary number')
                    ->helperText('Syncs to the main phone field on the subscriber profile.'),
                Forms\Components\Toggle::make('is_whatsapp')
                    ->label('WhatsApp'),
                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('phone')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('phone')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),
                Tables\Columns\IconColumn::make('is_whatsapp')
                    ->boolean()
                    ->label('WA'),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('is_primary', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn () => $this->getOwnerRecord()->syncPrimaryPhoneFromContacts()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn () => $this->getOwnerRecord()->syncPrimaryPhoneFromContacts()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->getOwnerRecord()->syncPrimaryPhoneFromContacts()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
