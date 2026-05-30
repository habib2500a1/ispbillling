<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ResellerPortalAccessService
{
    public function defaultPassword(): string
    {
        return (string) config('reseller_portal.default_password', '123456');
    }

    public function ensurePortalPassword(Reseller $reseller, ?string $plain = null): Reseller
    {
        $reseller = $reseller->fresh() ?? $reseller;

        if ($reseller->hasPortalAccess()) {
            return $reseller;
        }

        $password = $plain ?? $this->defaultPassword();
        $this->setPortalPassword($reseller, $password);

        return $reseller->fresh() ?? $reseller;
    }

    public function resetPortalPassword(Reseller $reseller, ?string $plain = null): string
    {
        $password = $plain ?? $this->defaultPassword();
        $this->setPortalPassword($reseller, $password);

        return $password;
    }

    public function setPortalPassword(Reseller $reseller, string $plain): void
    {
        $meta = is_array($reseller->meta) ? $reseller->meta : [];
        $meta['portal_password_plain'] = $plain;

        $reseller->forceFill([
            'portal_password' => Hash::make($plain),
            'meta' => $meta,
        ])->saveQuietly();
    }

    public function storePlainPasswordIfKnown(Reseller $reseller, string $plain): void
    {
        $meta = is_array($reseller->meta) ? $reseller->meta : [];
        $meta['portal_password_plain'] = $plain;

        $reseller->forceFill(['meta' => $meta])->saveQuietly();
    }

    public function portalPasswordPlain(Reseller $reseller): ?string
    {
        $plain = Arr::get($reseller->meta ?? [], 'portal_password_plain');

        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    public function portalLoginId(Reseller $reseller): string
    {
        return $reseller->portalLoginId();
    }

    public function ensureAccessToken(Reseller $reseller): string
    {
        $plain = $this->accessTokenPlain($reseller);
        if ($plain !== null && $this->hasAccessToken($reseller)) {
            return $plain;
        }

        return $this->regenerateAccessToken($reseller);
    }

    public function regenerateAccessToken(Reseller $reseller): string
    {
        $plain = $reseller->getKey().'-'.Str::lower(Str::random(32));
        $meta = is_array($reseller->meta) ? $reseller->meta : [];
        $meta['portal_access_token'] = $plain;
        $meta['portal_access_token_hash'] = Hash::make($plain);
        $meta['portal_access_token_at'] = now()->toIso8601String();

        $reseller->forceFill(['meta' => $meta])->saveQuietly();

        return $plain;
    }

    public function accessTokenUrl(Reseller $reseller): string
    {
        $token = $this->ensureAccessToken($reseller);

        return route('reseller.access.token', ['token' => $token]);
    }

    public function findResellerByAccessToken(string $token): ?Reseller
    {
        $token = trim($token);
        if ($token === '' || ! preg_match('/^(\d+)-([a-zA-Z0-9]{16,64})$/', $token, $matches)) {
            return null;
        }

        $reseller = Reseller::query()
            ->withoutGlobalScopes()
            ->whereKey((int) $matches[1])
            ->first();

        if ($reseller === null || ! $reseller->is_active) {
            return null;
        }

        $hash = Arr::get($reseller->meta ?? [], 'portal_access_token_hash');
        if (! is_string($hash) || $hash === '' || ! Hash::check($token, $hash)) {
            return null;
        }

        return $reseller;
    }

    public function hasAccessToken(Reseller $reseller): bool
    {
        return filled(Arr::get($reseller->meta ?? [], 'portal_access_token_hash'));
    }

    public function accessTokenPlain(Reseller $reseller): ?string
    {
        $plain = Arr::get($reseller->meta ?? [], 'portal_access_token');

        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    public function recordPortalLogin(Reseller $reseller): void
    {
        $reseller->forceFill(['portal_last_login_at' => now()])->saveQuietly();
    }

    public function bypassTwoFactorForSession(\Illuminate\Http\Request $request): void
    {
        $request->session()->put('reseller.2fa_passed', true);
    }
}
