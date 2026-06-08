<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiOperationsOrchestrator;
use App\Support\Rbac\StaffCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiCopilotController extends Controller
{
    public function dashboard(AiOperationsOrchestrator $orchestrator): JsonResponse
    {
        $this->authorizeAccess();

        return response()->json($orchestrator->dashboard());
    }

    public function ask(Request $request, AiOperationsOrchestrator $orchestrator): JsonResponse
    {
        $this->authorizeAccess();

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

    private function authorizeAccess(): void
    {
        $cap = StaffCapability::for(auth()->user());

        abort_unless(
            $cap->canReports()
                || $cap->canBilling()
                || $cap->canNetwork()
                || $cap->canSupport(),
            403,
        );
    }
}
