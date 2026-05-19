<?php

namespace Tests\Unit;

use App\Services\Notifications\Gateways\KhudeBartaSmsGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KhudeBartaSmsGatewayTest extends TestCase
{
    public function test_builds_json_payload_with_hash(): void
    {
        config([
            'notifications.sms.api_key' => '7ce0011ff5af2fc6',
            'notifications.sms.secret_key' => '1d83d261',
            'notifications.sms.sender_id' => 'TESTID',
            'notifications.sms.api_url' => 'http://portal.khudebarta.com:3775/sendtext',
        ]);

        $gw = new KhudeBartaSmsGateway;
        $hash = $gw->buildHash('7ce0011ff5af2fc6', '1d83d261', 'TESTID', '8801712345678', 'Hello');

        $this->assertSame(32, strlen($hash));
        $this->assertSame($hash, strtolower($hash));

        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $gw->send('01712345678', 'Hello');

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->url() === 'http://portal.khudebarta.com:3775/sendtext'
                && ($body['apikey'] ?? '') === '7ce0011ff5af2fc6'
                && ($body['callerID'] ?? '') === 'TESTID'
                && ($body['toUser'] ?? '') === '8801712345678'
                && ($body['messageContent'] ?? '') === 'Hello'
                && isset($body['hash']);
        });
    }
}
