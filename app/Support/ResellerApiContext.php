<?php

namespace App\Support;

use App\Models\Reseller;
use App\Models\ResellerStaff;

/** Holds reseller + optional staff actor for Sanctum API requests. */
final class ResellerApiContext
{
    private ?Reseller $reseller = null;

    private ?ResellerStaff $staff = null;

    public function set(Reseller $reseller, ?ResellerStaff $staff = null): void
    {
        $this->reseller = $reseller;
        $this->staff = $staff;
    }

    public function reseller(): ?Reseller
    {
        return $this->reseller;
    }

    public function staff(): ?ResellerStaff
    {
        return $this->staff;
    }

    public function staffId(): ?int
    {
        return $this->staff?->id;
    }

    public function isOwner(): bool
    {
        return $this->staff === null;
    }
}
