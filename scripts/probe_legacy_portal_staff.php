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

$html = $http->get($base.'/UserRole/Index')->body();
preg_match_all('#/UserRole/[A-Za-z0-9_/]+#', $html, $m);
echo "UserRole paths:\n";
foreach (array_unique($m[0]) as $u) {
    echo "  $u\n";
}
preg_match_all('#Ajax[A-Za-z0-9]+#', $html, $a);
echo "\nAjax tokens: ".implode(', ', array_slice(array_unique($a[0]), 0, 30))."\n";
