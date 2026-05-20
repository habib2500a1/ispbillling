<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = config('isp_digital.base_url');
$client = new App\Services\Import\IspDigitalSessionClient(
    $base,
    config('isp_digital.username'),
    config('isp_digital.password'),
);
$client->login();

$ref = new ReflectionClass($client);
$prop = $ref->getProperty('http');
$prop->setAccessible(true);
/** @var \Illuminate\Http\Client\PendingRequest $http */
$http = $prop->getValue($client);

foreach ([1, 3, 1595] as $hid) {
    $html = $http->get($base.'/Customer/Details?id='.$hid)->body();
    echo "=== Customer $hid ===\n";
    foreach (['Prepaid', 'prepaid', 'Postpaid', 'BillPeriod', 'BillingPeriod', 'AdvancePayment', 'IsPrepaid'] as $needle) {
        if (stripos($html, $needle) !== false) {
            echo "  contains: $needle\n";
        }
    }
    if (preg_match('/id="[^"]*[Pp]repaid[^"]*"/', $html, $m)) {
        echo '  id: '.$m[0]."\n";
    }
}
