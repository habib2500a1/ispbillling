<?php

namespace App\Services\Radius;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RadiusUserManagementService
{
    public function isAvailable(): bool
    {
        if (! config('radius_admin.enabled', false)) {
            return false;
        }

        try {
            DB::connection('radius')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function listUsernames(int $limit = 500): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $table = config('radius_admin.radcheck_table', 'radcheck');

        return DB::connection('radius')
            ->table($table)
            ->distinct()
            ->orderBy('username')
            ->limit($limit)
            ->pluck('username')
            ->map(fn ($u) => (string) $u)
            ->all();
    }

    /**
     * @return Collection<int, object>
     */
    public function getCheckAttributes(string $username): Collection
    {
        if (! $this->isAvailable()) {
            return collect();
        }

        return DB::connection('radius')
            ->table(config('radius_admin.radcheck_table', 'radcheck'))
            ->where('username', $username)
            ->orderBy('id')
            ->get();
    }

    public function createUser(string $username, string $password, ?string $group = null): void
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('RADIUS database is not available.');
        }

        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new \InvalidArgumentException('Username and password are required.');
        }

        DB::connection('radius')->transaction(function () use ($username, $password, $group): void {
            $check = config('radius_admin.radcheck_table', 'radcheck');
            DB::connection('radius')->table($check)->insert([
                'username' => $username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $password,
            ]);

            if ($group !== null && $group !== '') {
                DB::connection('radius')->table(config('radius_admin.usergroup_table', 'radusergroup'))->insert([
                    'username' => $username,
                    'groupname' => $group,
                    'priority' => 1,
                ]);
            }
        });
    }

    public function deleteUser(string $username): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        foreach ([
            config('radius_admin.radcheck_table', 'radcheck'),
            config('radius_admin.radreply_table', 'radreply'),
            config('radius_admin.usergroup_table', 'radusergroup'),
        ] as $table) {
            try {
                DB::connection('radius')->table($table)->where('username', $username)->delete();
            } catch (\Throwable $e) {
                Log::debug('radius.delete_table_skip', ['table' => $table, 'error' => $e->getMessage()]);
            }
        }
    }

    public function usernameForCustomer(Customer $customer): string
    {
        $username = trim((string) ($customer->radius_username ?: $customer->customer_code));

        return $username !== '' ? $username : 'cust'.$customer->id;
    }

    public function ensureCustomerUser(Customer $customer, ?string $password = null): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $username = $this->usernameForCustomer($customer);
        $exists = DB::connection('radius')
            ->table(config('radius_admin.radcheck_table', 'radcheck'))
            ->where('username', $username)
            ->exists();

        if ($exists) {
            return;
        }

        $secret = $password
            ?? (filled($customer->portal_password) ? (string) $customer->portal_password : null)
            ?? $username;

        $group = $customer->package?->mikrotik_profile_name;
        $this->createUser($username, $secret, filled($group) ? (string) $group : null);
    }

    public function setDownloadRateLimit(string $username, int $downloadMbps): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $table = config('radius_admin.radreply_table', 'radreply');
        $rate = $downloadMbps.'M/'.$downloadMbps.'M';

        DB::connection('radius')->table($table)
            ->where('username', $username)
            ->where('attribute', 'Mikrotik-Rate-Limit')
            ->delete();

        DB::connection('radius')->table($table)->insert([
            'username' => $username,
            'attribute' => 'Mikrotik-Rate-Limit',
            'op' => ':=',
            'value' => $rate,
        ]);
    }

    public function clearRateLimit(string $username): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        DB::connection('radius')
            ->table(config('radius_admin.radreply_table', 'radreply'))
            ->where('username', $username)
            ->where('attribute', 'Mikrotik-Rate-Limit')
            ->delete();
    }

    public function setReject(string $username, bool $reject): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $table = config('radius_admin.radcheck_table', 'radcheck');
        DB::connection('radius')->table($table)
            ->where('username', $username)
            ->where('attribute', 'Auth-Type')
            ->delete();

        if ($reject) {
            DB::connection('radius')->table($table)->insert([
                'username' => $username,
                'attribute' => 'Auth-Type',
                'op' => ':=',
                'value' => 'Reject',
            ]);
        }
    }
}
