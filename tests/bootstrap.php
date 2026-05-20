<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;

require __DIR__.'/../vendor/autoload.php';

RefreshDatabaseState::$migrated = false;
RefreshDatabaseState::$inMemoryConnections = [];

$testingDb = __DIR__.'/../database/testing.sqlite';

if (is_file($testingDb)) {
    unlink($testingDb);
}

if (! is_dir(dirname($testingDb))) {
    mkdir(dirname($testingDb), 0755, true);
}

touch($testingDb);
