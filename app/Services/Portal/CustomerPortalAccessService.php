<?php

namespace App\Services\Portal;

use App\Models\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CustomerPortalAccessService
{
    public function defaultPassword(): string
    {
        return (string) config('portal.default_password', '123456');
    }

    public function ensurePortalPassword(Customer $customer, ?string $plain = null): Customer
    {
        $customer = $customer->fresh() ?? $customer;

        if ($customer->portalAccessEnabled()) {
            return $customer;
        }

        $password = $plain ?? $this->defaultPassword();
        $customer->forceFill([
            'portal_password' => Hash::make($password),
        ])->saveQuietly();

        return $customer->fresh() ?? $customer;
    }

    public function resetPortalPassword(Customer $customer, ?string $plain = null): string
    {
        $password = $plain ?? $this->defaultPassword();
        $customer->forceFill([
            'portal_password' => Hash::make($password),
        ])->saveQuietly();

        return $password;
    }

    public function portalLoginId(Customer $customer): string
    {
        if (filled($customer->customer_code)) {
            return (string) $customer->customer_code;
        }

        if (filled($customer->phone)) {
            return (string) $customer->phone;
        }

        return (string) ($customer->email ?? 'subscriber-'.$customer->getKey());
    }

    public function ensureAccessToken(Customer $customer): string
    {
        $plain = $this->accessTokenPlain($customer);
        if ($plain !== null && $this->hasAccessToken($customer)) {
            return $plain;
        }

        return $this->regenerateAccessToken($customer);
    }

    public function regenerateAccessToken(Customer $customer): string
    {
        $plain = $customer->getKey().'-'.Str::lower(Str::random(32));
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['portal_access_token'] = $plain;
        $meta['portal_access_token_hash'] = Hash::make($plain);
        $meta['portal_access_token_at'] = now()->toIso8601String();

        $customer->forceFill(['meta' => $meta])->saveQuietly();

        return $plain;
    }

    public function accessTokenUrl(Customer $customer): string
    {
        $token = $this->ensureAccessToken($customer);

        return route('portal.access.token', ['token' => $token]);
    }

    public function findCustomerByAccessToken(string $token): ?Customer
    {
        $token = trim($token);
        if ($token === '' || ! preg_match('/^(\d+)-([a-zA-Z0-9]{16,64})$/', $token, $matches)) {
            return null;
        }

        $customer = Customer::query()
            ->withoutGlobalScopes()
            ->whereKey((int) $matches[1])
            ->first();

        if ($customer === null) {
            return null;
        }

        $hash = Arr::get($customer->meta ?? [], 'portal_access_token_hash');
        if (! is_string($hash) || $hash === '' || ! Hash::check($token, $hash)) {
            return null;
        }

        if ($customer->status !== \App\Support\CustomerStatus::ACTIVE) {
            return null;
        }

        return $customer;
    }

    public function hasAccessToken(Customer $customer): bool
    {
        return filled(Arr::get($customer->meta ?? [], 'portal_access_token_hash'));
    }

    public function accessTokenPlain(Customer $customer): ?string
    {
        $plain = Arr::get($customer->meta ?? [], 'portal_access_token');

        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    public function portalCredentialsSummary(Customer $customer): string
    {
        $login = $this->portalLoginId($customer);
        $defaultNote = $customer->portalAccessEnabled()
            ? 'Password: set (default was '. $this->defaultPassword().' if never changed)'
            : 'Password: will use default '.$this->defaultPassword().' on first login';

        return "Login: {$login}\n{$defaultNote}";
    }
}
