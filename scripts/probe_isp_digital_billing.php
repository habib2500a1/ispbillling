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
$http = $prop->getValue($client);

// Scan first 50 customers for any with invoices/payments
$page = $client->fetchCustomerPage(0, 50);
$found = [];
foreach ($page['aaData'] as $row) {
    $hid = (int) ($row['CustomerHeaderId'] ?? 0);
    if ($hid < 1) {
        continue;
    }
    $r = $http->asForm()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post($base.'/Customer/AjaxServiceAndProductInvoices', [
            'draw' => '1', 'start' => '0', 'length' => '5',
            'customerHeadId' => (string) $hid,
        ]);
    $invTotal = (int) ($r->json()['iTotalDisplayRecords'] ?? 0);
    $pr = $http->asForm()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post($base.'/Customer/AjaxReceivedHistory/'.$hid, ['draw' => '1', 'start' => '0', 'length' => '5']);
    $payTotal = (int) ($pr->json()['iTotalDisplayRecords'] ?? 0);
    if ($invTotal > 0 || $payTotal > 0) {
        $found[] = ['code' => $row['CustomerId'], 'hid' => $hid, 'inv' => $invTotal, 'pay' => $payTotal];
    }
}
echo 'Found with history: '.count($found)."\n";
print_r($found);

if ($found !== []) {
    $hid = $found[0]['hid'];
    $r = $http->asForm()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post($base.'/Customer/AjaxServiceAndProductInvoices', [
            'draw' => '1', 'start' => '0', 'length' => '3',
            'customerHeadId' => (string) $hid,
        ]);
    echo "\nInvoice sample:\n".json_encode($r->json()['aaData'][0] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    $pr = $http->asForm()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post($base.'/Customer/AjaxReceivedHistory/'.$hid, ['draw' => '1', 'start' => '0', 'length' => '3']);
    echo "\nPayment sample:\n".json_encode($pr->json()['data'][0] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
}

// Try transaction endpoint
$hid = (int) ($found[0]['hid'] ?? 1595);
foreach ([
    ['customerHeadId' => $hid],
    ['CustomerHeaderId' => $hid],
] as $p) {
    $r = $http->asForm()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post($base.'/Customer/AjaxCusomerTransactionData', array_merge(['draw' => '1', 'start' => '0', 'length' => '5'], $p));
    echo 'transaction '.json_encode($p).' => '.$r->status().' rows='.count($r->json()['aaData'] ?? $r->json()['data'] ?? [])."\n";
    $d = $r->json()['aaData'][0] ?? $r->json()['data'][0] ?? null;
    if ($d) {
        echo json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }
}
