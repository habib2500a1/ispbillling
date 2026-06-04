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
    '/Sms/Index', '/SMS/Index', '/BulkSms/Index', '/Message/Index',
    '/SmsCampaign/Index', '/CustomerSms/Index', '/Notification/Index',
    '/ServiceInvoice/Index', '/UserRole/Index', '/User/Index',
    '/Customer/Index',
];

echo "=== Page probes ===\n";
foreach ($paths as $path) {
    $r = $http->get($base.$path);
    if ($r->status() === 200 && strlen($r->body()) > 5000) {
        echo "OK $path (".strlen($r->body())." bytes)\n";
        preg_match_all('#/[A-Za-z]+/Ajax[A-Za-z0-9]+#', $r->body(), $m);
        foreach (array_unique($m[0]) as $ajax) {
            if (stripos($ajax, 'sms') !== false || stripos($ajax, 'message') !== false
                || stripos($ajax, 'invoice') !== false || stripos($ajax, 'user') !== false
                || stripos($ajax, 'collect') !== false) {
                echo "  $ajax\n";
            }
        }
    }
}

echo "\n=== Service invoice pagination ===\n";
$invClient = new App\Services\Import\LegacyPortalSessionClient(
    $base, config('legacy_portal.username'), config('legacy_portal.password'),
);
$invClient->login();
foreach ([10, 100, 500] as $length) {
    $page = $invClient->fetchServiceInvoicePage(0, $length);
    echo "length=$length total=".$page['iTotalDisplayRecords'].' rows='.count($page['aaData'])."\n";
}
$start = 0;
$all = 0;
do {
    $page = $invClient->fetchServiceInvoicePage($start, 100);
    $all += count($page['aaData']);
    $total = $page['iTotalDisplayRecords'];
    $start += 100;
} while ($start < $total && count($page['aaData']) > 0);
echo "paginated fetch total rows=$all remote total=$total\n";

echo "\n=== Customer row collector fields (sample 100) ===\n";
$custClient = new App\Services\Import\LegacyPortalSessionClient(
    $base, config('legacy_portal.username'), config('legacy_portal.password'),
);
$custClient->login();
$page = $custClient->fetchCustomerPage(0, 100);
$withCollector = 0;
foreach ($page['aaData'] as $row) {
    foreach ($row as $k => $v) {
        if (is_string($v) && $v !== '' && preg_match('/collect|assign|employee|staff|agent|sales/i', (string) $k)) {
            echo $row['CustomerId']." $k => $v\n";
            $withCollector++;
        }
    }
}
echo "matches in first 100: $withCollector\n";
