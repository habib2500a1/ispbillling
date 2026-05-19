<?php

namespace App\Services\Staff;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $google2fa = new Google2FA,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(User $user, string $secret): string
    {
        $issuer = config('staff.two_factor_issuer', 'ISP Billing');
        $label = rawurlencode($issuer.':'.$user->email);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.
            rawurlencode($this->google2fa->getQRCodeUrl($issuer, $user->email, $secret));
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $this->decryptSecret($user);
        if ($secret === null) {
            return false;
        }

        if ($this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code) ?? '')) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * @return list<string>|false
     */
    public function enable(User $user, string $secret, string $code): array|false
    {
        if (! $this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code) ?? '')) {
            return false;
        }

        [$stored, $plain] = $this->buildRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $stored,
        ])->save();

        return $plain;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    /**
     * @return list<string>
     */
    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        [$stored, $plain] = $this->buildRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $stored])->save();

        return $plain;
    }

    /**
     * @return array{0: list<array{hash: string, used: bool}>, 1: list<string>}
     */
    private function buildRecoveryCodes(): array
    {
        $count = (int) config('staff.recovery_code_count', 8);
        $stored = [];
        $plain = [];
        for ($i = 0; $i < $count; $i++) {
            $code = Str::upper(Str::random(4).'-'.Str::random(4));
            $plain[] = $code;
            $stored[] = [
                'hash' => hash('sha256', $code),
                'used' => false,
            ];
        }

        return [$stored, $plain];
    }

    private function decryptSecret(User $user): ?string
    {
        if ($user->two_factor_secret === null) {
            return null;
        }

        try {
            return Crypt::decryptString($user->two_factor_secret);
        } catch (\Throwable) {
            return null;
        }
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = Str::upper(str_replace(' ', '', $code));
        $stored = $user->two_factor_recovery_codes;
        if (! is_array($stored)) {
            return false;
        }

        $hash = hash('sha256', $normalized);
        $updated = false;
        foreach ($stored as &$entry) {
            if (($entry['used'] ?? false) === true) {
                continue;
            }
            if (($entry['hash'] ?? '') === $hash) {
                $entry['used'] = true;
                $updated = true;
                break;
            }
        }
        unset($entry);

        if (! $updated) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $stored])->save();

        return true;
    }
}
