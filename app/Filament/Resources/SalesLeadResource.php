<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\SalesLeadResource\Pages;
use App\Models\SalesLead;
use App\Models\User;
use App\Services\Sales\SalesLeadConversionService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalesLeadResource extends Resource
{
    protected static ?string $model = SalesLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'New connections';

    protected static ?int $navigationSort = 6;

    protected static bool $shouldRegisterNavigation = true;

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->hasRole('super-admin') || $u->hasRole('isp-admin'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\Select::make('source')->options([
                'walk_in' => 'Walk-in',
                'phone' => 'Phone',
                'facebook' => 'Facebook',
                'referral' => 'Referral',
                'website' => 'Website',
                'whatsapp' => 'WhatsApp',
                'other' => 'Other',
            ])->required(),
            Forms\Components\Select::make('status')->options([
                SalesLead::STATUS_NEW => 'New',
                SalesLead::STATUS_CONTACTED => 'Contacted',
                SalesLead::STATUS_QUALIFIED => 'Qualified',
                SalesLead::STATUS_WON => 'Won',
                SalesLead::STATUS_LOST => 'Lost',
            ])->required(),
            Forms\Components\Select::make('area_id')->relationship('area', 'name')->searchable(),
            Forms\Components\Select::make('zone_id')->relationship('zone', 'name')->searchable(),
            Forms\Components\Select::make('assigned_to')->label('Assigned staff')
                ->options(fn () => User::query()->pluck('name', 'id')),
            Forms\Components\Select::make('package_id')->relationship('package', 'name')->searchable(),
            Forms\Components\TextInput::make('estimated_mrr')->numeric()->label('Est. MRR (BDT)'),
            Forms\Components\DateTimePicker::make('next_follow_up_at'),
            Forms\Components\Textarea::make('address')->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('source')->toggleable(),
                Tables\Columns\TextColumn::make('assignee.name')->label('Assigned')->toggleable(),
                Tables\Columns\TextColumn::make('next_follow_up_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    SalesLead::STATUS_NEW => 'New',
                    SalesLead::STATUS_CONTACTED => 'Contacted',
                    SalesLead::STATUS_QUALIFIED => 'Qualified',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('convert')
                    ->label('Convert to customer')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SalesLead $record): bool => $record->converted_customer_id === null
                        && $record->status !== SalesLead::STATUS_LOST)
                    ->action(function (SalesLead $record) {
                        $customer = app(SalesLeadConversionService::class)->convert($record);

                        return redirect(CustomerResource::getUrl('view', ['record' => $customer]));
                    }),
                Tables\Actions\Action::make('view_customer')
                    ->label('View customer')
                    ->icon('heroicon-o-eye')
                    ->url(fn (SalesLead $record): string => CustomerResource::getUrl('view', ['record' => $record->converted_customer_id]))
                    ->visible(fn (SalesLead $record): bool => $record->converted_customer_id !== null),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesLeads::route('/'),
            'create' => Pages\CreateSalesLead::route('/create'),
            'edit' => Pages\EditSalesLead::route('/{record}/edit'),
        ];
    }
}
