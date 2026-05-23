<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class ResellerTwoFactorService
{
    public function __construct(
        private readonly Google2FA $google2fa = new Google2FA,
    ) {}

    public function isEnabled(Reseller $reseller): bool
    {
        return $reseller->two_factor_confirmed_at !== null;
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(Reseller $reseller, string $secret): string
    {
        $issuer = config('staff.two_factor_issuer', 'ISP Partner');
        $label = $reseller->portalLoginId();

        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.
            rawurlencode($this->google2fa->getQRCodeUrl($issuer, $label, $secret));
    }

    public function verify(Reseller $reseller, string $code): bool
    {
        $secret = $this->decryptSecret($reseller);
        if ($secret === null) {
            return false;
        }

        if ($this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code) ?? '')) {
            return true;
        }

        return $this->consumeRecoveryCode($reseller, $code);
    }

    /**
     * @return list<string>|false
     */
    public function enable(Reseller $reseller, string $secret, string $code): array|false
    {
        if (! $this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code) ?? '')) {
            return false;
        }

        [$stored, $plain] = $this->buildRecoveryCodes();

        $reseller->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $stored,
        ])->save();

        return $plain;
    }

    public function disable(Reseller $reseller): void
    {
        $reseller->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    private function decryptSecret(Reseller $reseller): ?string
    {
        if (blank($reseller->two_factor_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $reseller->two_factor_secret);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function buildRecoveryCodes(): array
    {
        $plain = collect(range(1, 8))->map(fn (): string => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)))->all();

        return [json_encode($plain), $plain];
    }

    private function consumeRecoveryCode(Reseller $reseller, string $code): bool
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $code) ?? '');
        $stored = json_decode((string) $reseller->two_factor_recovery_codes, true);
        if (! is_array($stored)) {
            return false;
        }

        foreach ($stored as $i => $recovery) {
            if (strtoupper((string) $recovery) === $normalized) {
                unset($stored[$i]);
                $reseller->forceFill([
                    'two_factor_recovery_codes' => json_encode(array_values($stored)),
                ])->save();

                return true;
            }
        }

        return false;
    }
}
