<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Models\CallCenterSetting;
use App\Services\CallCenter\PortSipConnectionProfile;
use App\Services\CallCenter\WebSipConfigPresenter;
use App\Support\SupportPanelAccess;
use App\Support\TenantResolver;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
class ManageCallCenterSettings extends Page
{
    use HidesHubNavigation;
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-phone';

    protected static string $view = 'filament.pages.manage-call-center-settings';

    protected static ?string $navigationLabel = 'Call center SIP';

    protected static ?string $title = 'PortSIP / WebSIP — একই SIP লগইন';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $slug = 'manage-call-center-settings';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return SupportPanelAccess::viewTickets($user)
            && ($user?->hasAnyRole(['super-admin', 'isp-admin']) ?? false);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = CallCenterSetting::forTenant(TenantResolver::requiredTenantId());
        $meta = is_array($settings->meta) ? $settings->meta : [];
        $username = (string) ($meta['websip_username'] ?? $settings->default_extension ?? '');

        $this->form->fill([
            'websip_enabled' => $settings->websip_enabled,
            'sip_server' => $settings->sip_server,
            'sip_port' => (int) ($meta['sip_port'] ?? PortSipConnectionProfile::DEFAULT_SIP_PORT),
            'sip_domain' => $settings->sip_domain,
            'sip_username' => $username,
            'sip_password' => '',
            'outbound_caller_id' => $settings->outbound_caller_id ?: $username,
            'wss_uri' => $settings->wss_uri,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('লাইভ কল বাটন (অ্যাডমিন On / Off)')
                    ->description('বন্ধ করলে নিচের সবুজ ফোন, টপবার «কল», সাবস্ক্রাইবার লিস্টের কল আইকন — সব লুকাবে। SIP সেটিং সংরক্ষিত থাকে; আবার চালু করলেই ফিরে আসবে।')
                    ->schema([
                        Toggle::make('websip_enabled')
                            ->label('লাইভ কল চালু')
                            ->default(false)
                            ->inline(false)
                            ->dehydrated()
                            ->helperText('স্টাফ শুধু তখনই ডায়ালার ও কল বাটন দেখবে।'),
                    ]),
                Section::make('PortSIP — একই সেটিং ব্রাউজার ডায়ালারেও')
                    ->description('PortSIP অ্যাপে যেভাবে দেন (Server IP, Domain, Extension, Password) — ঠিক একই তথ্য এখানে। ব্রাউজার অটো WSS দিয়ে কানেক্ট করার চেষ্টা করে।')
                    ->schema([
                        TextInput::make('sip_server')
                            ->label('SIP Server (IP)')
                            ->placeholder('202.40.176.2')
                            ->required()
                            ->helperText('PortSIP → Server / Registrar IP'),
                        TextInput::make('sip_port')
                            ->label('SIP Port')
                            ->numeric()
                            ->default(PortSipConnectionProfile::DEFAULT_SIP_PORT)
                            ->helperText('PortSIP: UDP 5060 (অ্যাপে)। ব্রাউজার WSS ব্যবহার করে।'),
                        TextInput::make('sip_domain')
                            ->label('SIP Domain')
                            ->placeholder('sip17.bdwebs.com')
                            ->required()
                            ->helperText('PortSIP → Domain / Hostname'),
                        TextInput::make('sip_username')
                            ->label('Extension / Username')
                            ->placeholder('09617179160')
                            ->required(),
                        TextInput::make('sip_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('PortSIP-এর পাসওয়ার্ড। খালি রাখলে আগেরটা থাকবে।'),
                        TextInput::make('outbound_caller_id')
                            ->label('Caller ID')
                            ->helperText('আউটগোয়িং নম্বর (সাধারণত extension-এর মতো)'),
                        TextInput::make('wss_uri')
                            ->label('WSS URI (শুধু ব্রাউজার, ঐচ্ছিক)')
                            ->placeholder('খালি = অটো (sip domain থেকে)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Voice call gateway (SMS/voice alerts)')
                    ->description('Reminder-এ SMS বন্ধ থাকলে ভয়েস কল/লগ — সার্ভার .env থেকে চালু হয় (টেন্যান্ট ফর্ম নয়)।')
                    ->schema([
                        Placeholder::make('voice_gateway_status')
                            ->label('Status')
                            ->content(fn (): string => self::voiceGatewayStatusLabel())
                            ->helperText('Production: VOICE_CALL_ENABLED, VOICE_CALL_DRIVER, VOICE_CALL_WEBHOOK_URL (.env.example দেখুন)'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $settings = CallCenterSetting::forTenant(TenantResolver::requiredTenantId());
        $state = $this->form->getState();
        $meta = is_array($settings->meta) ? $settings->meta : [];

        $username = trim((string) ($state['sip_username'] ?? ''));
        $meta['websip_username'] = $username;
        $meta['sip_port'] = max(1, (int) ($state['sip_port'] ?? PortSipConnectionProfile::DEFAULT_SIP_PORT));

        if (filled($state['sip_password'] ?? null)) {
            $meta['websip_password'] = WebSipConfigPresenter::encryptPassword((string) $state['sip_password']);
        }

        $liveCallOn = filter_var($state['websip_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $settings->update([
            'websip_enabled' => $liveCallOn,
            'sip_server' => trim((string) ($state['sip_server'] ?? '')),
            'sip_domain' => trim((string) ($state['sip_domain'] ?? '')),
            'default_extension' => $username,
            'outbound_caller_id' => trim((string) ($state['outbound_caller_id'] ?? '')) ?: $username,
            'wss_uri' => trim((string) ($state['wss_uri'] ?? '')) ?: $settings->wss_uri,
            'meta' => $meta,
        ]);

        Notification::make()
            ->title('PortSIP / WebSIP settings saved')
            ->body($liveCallOn
                ? 'লাইভ কল বাটন চালু — পেজ রিফ্রেশ করুন।'
                : 'লাইভ কল বাটন বন্ধ — নিচের সবুজ ফোন লুকাবে (পেজ রিফ্রেশ করুন)।')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save settings')
                ->submit('save'),
        ];
    }

    public static function voiceGatewayStatusLabel(): string
    {
        if (! config('call_center.voice_call.enabled')) {
            return 'Disabled — set VOICE_CALL_ENABLED=true in server .env';
        }

        $driver = (string) config('call_center.voice_call.driver', 'log_only');

        if ($driver === 'http_webhook') {
            $url = trim((string) config('call_center.voice_call.webhook_url', ''));

            return $url !== ''
                ? "Enabled · http_webhook → {$url}"
                : 'Enabled · http_webhook — VOICE_CALL_WEBHOOK_URL is empty in .env';
        }

        if ($driver === 'log_only') {
            return 'Enabled · log_only (test mode — logs only, no real outbound call)';
        }

        return "Enabled · {$driver}";
    }
}
