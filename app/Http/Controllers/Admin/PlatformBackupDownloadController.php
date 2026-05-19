<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\ManagePlatformBackups;
use App\Http\Controllers\Controller;
use App\Services\System\PlatformBackupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlatformBackupDownloadController extends Controller
{
    public function __invoke(string $id, PlatformBackupService $backups): BinaryFileResponse
    {
        abort_unless(ManagePlatformBackups::canAccess(), 403);

        $path = $backups->resolveZipPath($id);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
        ]);
    }
}
