<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;

if (is_file(__DIR__.'/../storage/.production-live') && getenv('ALLOW_PRODUCTION_TESTS') !== '1') {
    fwrite(STDERR, "PHPUnit is disabled on this production server (storage/.production-live).\n");
    fwrite(STDERR, "Run tests locally or set ALLOW_PRODUCTION_TESTS=1 to override.\n");
    exit(1);
}

if (getenv('ALLOW_PRODUCTION_TESTS') !== '1' && in_array(getenv('APP_ENV') ?: '', ['production', 'prod'], true)) {
    fwrite(STDERR, "Refusing to run tests with APP_ENV=production on the live app tree.\n");
    exit(1);
}

require __DIR__.'/../vendor/autoload.php';

RefreshDatabaseState::$migrated = false;
RefreshDatabaseState::$inMemoryConnections = [];

$testingDb = __DIR__.'/../database/testing.sqlite';

if (! is_dir(dirname($testingDb))) {
    mkdir(dirname($testingDb), 0755, true);
}

if (is_file($testingDb)) {
    @unlink($testingDb);
}

touch($testingDb);
