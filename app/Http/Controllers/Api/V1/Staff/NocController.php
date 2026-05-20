<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\MobileNocService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NocController extends Controller
{
    public function dashboard(Request $request, MobileNocService $noc): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($noc->dashboard($user));
    }
}
