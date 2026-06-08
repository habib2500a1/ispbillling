<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiOperationsOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAiController extends Controller
{
    public function ask(Request $request, AiOperationsOrchestrator $orchestrator): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'session' => ['nullable', 'array'],
        ]);

        $result = $orchestrator->ask(
            (string) $data['query'],
            is_array($data['session'] ?? null) ? $data['session'] : [],
        );

        return response()->json([
            'reply' => $result['reply'] ?? '',
            'cards' => $result['cards'] ?? [],
            'table' => $result['table'] ?? null,
            'links' => $result['links'] ?? [],
            'domain' => $result['domain'] ?? 'general',
            'session' => $result['session'] ?? [],
            'advisory' => true,
        ]);
    }

    public function dashboard(AiOperationsOrchestrator $orchestrator): JsonResponse
    {
        return response()->json($orchestrator->dashboard());
    }
}
