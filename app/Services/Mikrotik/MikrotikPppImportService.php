<?php

namespace App\Services\Mikrotik;

use App\Http\Controllers\MikrotikController;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\OfficialInfo;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * anetbd-style PPP import for Code Pagol:
 * load secrets from router → import selected → create/update subscribers.
 */
class MikrotikPppImportService
{
    /**
     * @return list<array{name: string, password: string, profile: string, disabled: bool, comment: string, service: string}>
     */
    public function listSecretsFromRouter(RouterList $router): array
    {
        if ($router->action !== 'connected') {
            $router->action = 'connected';
            $router->save();
        }

        $result = app(MikrotikController::class)->routerList(
            $router->router_name,
            '/ppp/secret/print',
            '/ppp secret print without-paging terse',
            [],
            false
        );

        $payload = $result[$router->router_name] ?? null;
        if (! is_array($payload) || empty($payload['status'])) {
            $msg = is_array($payload) ? ($payload['message'] ?? 'Router unreachable') : 'Router unreachable';
            throw new \RuntimeException($msg);
        }

        $rows = [];
        foreach ($payload['data'] ?? [] as $user) {
            if (! is_array($user) || empty($user['name'])) {
                continue;
            }

            $disabled = false;
            if (isset($user['disabled'])) {
                $disabled = $user['disabled'] === true || $user['disabled'] === 'true' || $user['disabled'] === 'yes';
            } elseif (isset($user['status']) && strtolower((string) $user['status']) === 'disable') {
                $disabled = true;
            }

            $rows[] = [
                'name' => (string) $user['name'],
                'password' => (string) ($user['password'] ?? ''),
                'profile' => (string) ($user['profile'] ?? '-'),
                'disabled' => $disabled,
                'comment' => (string) ($user['comment'] ?? ''),
                'service' => (string) ($user['service'] ?? 'pppoe'),
                'caller_id' => (string) ($user['caller-id'] ?? ''),
                'remote_address' => (string) ($user['remote-address'] ?? $user['ppp_remote_ip'] ?? ''),
            ];
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @param  list<string>  $secretNames
     * @param  array{create_missing?: bool, update_existing?: bool}  $options
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importSelectedFromRouter(RouterList $router, array $secretNames, array $options = [], ?array $prefetched = null): array
    {
        $secretNames = array_values(array_filter(array_map('trim', $secretNames)));
        if ($secretNames === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['No users selected.']];
        }

        $createMissing = (bool) ($options['create_missing'] ?? true);
        $updateExisting = (bool) ($options['update_existing'] ?? true);

        $all = collect($prefetched ?? $this->listSecretsFromRouter($router))->keyBy(fn ($r) => strtolower($r['name']));
        $wanted = [];
        foreach ($secretNames as $name) {
            $row = $all->get(strtolower($name));
            if ($row) {
                $wanted[] = $row;
            }
        }

        if ($wanted === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Selected users not found on router.']];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $idAllocator = app(\App\Services\Billing\CustomerIdAllocator::class);
        $prefix = $idAllocator->prefix();
        $lastIdCount = $idAllocator->highestNumber();
        $packageMap = $this->packagesByProfile($router->router_name);

        $existingSecrets = PPPSecrets::where('router_name', $router->router_name)
            ->get()
            ->keyBy(fn ($s) => strtolower((string) $s->username));

        DB::beginTransaction();
        try {
            foreach ($wanted as $row) {
                try {
                    $lower = strtolower($row['name']);
                    $secret = $existingSecrets->get($lower);
                    $status = $row['disabled'] ? 'disable' : 'active';
                    $packageId = $packageMap[$row['profile']] ?? ($packageMap[strtolower($row['profile'])] ?? null);

                    if ($secret) {
                        if (! $updateExisting) {
                            $skipped++;

                            continue;
                        }

                        $passwordToStore = $this->passwordForStore($secret, $row['password']);
                        $secret->password = $passwordToStore;
                        $secret->service = $row['service'] ?: $secret->service;
                        $secret->profile = $row['profile'] ?: $secret->profile;
                        $secret->caller_id = $row['caller_id'];
                        $secret->comment = $row['comment'];
                        $secret->ppp_remote_ip = $row['remote_address'];
                        $secret->status = $status;
                        $secret->save();

                        $customer = CustomersInfo::where('ppp_user_id', $secret->id)->first();
                        if ($customer) {
                            $attrs = [];
                            if (in_array($customer->status, ['active', 'disable', 'pending'], true)) {
                                $attrs['status'] = $status === 'disable' ? 'disable' : 'active';
                            }
                            if ($packageId && ! $customer->package_id) {
                                $attrs['package_id'] = $packageId;
                            }
                            if ($attrs !== []) {
                                $customer->update($attrs);
                            }
                            $updated++;
                        } elseif ($createMissing) {
                            // PPP secret exists (e.g. after customer purge) but no billing customer — create one.
                            $lastIdCount++;
                            $newId = match ($options['code_format'] ?? 'prefix_sequential') {
                                'secret_as_code' => $row['name'],
                                'numeric' => (string) ($lastIdCount),
                                default => $prefix.$lastIdCount,
                            };
                            if (CustomersInfo::where('customer_unique_id', $newId)->exists()) {
                                $lastIdCount++;
                                $newId = $prefix.$lastIdCount;
                            }

                            CustomersInfo::create([
                                'customer_unique_id' => $newId,
                                'ppp_user_id' => $secret->id,
                                'customer_name' => $row['comment'] !== '' ? $row['comment'] : $row['name'],
                                'status' => $status === 'disable' ? 'disable' : 'active',
                                'package_id' => $packageId,
                                'connection_date' => Carbon::now(),
                            ]);
                            BillingInfo::create([
                                'customer_bill_unique_id' => $newId,
                                'billing_type' => 'prepaid',
                                'auto_disable_date' => Carbon::now(),
                            ]);
                            OfficialInfo::create(['customer_office_unique_id' => $newId]);
                            $created++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        if (! $createMissing) {
                            $skipped++;

                            continue;
                        }

                        $secret = PPPSecrets::create([
                            'router_name' => $router->router_name,
                            'username' => $row['name'],
                            'password' => $row['password'],
                            'service' => $row['service'] ?: 'pppoe',
                            'profile' => $row['profile'] ?: '-',
                            'caller_id' => $row['caller_id'],
                            'comment' => $row['comment'],
                            'ppp_remote_ip' => $row['remote_address'],
                            'status' => $status,
                        ]);
                        $existingSecrets->put($lower, $secret);

                        $lastIdCount++;
                        $newId = match ($options['code_format'] ?? 'prefix_sequential') {
                            'secret_as_code' => $row['name'],
                            'numeric' => (string) ($lastIdCount),
                            default => $prefix.$lastIdCount,
                        };
                        // Avoid duplicate customer_unique_id when using secret_as_code
                        if (CustomersInfo::where('customer_unique_id', $newId)->exists()) {
                            $lastIdCount++;
                            $newId = $prefix.$lastIdCount;
                        }

                        CustomersInfo::create([
                            'customer_unique_id' => $newId,
                            'ppp_user_id' => $secret->id,
                            'customer_name' => $row['comment'] !== '' ? $row['comment'] : $row['name'],
                            'status' => $status === 'disable' ? 'disable' : 'active',
                            'package_id' => $packageId,
                            'connection_date' => Carbon::now(),
                        ]);
                        BillingInfo::create([
                            'customer_bill_unique_id' => $newId,
                            'billing_type' => 'prepaid',
                            'auto_disable_date' => Carbon::now(),
                        ]);
                        OfficialInfo::create(['customer_office_unique_id' => $newId]);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = $row['name'].' — '.$e->getMessage();
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * Import every PPP secret on the router (anetbd importFromRouter).
     *
     * @param  array{create_missing?: bool, update_existing?: bool}  $options
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importFromRouter(RouterList $router, array $options = []): array
    {
        $secrets = $this->listSecretsFromRouter($router);
        $names = array_column($secrets, 'name');

        return $this->importSelectedFromRouter($router, $names, $options, $secrets);
    }

    private function nextCustomerIdSeed(string $prefix): int
    {
        return app(\App\Services\Billing\CustomerIdAllocator::class)->highestNumber();
    }

    /**
     * @return array<string, int> profile/package name => package id
     */
    private function packagesByProfile(string $routerName): array
    {
        $map = [];
        PackageList::query()
            ->where(function ($q) use ($routerName) {
                $q->whereNull('router_name')
                    ->orWhere('router_name', '')
                    ->orWhere('router_name', $routerName);
            })
            ->get(['id', 'package'])
            ->each(function ($pkg) use (&$map) {
                $name = trim((string) $pkg->package);
                if ($name !== '') {
                    $map[$name] = (int) $pkg->id;
                    $map[strtolower($name)] = (int) $pkg->id;
                }
            });

        return $map;
    }

    private function passwordForStore(PPPSecrets $existing, string $rawPassword): string
    {
        if ($rawPassword === '') {
            return $existing->getRawOriginal('password');
        }

        try {
            if ($existing->password === $rawPassword) {
                return $existing->getRawOriginal('password');
            }
        } catch (\Throwable) {
            // fall through — store new password
        }

        return $rawPassword;
    }
}
