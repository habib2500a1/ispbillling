<?php

namespace App\Filament\Pages;

use App\Models\StaffSecuritySetting;
use App\Services\Staff\ActivityLogger;
use App\Support\TenantResolver;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageStaffSecurity extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static string $view = 'filament.pages.manage-staff-security';

    protected static ?string $navigationLabel = 'Staff security';

    protected static ?string $title = 'IP restrictions & 2FA policy';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $tenantId = TenantResolver::requiredTenantId();
        $settings = StaffSecuritySetting::withoutGlobalScopes()
            ->firstOrCreate(['tenant_id' => $tenantId], [
                'ip_restriction_enabled' => false,
                'allowed_ips' => [],
                'require_two_factor' => false,
            ]);

        $this->form->fill([
            'ip_restriction_enabled' => $settings->ip_restriction_enabled,
            'allowed_ips' => $settings->allowed_ips ?? [],
            'require_two_factor' => $settings->require_two_factor,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('IP restrictions')->schema([
                    Toggle::make('ip_restriction_enabled')
                        ->label('Restrict admin login by IP')
                        ->helperText('When enabled, staff must connect from an allowed IP (tenant list, or their own / branch rules).'),
                    TagsInput::make('allowed_ips')
                        ->label('Tenant allowed IPs')
                        ->placeholder('192.168.0.0/24'),
                ]),
                Section::make('Two-factor policy')->schema([
                    Toggle::make('require_two_factor')
                        ->label('Require 2FA for all staff (recommended)')
                        ->helperText('Staff without 2FA will be prompted to set it up on next login.'),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenantId = TenantResolver::requiredTenantId();
        $state = $this->form->getState();

        StaffSecuritySetting::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'ip_restriction_enabled' => (bool) ($state['ip_restriction_enabled'] ?? false),
                'allowed_ips' => $state['allowed_ips'] ?? [],
                'require_two_factor' => (bool) ($state['require_two_factor'] ?? false),
            ]
        );

        app(ActivityLogger::class)->log('security.updated', 'Staff security settings updated');

        Notification::make()->title('Security settings saved')->success()->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('security.manage'));
    }
}
