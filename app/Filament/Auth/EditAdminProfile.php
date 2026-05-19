<?php

namespace App\Filament\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile;
use Illuminate\Validation\Rules\Password;

class EditAdminProfile extends EditProfile
{
    public static function getLabel(): string
    {
        return 'My account';
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('Profile')
                            ->description('Your display name and login email.')
                            ->schema([
                                $this->getNameFormComponent(),
                                $this->getEmailFormComponent(),
                            ])
                            ->columns(1),
                        Section::make('Change password')
                            ->description('Leave new password blank to keep the current one. You must enter your current password when setting a new password.')
                            ->schema([
                                TextInput::make('current_password')
                                    ->label('Current password')
                                    ->password()
                                    ->revealable(filament()->arePasswordsRevealable())
                                    ->autocomplete('current-password')
                                    ->dehydrated(false)
                                    ->required(fn (Get $get): bool => filled($get('password')))
                                    ->currentPassword(),
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ])
                            ->columns(1),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data')
                    ->inlineLabel(! static::isSimple()),
            ),
        ];
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('New password')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::default())
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Account updated';
    }
}
