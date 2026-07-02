<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode'));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && $token === (string) config('whatsapp_bot.verify_token')) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        abort(403);
    }

    public function handle(Request $request, WhatsAppBotService $bot): Response
    {
        if (! config('whatsapp_bot.enabled', false)) {
            return response('disabled', 503);
        }

        if (! $this->isValidSignature($request)) {
            Log::warning('whatsapp.bot.invalid_signature', [
                'ip' => $request->ip(),
            ]);

            return response('unauthorized', 401);
        }

        try {
            $bot->handleWebhookPayload($request->all());
        } catch (\Throwable $e) {
            Log::warning('whatsapp.bot.handle_failed', ['error' => $e->getMessage()]);
        }

        return response('ok', 200);
    }

    private function isValidSignature(Request $request): bool
    {
        $secret = (string) config('whatsapp_bot.app_secret', '');
        if ($secret === '') {
            return ! app()->environment('production');
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', (string) $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }
}
