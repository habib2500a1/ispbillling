<?php

namespace App\Filament\Pages;

use App\Services\Radius\RadiusUserManagementService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RadiusUserAdmin extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static string $view = 'filament.pages.radius-user-admin';

    protected static ?string $navigationLabel = 'RADIUS users';

    protected static ?string $title = 'RADIUS user management';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 15;

    /** @var list<string> */
    public array $usernames = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && app(RadiusUserManagementService::class)->isAvailable();
    }

    public function mount(): void
    {
        $this->refreshUsernames();
    }

    protected function getHeaderActions(): array
    {
        $service = app(RadiusUserManagementService::class);

        return [
            Action::make('create_user')
                ->label('Add RADIUS user')
                ->icon('heroicon-o-plus')
                ->form([
                    TextInput::make('username')->required()->maxLength(64),
                    TextInput::make('password')->password()->required(),
                    TextInput::make('group')->label('Group name (optional)')->maxLength(64),
                ])
                ->action(function (array $data) use ($service): void {
                    $service->createUser($data['username'], $data['password'], $data['group'] ?? null);
                    $this->refreshUsernames();
                    Notification::make()->title('RADIUS user created')->success()->send();
                })
                ->visible(fn (): bool => $service->isAvailable()),
        ];
    }

    public function rejectUser(string $username): void
    {
        app(RadiusUserManagementService::class)->setReject($username, true);
        Notification::make()->title('Auth rejected for '.$username)->success()->send();
    }

    public function allowUser(string $username): void
    {
        app(RadiusUserManagementService::class)->setReject($username, false);
        Notification::make()->title('Auth allowed for '.$username)->success()->send();
    }

    public function deleteUser(string $username): void
    {
        app(RadiusUserManagementService::class)->deleteUser($username);
        $this->refreshUsernames();
        Notification::make()->title('User removed from RADIUS DB')->success()->send();
    }

    public function isRadiusAvailable(): bool
    {
        return app(RadiusUserManagementService::class)->isAvailable();
    }

    private function refreshUsernames(): void
    {
        $this->usernames = app(RadiusUserManagementService::class)->listUsernames(1000);
    }
}
