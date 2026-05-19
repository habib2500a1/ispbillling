<?php

namespace App\Models;

use App\Support\Rbac\IspPermissionCatalog;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'category',
    ];

    public function resolvedLabel(): string
    {
        return $this->display_name
            ?? IspPermissionCatalog::labelFor($this->name);
    }

    public function resolvedCategory(): ?string
    {
        if (filled($this->category)) {
            return IspPermissionCatalog::categoryLabels()[$this->category] ?? $this->category;
        }

        return IspPermissionCatalog::categoryFor($this->name);
    }
}
