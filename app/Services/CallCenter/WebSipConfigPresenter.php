<?php

namespace App\Services\CallCenter;

use App\Models\User;
use App\Support\WebSipFeature;

/**
 * Safe client-side WebSIP config — same SIP profile as PortSIP (UDP 5060).
 */
final class WebSipConfigPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public function forUser(?User $user): ?array
    {
        if ($user === null || ! WebSipFeature::isEnabledForUser($user)) {
            return null;
        }

        $profile = PortSipConnectionProfile::forTenant(WebSipFeature::tenantIdFor($user));
        if ($profile === null) {
            return null;
        }

        $config = $profile->toWebSipClientConfig($user->name);
        $config['settings_url'] = \App\Filament\Pages\ManageCallCenterSettings::getUrl();

        if ($config['identity_host'] === '' || $config['wss_uris'] === []) {
            $config['configured'] = false;
        }

        return $config;
    }

    public static function encryptPassword(string $plain): string
    {
        return \Illuminate\Support\Facades\Crypt::encryptString($plain);
    }
}
