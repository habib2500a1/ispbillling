<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PortalSpeedTestController extends Controller
{
    public function index(): View
    {
        return view('portal.speed-test');
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'server_time' => microtime(true),
            'server' => config('app.name'),
        ]);
    }

    public function download(): Response
    {
        $bytes = (int) config('portal.speed_test.download_bytes', 2_097_152);
        $chunk = 65536;

        return response()->stream(function () use ($bytes, $chunk): void {
            $sent = 0;
            while ($sent < $bytes) {
                $size = min($chunk, $bytes - $sent);
                echo random_bytes($size);
                $sent += $size;
            }
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) $bytes,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $received = strlen((string) $request->getContent());

        return response()->json([
            'bytes_received' => $received,
            'ok' => true,
        ]);
    }
}
