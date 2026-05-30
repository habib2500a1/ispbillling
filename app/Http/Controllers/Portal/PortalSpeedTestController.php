<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'csrf_token' => csrf_token(),
        ]);
    }

    public function quickDownload(): StreamedResponse
    {
        return $this->streamDownloadBytes(
            (int) config('portal.speed_test.quick_download_bytes', 524_288),
        );
    }

    public function download(): StreamedResponse
    {
        return $this->streamDownloadBytes(
            (int) config('portal.speed_test.download_bytes', 2_097_152),
        );
    }

    private function streamDownloadBytes(int $bytes): StreamedResponse
    {
        $bytes = max(65536, min(8_388_608, $bytes));
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
        $request->validate([
            'data' => ['required', 'file', 'max:'.max(256, (int) config('portal.speed_test.upload_kilobytes', 768))],
        ]);

        $file = $request->file('data');
        $received = $file ? (int) $file->getSize() : 0;

        return response()->json([
            'bytes_received' => $received,
            'ok' => $received > 0,
        ]);
    }
}
