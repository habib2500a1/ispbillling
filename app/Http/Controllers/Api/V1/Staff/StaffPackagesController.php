<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPackagesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->staff($request);

        $packages = Package::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Package $p) => $this->row($p));

        return response()->json(['data' => $packages]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->manager($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'download_mbps' => ['required', 'numeric', 'min:0'],
            'upload_mbps' => ['nullable', 'numeric', 'min:0'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $package = Package::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => $data['name'],
            'download_mbps' => $data['download_mbps'],
            'upload_mbps' => $data['upload_mbps'] ?? $data['download_mbps'],
            'price_monthly' => $data['price_monthly'],
            'is_active' => $data['is_active'] ?? true,
            'show_on_website' => true,
            'type' => 'residential',
            'pricing_model' => 'flat',
        ]);

        return response()->json(['package' => $this->row($package), 'message' => 'Package created.'], 201);
    }

    public function update(Request $request, int $package): JsonResponse
    {
        $user = $this->manager($request);

        $model = Package::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($package)
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'download_mbps' => ['sometimes', 'numeric', 'min:0'],
            'upload_mbps' => ['sometimes', 'numeric', 'min:0'],
            'price_monthly' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model->update($data);

        return response()->json(['package' => $this->row($model->fresh()), 'message' => 'Package updated.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Package $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'download_mbps' => $p->download_mbps,
            'upload_mbps' => $p->upload_mbps,
            'price_monthly' => (float) $p->price_monthly,
            'is_active' => (bool) $p->is_active,
            'show_on_website' => (bool) $p->show_on_website,
            'mikrotik_profile_name' => $p->mikrotik_profile_name,
        ];
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && $user->hasAnyRole([
                'super-admin', 'isp-admin', 'admin', 'isp-manager', 'branch-manager',
                'cashier', 'collector',
            ]),
            403,
        );

        return $user;
    }

    private function manager(Request $request): User
    {
        $user = $this->staff($request);
        abort_unless(
            $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'branch-manager']),
            403,
        );

        return $user;
    }
}
