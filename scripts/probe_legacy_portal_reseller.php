<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = config('legacy_portal.base_url');
$client = new App\Services\Import\LegacyPortalSessionClient(
    $base,
    config('legacy_portal.username'),
    config('legacy_portal.password'),
);
$client->login();

$ref = new ReflectionClass($client);
$prop = $ref->getProperty('http');
$prop->setAccessible(true);
/** @var \Illuminate\Http\Client\PendingRequest $http */
$http = $prop->getValue($client);

$paths = [
    '/Reseller/Index', '/Resellers/Index', '/MacReseller/Index', '/MACReseller/Index',
    '/SubReseller/Index', '/Franchise/Index', '/Dealer/Index', '/Agent/Index',
    '/ResellerManagement/Index', '/Mac/Index',
];

echo "=== Pages ===\n";
foreach ($paths as $path) {
    $r = $http->get($base.$path);
    if ($r->status() === 200 && strlen($r->body()) > 3000) {
        echo "OK $path (".strlen($r->body()).")\n";
        preg_match_all('#/[A-Za-z]+/[A-Za-z0-9_/]+#', $r->body(), $m);
        foreach (array_unique($m[0]) as $u) {
            if (stripos($u, 'resell') !== false || stripos($u, 'mac') !== false || stripos($u, 'Ajax') !== false) {
                echo "  $u\n";
            }
        }
    }
}

$home = $http->get($base.'/Dashboard/Index')->body();
preg_match_all('#href="(/[^"]*resell[^"]*)"#i', $home, $links);
echo "\nDashboard reseller links:\n";
foreach (array_unique($links[1] ?? []) as $l) {
    echo "  $l\n";
}

echo "\n=== Customer row reseller fields ===\n";
$page = $client->fetchCustomerPage(0, 525);
$keys = [];
foreach ($page['aaData'] as $row) {
    foreach ($row as $k => $v) {
        if ($v !== null && $v !== '' && $v !== false && preg_match('/resell|dealer|franch|agent|mac/i', (string) $k)) {
            $keys[$k] = ($keys[$k] ?? 0) + 1;
        }
    }
}
print_r($keys);
