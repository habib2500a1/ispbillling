<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 0;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identity')->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('organization_type')
                        ->label('Organization type')
                        ->options([
                            'single_isp' => 'Single ISP',
                            'multi_isp' => 'Multi ISP',
                            'multi_branch' => 'Multi Branch',
                            'franchise' => 'Franchise ISP',
                            'reseller_isp' => 'Reseller ISP',
                        ])
                        ->default('single_isp')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->required(),
                ])->columns(2),
                Forms\Components\Section::make('Contact & domain')->schema([
                    Forms\Components\TextInput::make('domain')
                        ->placeholder('bill.example.com')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('address')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('contact_phone')
                        ->tel()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('contact_email')
                        ->email()
                        ->maxLength(255),
                ])->columns(2),
                Forms\Components\Section::make('Branding')->schema([
                    Forms\Components\TextInput::make('branding.app_name')
                        ->label('App name')
                        ->maxLength(255),
                    Forms\Components\ColorPicker::make('branding.primary_color')
                        ->label('Primary color'),
                    Forms\Components\ColorPicker::make('branding.accent_color')
                        ->label('Accent color'),
                    Forms\Components\Select::make('branding.theme')
                        ->label('Theme')
                        ->options([
                            'default' => 'Default',
                            'dark' => 'Dark',
                            'light' => 'Light',
                        ])
                        ->default('default'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('organization_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'multi_isp' => 'Multi ISP',
                        'multi_branch' => 'Multi Branch',
                        'franchise' => 'Franchise',
                        'reseller_isp' => 'Reseller ISP',
                        default => 'Single ISP',
                    }),
                Tables\Columns\TextColumn::make('domain')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
