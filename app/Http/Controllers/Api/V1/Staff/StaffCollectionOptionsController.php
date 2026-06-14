<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Services\Billing\CollectionDiscountSettings;
use App\Models\User;
use App\Services\Collector\CollectorStaffResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffCollectionOptionsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $resolver = app(CollectorStaffResolver::class);
        $collectors = [];
        foreach ($resolver->collectableStaffOptions($user->tenant_id) as $id => $label) {
            $collectors[] = [
                'id' => (int) $id,
                'label' => $label,
                'name' => explode(' · ', $label)[0],
            ];
        }

        return response()->json([
            'data' => array_merge(
                CollectionDiscountSettings::mobileOptions($user),
                [
                    'can_pick_collector' => $resolver->canPickCollector($user),
                    'default_collector_id' => $resolver->defaultCollectorId($user),
                    'collectors' => $collectors,
                ],
            ),
        ]);
    }
}
