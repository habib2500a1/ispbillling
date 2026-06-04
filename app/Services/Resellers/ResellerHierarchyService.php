<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use Illuminate\Support\Collection;

final class ResellerHierarchyService
{
    public function syncPath(Reseller $reseller): void
    {
        $segments = [];
        $depth = 0;
        $current = $reseller;

        while ($current !== null) {
            array_unshift($segments, (string) $current->id);
            if ($current->parent_id) {
                $current = Reseller::query()->withoutGlobalScopes()->find($current->parent_id);
                $depth++;
            } else {
                $current = null;
            }

            if ($depth > 50) {
                break;
            }
        }

        $reseller->forceFill([
            'hierarchy_path' => '/'.implode('/', $segments).'/',
            'hierarchy_depth' => $depth,
        ])->saveQuietly();
    }

    /**
     * @return Collection<int, Reseller>
     */
    public function ancestors(Reseller $reseller): Collection
    {
        $ancestors = collect();
        $parentId = $reseller->parent_id;

        while ($parentId !== null && $ancestors->count() < 50) {
            $parent = Reseller::query()->withoutGlobalScopes()->find($parentId);
            if ($parent === null) {
                break;
            }
            $ancestors->push($parent);
            $parentId = $parent->parent_id;
        }

        return $ancestors;
    }

    /**
     * @return Collection<int, Reseller>
     */
    public function descendants(Reseller $reseller, bool $includeSelf = false): Collection
    {
        $path = $reseller->hierarchy_path;
        if (blank($path)) {
            $this->syncPath($reseller);
            $path = $reseller->fresh()->hierarchy_path;
        }

        $query = Reseller::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $reseller->tenant_id)
            ->where('hierarchy_path', 'like', rtrim((string) $path, '/').'/%');

        if (! $includeSelf) {
            $query->where('id', '!=', $reseller->id);
        }

        return $query->orderBy('hierarchy_depth')->orderBy('name')->get();
    }

    public function isDescendantOf(Reseller $child, Reseller $ancestor): bool
    {
        $path = (string) $child->hierarchy_path;
        $ancestorPath = rtrim((string) $ancestor->hierarchy_path, '/').'/';

        return $path !== '' && str_starts_with($path, $ancestorPath) && $child->id !== $ancestor->id;
    }

    public function canCreateChild(Reseller $parent, string $childType): bool
    {
        return true;
    }
}
