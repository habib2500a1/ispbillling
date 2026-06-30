<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiResellerAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerAiController extends Controller
{
    public function ask(Request $request, AiResellerAssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
        ]);

        $result = $assistant->ask($request->user(), (string) $data['query']);

        return response()->json([
            'reply' => $result['reply'] ?? '',
            'dashboard' => $result['dashboard'] ?? null,
            'advisory' => true,
        ]);
    }
}
