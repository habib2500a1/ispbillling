<?php

namespace App\Services\Import;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class LegacyPortalApplicationUserImporter
{
    public function __construct(
        private readonly int $tenantId = 1,
    ) {}

    /**
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function importAll(LegacyPortalSessionClient $client, bool $force = false): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        $page = $client->fetchApplicationUsersPage(0, 100);

        foreach ($page['aaData'] as $row) {
            $result = $this->importRow($row, $force);
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'imported'|'updated'|'skipped'
     */
    public function importRow(array $row, bool $force = false): string
    {
        $username = Str::lower(trim((string) ($row['UserName'] ?? '')));
        if ($username === '') {
            return 'skipped';
        }

        $email = 'legacyportal+'.$username.'@import.local';
        $name = trim((string) (($row['AssignedEmployeeName'] ?? '') ?: $row['UserName']));
        $existing = User::query()
            ->where('tenant_id', $this->tenantId)
            ->where('email', $email)
            ->first();

        if ($existing !== null && ! $force) {
            return 'skipped';
        }

        $active = strtolower((string) ($row['Status'] ?? 'true')) !== 'false';

        $attrs = [
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'email' => $email,
            'is_active' => $active,
        ];

        if ($existing !== null) {
            if ($force) {
                $existing->forceFill($attrs)->saveQuietly();
                $this->syncRole($existing, (string) ($row['AssignedRoleName'] ?? ''));
            }

            return 'updated';
        }

        $user = User::query()->create($attrs + [
            'password' => Hash::make(Str::random(24)),
        ]);
        $this->syncRole($user, (string) ($row['AssignedRoleName'] ?? ''));

        return 'imported';
    }

    private function syncRole(User $user, string $roleName): void
    {
        $role = match (strtolower($roleName)) {
            'employee' => 'cashier',
            'support' => 'cashier',
            'admin', 'administrator' => 'admin',
            default => 'cashier',
        };

        if (! $user->hasRole($role)) {
            $user->syncRoles([$role]);
        }
    }
}
