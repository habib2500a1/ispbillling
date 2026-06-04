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
$http = $prop->getValue($client);

$home = $http->get($base.'/')->body();
if ($home === '' || strlen($home) < 100) {
    $home = $http->get($base.'/Home/Index')->body();
}
preg_match_all('#href="([^"]+)"#', $home, $m);
foreach (array_unique($m[1]) as $href) {
    if (preg_match('/user|role|staff|collect|employee|admin/i', $href)) {
        echo "$href\n";
    }
}

foreach (['/Home/Index', '/Dashboard/Index', '/Admin/Index'] as $path) {
    $r = $http->get($base.$path);
    if ($r->status() === 200 && strlen($r->body()) > 2000) {
        echo "\nOK $path\n";
        preg_match_all('#/[A-Za-z]+/Ajax[A-Za-z0-9]+#', $r->body(), $ajax);
        foreach (array_unique($ajax[0]) as $a) {
            if (preg_match('/user|role/i', $a)) {
                echo "  $a\n";
            }
        }
    }
}
