<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSaasOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouterList extends Model
{
    use BelongsToSaasOperator;
    use HasFactory;

    protected $fillable = ['saas_operator_id', 'router_name', 'ip_address', 'username', 'password', 'action', 'ssh_port', 'api_port'];

    /**
     * Helper to encrypt value if it's plaintext.
     */
    private function looksEncrypted($value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        $decoded = json_decode(base64_decode($value, true) ?: '', true);

        return is_array($decoded)
            && isset($decoded['iv'], $decoded['value'], $decoded['mac']);
    }

    private function encryptValue($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if ($this->looksEncrypted($value)) {
            try {
                decrypt($value);

                return $value;
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Encrypted with another APP_KEY — leave as-is (do not double-encrypt).
                return $value;
            }
        }

        return encrypt($value);
    }

    /**
     * Helper to decrypt value if it's encrypted.
     */
    private function decryptValue($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return decrypt($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Wrong APP_KEY or legacy plaintext
            if ($this->looksEncrypted($value)) {
                return null;
            }

            return $value;
        }
    }

    // --- Getters ---

    public function getIpAddressAttribute($value)
    {
        return $this->decryptValue($value);
    }

    public function getUsernameAttribute($value)
    {
        return $this->decryptValue($value);
    }

    public function getPasswordAttribute($value)
    {
        return $this->decryptValue($value);
    }

    public function getSshPortAttribute($value)
    {
        $decrypted = $this->decryptValue($value);
        if ($decrypted === null || $decrypted === '') {
            return null;
        }

        return (int) $decrypted;
    }

    public function getApiPortAttribute($value)
    {
        $decrypted = $this->decryptValue($value);
        if ($decrypted === null || $decrypted === '') {
            return null;
        }

        return (int) $decrypted;
    }

    // --- Setters ---

    public function setIpAddressAttribute($value)
    {
        $this->attributes['ip_address'] = $this->encryptValue($value);
    }

    public function setUsernameAttribute($value)
    {
        $this->attributes['username'] = $this->encryptValue($value);
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $this->encryptValue($value);
    }

    public function setSshPortAttribute($value)
    {
        $value = $value === '' ? null : $value;
        $this->attributes['ssh_port'] = ($value !== null) ? $this->encryptValue($value) : null;
    }

    public function setApiPortAttribute($value)
    {
        $value = $value === '' ? null : $value;
        $this->attributes['api_port'] = ($value !== null) ? $this->encryptValue($value) : null;
    }
}
