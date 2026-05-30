<?php

namespace App\Support;

use App\Models\Reseller;
use App\Models\ResellerStaff;

final class ResellerPortalSession
{
    public function reseller(): ?Reseller
    {
        $user = auth('reseller')->user();
        if ($user instanceof Reseller) {
            return $user;
        }

        $sanctum = request()->user();
        if ($sanctum instanceof Reseller) {
            return $sanctum;
        }

        return app(ResellerApiContext::class)->reseller();
    }

    public function staff(): ?ResellerStaff
    {
        $reseller = $this->reseller();
        $staffId = session('reseller.staff_id');

        if ($staffId === null) {
            $staffId = app(ResellerApiContext::class)->staffId();
        }

        if ($reseller === null || $staffId === null) {
            return app(ResellerApiContext::class)->staff();
        }

        $staff = ResellerStaff::query()
            ->where('reseller_id', $reseller->id)
            ->whereKey((int) $staffId)
            ->where('is_active', true)
            ->first();

        if ($staff === null) {
            session()->forget(['reseller.staff_id', 'reseller.staff_name']);

            return null;
        }

        return $staff;
    }

    public function isOwner(): bool
    {
        return $this->staff() === null;
    }

    public function actorName(): string
    {
        return $this->staff()?->name ?? $this->reseller()?->name ?? '';
    }

    public function canPortal(string $permission): bool
    {
        $reseller = $this->reseller();
        if ($reseller === null) {
            return false;
        }

        if ($permission === ResellerPortalPermission::STAFF_MANAGE) {
            return $this->isOwner() && $reseller->canPortal($permission);
        }

        if (! $reseller->canPortal($permission)) {
            return false;
        }

        $staff = $this->staff();
        if ($staff !== null) {
            return $staff->canPortal($permission);
        }

        return true;
    }

    public function bindStaff(ResellerStaff $staff): void
    {
        session([
            'reseller.staff_id' => $staff->id,
            'reseller.staff_name' => $staff->name,
        ]);
    }

    public function clearStaff(): void
    {
        session()->forget(['reseller.staff_id', 'reseller.staff_name']);
    }
}
