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

foreach ([2, 3] as $rid) {
    $r = $http->asForm()->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Referer' => $base.'/MACReseller/Details/'.$rid,
    ])->post($base.'/MACReseller/AjaxMACResellerClientsByResellerID', [
        'draw' => '1',
        'start' => '0',
        'length' => '5',
        'id' => (string) $rid,
    ]);

    $json = $r->json();
    $rows = $json['data'] ?? $json['aaData'] ?? [];
    echo "rid={$rid} HTTP {$r->status()} total=".($json['iTotalDisplayRecords'] ?? count($rows))." rows=".count($rows)."\n";
    if ($rows !== []) {
        echo 'keys: '.implode(', ', array_keys($rows[0]))."\n";
        echo json_encode($rows[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }
}
