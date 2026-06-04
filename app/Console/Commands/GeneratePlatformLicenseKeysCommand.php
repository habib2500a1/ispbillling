<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GeneratePlatformLicenseKeysCommand extends Command
{
    protected $signature = 'isp:license:generate-keys
        {--force : Overwrite existing keys}';

    protected $description = 'Generate RSA keypair for signing sold (on-premise) licenses. Keep private key offline.';

    public function handle(): int
    {
        $publicPath = (string) config('isp.license.public_key_path');
        $privatePath = (string) config('isp.license.private_key_path');

        if (! $this->option('force') && (is_readable($publicPath) || is_readable($privatePath))) {
            $this->error('Keys already exist. Use --force to overwrite.');

            return self::FAILURE;
        }

        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($res === false) {
            $this->error('openssl_pkey_new failed.');

            return self::FAILURE;
        }

        openssl_pkey_export($res, $privatePem);
        $details = openssl_pkey_get_details($res);
        $publicPem = is_array($details) ? ($details['key'] ?? '') : '';

        if ($privatePem === '' || $publicPem === '') {
            $this->error('Failed to export keys.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($publicPath));
        File::ensureDirectoryExists(dirname($privatePath));

        File::put($publicPath, $publicPem);
        File::put($privatePath, $privatePem);
        @chmod($privatePath, 0600);

        $this->info('Public key: '.$publicPath.' (ship with app)');
        $this->warn('Private key: '.$privatePath.' — BACK UP OFFLINE; never commit or give to customers.');

        return self::SUCCESS;
    }
}
