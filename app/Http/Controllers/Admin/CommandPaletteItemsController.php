<?php

namespace App\Http\Controllers\Admin;

use App\Support\AdminCommandPalette;
use Illuminate\Http\JsonResponse;

final class CommandPaletteItemsController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'items' => AdminCommandPalette::items(),
        ]);
    }
}
