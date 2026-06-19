<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Support\Gis\PostgisSupport;
use Illuminate\Http\JsonResponse;

class GisVectorTileController extends Controller
{
    public function manifest(): JsonResponse
    {
        $enabled = (bool) config('gis.vector_tiles.enabled', false) && PostgisSupport::enabled();
        $base = rtrim((string) config('gis.vector_tiles.base_url', '/gis/tiles'), '/');

        $layers = [];
        if ($enabled) {
            foreach (config('gis.vector_tiles.layers', []) as $key => $layer) {
                $layers[$key] = [
                    'label' => $layer['label'] ?? $key,
                    'url' => $base.'/'.($layer['table'] ?? $key).'/{z}/{x}/{y}.pbf',
                    'min_zoom' => $layer['min_zoom'] ?? 8,
                    'max_zoom' => $layer['max_zoom'] ?? 18,
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'enabled' => $enabled,
            'postgis' => PostgisSupport::enabled(),
            'layers' => $layers,
        ]);
    }
}
