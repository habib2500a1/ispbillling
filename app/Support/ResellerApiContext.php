<?php

namespace App\Support;

use App\Models\Reseller;
use App\Models\ResellerApiKey;
use App\Models\ResellerStaff;

/** Holds reseller + optional staff actor for Sanctum API requests. */
final class ResellerApiContext
{
    private ?Reseller $reseller = null;

    private ?ResellerStaff $staff = null;

    private ?ResellerApiKey $apiKey = null;

    public function set(Reseller $reseller, ?ResellerStaff $staff = null, ?ResellerApiKey $apiKey = null): void
    {
        $this->reseller = $reseller;
        $this->staff = $staff;
        $this->apiKey = $apiKey;
    }

    public function apiKey(): ?ResellerApiKey
    {
        return $this->apiKey;
    }

    public function usesApiKey(): bool
    {
        return $this->apiKey !== null;
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
