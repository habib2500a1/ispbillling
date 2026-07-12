<?php

namespace App\Services\Portal;

use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CustomerPortalAccessService
{
    public function ensureAccessToken(CustomersInfo $customer): string
    {
        $customer = $customer->fresh() ?? $customer;

        if ($this->hasAccessToken($customer)) {
            return '';
        }

        return $this->regenerateAccessToken($customer);
    }

    public function regenerateAccessToken(CustomersInfo $customer): string
    {
        $customer = $customer->fresh() ?? $customer;
        $pppId = (int) ($customer->ppp_user_id ?? 0);

        if ($pppId < 1) {
            throw new \RuntimeException('Customer has no PPP user linked.');
        }

        $plain = $pppId.'-'.Str::lower(Str::random(32));

        $customer->forceFill([
            'portal_access_token_hash' => Hash::make($plain),
            'portal_access_token_at' => now(),
        ])->saveQuietly();

        return $plain;
    }

    public function accessTokenUrl(CustomersInfo $customer): ?string
    {
        if (! $customer->pppUser) {
            return null;
        }

        $token = $this->ensureAccessToken($customer);
        if ($token === '') {
            return null;
        }

        return route('portal.access.token', ['token' => $token]);
    }

    public function findPppUserByAccessToken(string $token): ?PPPSecrets
    {
        $token = trim($token);
        if ($token === '' || ! preg_match('/^(\d+)-([a-zA-Z0-9]{16,64})$/', $token, $matches)) {
            return null;
        }

        $pppId = (int) $matches[1];
        $ppp = PPPSecrets::query()->find($pppId);
        if (! $ppp) {
            return null;
        }

        $customer = CustomersInfo::query()
            ->where('ppp_user_id', $ppp->id)
            ->first();

        if (! $customer || ! is_string($customer->portal_access_token_hash) || $customer->portal_access_token_hash === '') {
            return null;
        }

        if (! Hash::check($token, $customer->portal_access_token_hash)) {
            return null;
        }

        if (in_array($customer->status, ['deleted'], true)) {
            return null;
        }

        return $ppp;
    }

    public function hasAccessToken(CustomersInfo $customer): bool
    {
        return filled($customer->portal_access_token_hash);
    }

    public function portalCredentialsSummary(CustomersInfo $customer): string
    {
        $username = $customer->pppUser?->username ?? '—';

        return "Login: {$username}\nPassword: PPP password (MikroTik secret)";
    }
}
