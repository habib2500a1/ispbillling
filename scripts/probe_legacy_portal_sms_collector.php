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

$hid = 1585;
$posts = [
    '/Customer/AjaxMessagesHistory/'.$hid,
    '/Customer/AjaxRemarksHistory/'.$hid,
    '/Customer/AjaxCompainHistory/'.$hid,
];

foreach ($posts as $url) {
    $r = $http->asForm()->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Referer' => $base.'/Customer/Details/'.$hid,
    ])->post($base.$url, [
        'draw' => '1', 'start' => '0', 'length' => '50',
        'customerHeadId' => (string) $hid,
    ]);
    $json = $r->json();
    $rows = $json['aaData'] ?? $json['data'] ?? [];
    echo basename($url)." HTTP {$r->status()} total=".($json['iTotalDisplayRecords'] ?? count($rows))."\n";
    if ($rows !== []) {
        echo json_encode($rows[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }
}

$html = $http->get($base.'/UserRole/Index')->body();
preg_match_all('#/UserRole/[A-Za-z0-9_/]+#', $html, $m);
echo "\nUserRole paths:\n";
foreach (array_unique($m[0]) as $u) {
    echo "  $u\n";
}
preg_match_all('#/User/[A-Za-z0-9_/]+#', $html, $m2);
echo "\nUser paths in UserRole page:\n";
foreach (array_unique($m2[0]) as $u) {
    echo "  $u\n";
}

// Customer details HTML for collector label
$html = $client->fetchCustomerDetailsHtml($hid);
if (preg_match_all('#<div class="col-sm-5">([^<]+)</div>#', $html, $labels)) {
    foreach ($labels[1] as $l) {
        $l = trim(html_entity_decode($l));
        if (preg_match('/collect|assign|employee|agent|staff|technician|sales/i', $l)) {
            echo "Label: $l\n";
        }
    }
}
