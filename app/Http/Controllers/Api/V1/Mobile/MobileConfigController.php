<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileConfigService;
use Illuminate\Http\JsonResponse;

class MobileConfigController extends Controller
{
    public function show(MobileConfigService $config): JsonResponse
    {
        return response()->json($config->payload());
    }
}
