<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiOperationsOrchestrator;
use App\Support\Rbac\StaffCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAiController extends Controller
{
    public function ask(Request $request, AiOperationsOrchestrator $orchestrator): JsonResponse
    {
        $this->authorizeAiAccess($request);
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

    public function dashboard(Request $request, AiOperationsOrchestrator $orchestrator): JsonResponse
    {
        $this->authorizeAiAccess($request);

        return response()->json($orchestrator->dashboard());
    }

    private function authorizeAiAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof \App\Models\User, 403);

        $cap = StaffCapability::for($user);
        abort_unless(
            $cap->canReports() || $cap->canBilling() || $cap->canNetwork() || $cap->canSupport(),
            403,
            'AI copilot access requires reports, billing, network, or support permission.',
        );
    }
}
