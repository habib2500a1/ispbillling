<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;

final class NagadCrypto
{
    public function __construct(
        private readonly string $merchantPrivateKeyPem,
        private readonly string $pgPublicKeyPem,
    ) {}

    public static function fromConfig(): self
    {
        $merchantPrivate = self::wrapPrivateKey((string) config('nagad.merchant_private_key'));
        $pgPublic = self::wrapPublicKey((string) config('nagad.pg_public_key'));

        return new self($merchantPrivate, $pgPublic);
    }

    public function encryptSensitive(string $json): string
    {
        $key = openssl_pkey_get_public($this->pgPublicKeyPem);
        if ($key === false) {
            throw new PaymentGatewayException('Invalid Nagad PG public key.');
        }

        $encrypted = '';
        if (! openssl_public_encrypt($json, $encrypted, $key, OPENSSL_PKCS1_PADDING)) {
            throw new PaymentGatewayException('Nagad encryption failed.');
        }

        return base64_encode($encrypted);
    }

    public function sign(string $json): string
    {
        $key = openssl_pkey_get_private($this->merchantPrivateKeyPem);
        if ($key === false) {
            throw new PaymentGatewayException('Invalid Nagad merchant private key.');
        }

        $signature = '';
        if (! openssl_sign($json, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new PaymentGatewayException('Nagad signature failed.');
        }

        return base64_encode($signature);
    }

    public function decryptSensitive(string $base64Cipher): string
    {
        $key = openssl_pkey_get_private($this->merchantPrivateKeyPem);
        if ($key === false) {
            throw new PaymentGatewayException('Invalid Nagad merchant private key.');
        }

        $plain = '';
        if (! openssl_private_decrypt(base64_decode($base64Cipher), $plain, $key, OPENSSL_PKCS1_PADDING)) {
            throw new PaymentGatewayException('Nagad decrypt failed.');
        }

        return $plain;
    }

    private static function wrapPrivateKey(string $raw): string
    {
        $raw = trim(str_replace(["\r\n", "\r"], "\n", $raw));
        if (str_contains($raw, 'BEGIN')) {
            return $raw;
        }

        $body = preg_replace('/\s+/', '', $raw) ?? $raw;

        return "-----BEGIN RSA PRIVATE KEY-----\n".chunk_split($body, 64, "\n").'-----END RSA PRIVATE KEY-----';
    }

    private static function wrapPublicKey(string $raw): string
    {
        $raw = trim(str_replace(["\r\n", "\r"], "\n", $raw));
        if (str_contains($raw, 'BEGIN')) {
            return $raw;
        }

        $body = preg_replace('/\s+/', '', $raw) ?? $raw;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split($body, 64, "\n").'-----END PUBLIC KEY-----';
    }
}
