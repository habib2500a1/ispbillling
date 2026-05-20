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

$html = $http->get($base.'/Customer/Details/1595')->body();
file_put_contents('/tmp/isp_digital_details.html', $html);

preg_match_all('#/Customer/[A-Za-z0-9_/]+#', $html, $m);
echo "Customer paths:\n";
foreach (array_unique($m[0]) as $u) {
    echo "  $u\n";
}

if (preg_match_all('#data-[a-z-]+="([^"]+)"#i', $html, $dm)) {
    echo "\nSample data attrs (first 20):\n";
    foreach (array_slice(array_unique($dm[1]), 0, 20) as $v) {
        echo "  $v\n";
    }
}

// Extract label/value pairs from client details table pattern
preg_match_all('#<td[^>]*class="[^"]*detail-label[^"]*"[^>]*>([^<]+)</td>\s*<td[^>]*>([^<]*)</td>#i', $html, $pairs, PREG_SET_ORDER);
echo "\nDetail pairs found: ".count($pairs)."\n";
foreach (array_slice($pairs, 0, 15) as $p) {
    echo trim($p[1]).' => '.trim(strip_tags($p[2]))."\n";
}
