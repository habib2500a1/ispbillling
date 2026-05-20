<?php

namespace App\Services\Mikrotik;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Support\BillingDefaults;
use App\Support\CustomerCodeGenerator;
use App\Support\CustomerPppLoginResolver;
use App\Support\CustomerStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class MikrotikPppImportService
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
    ) {}

    /**
     * Pull /ppp/secret from router → subscribers (create or update).
     *
     * @param  array{
     *   create_missing?: bool,
     *   update_existing?: bool,
     *   default_package_id?: ?int,
     *   default_area_id?: ?int,
     *   code_format?: ?string,
     * }  $options
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    /**
     * @return list<array{name: string, password: ?string, profile: ?string, disabled: bool, comment: ?string}>
     */
    public function listSecretsFromRouter(MikrotikServer $server): array
    {
        return $this->mikrotik->fetchPppSecrets($server);
    }

    /**
     * @param  list<string>  $secretNames  Empty = import none (caller should require selection)
     * @param  array<string, mixed>  $options
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    /**
     * @param  list<array{name: string, password: ?string, profile: ?string, disabled: bool, comment: ?string}>|null  $prefetchedSecrets
     */
    public function importSelectedFromRouter(MikrotikServer $server, array $secretNames, array $options = [], ?array $prefetchedSecrets = null): array
    {
        $secretNames = array_values(array_filter(array_map('trim', $secretNames)));
        if ($secretNames === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['No users selected.']];
        }

        $all = collect($prefetchedSecrets ?? $this->mikrotik->fetchPppSecrets($server))->keyBy('name');
        $rows = [];
        foreach ($secretNames as $name) {
            $secret = $all->get($name);
            if ($secret === null) {
                continue;
            }
            $rows[] = [
                'secret_name' => $secret['name'],
                'password' => $secret['password'],
                'profile' => $secret['profile'],
                'disabled' => $secret['disabled'],
                'comment' => $secret['comment'],
                'name' => $secret['comment'] ?: $secret['name'],
                'phone' => null,
                'customer_code' => null,
            ];
        }

        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Selected users not found on router.']];
        }

        $options['import_source'] = $options['import_source'] ?? 'mikrotik';
        $options['_packages_by_profile'] = Package::withoutGlobalScopes()
            ->where('tenant_id', (int) $server->tenant_id)
            ->where('mikrotik_server_id', $server->id)
            ->whereNotNull('mikrotik_profile_name')
            ->pluck('id', 'mikrotik_profile_name')
            ->all();

        return $this->importRows(
            (int) $server->tenant_id,
            $server,
            $rows,
            $options['create_missing'] ?? true,
            $options['update_existing'] ?? true,
            $options,
        );
    }

    /**
     * @deprecated Use importSelectedFromRouter — kept for CLI; imports ALL secrets when no filter passed.
     *
     * @param  array<string, mixed>  $options
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importFromRouter(MikrotikServer $server, array $options = []): array
    {
        $secrets = $this->mikrotik->fetchPppSecrets($server);
        $names = array_column($secrets, 'name');

        return $this->importSelectedFromRouter($server, $names, $options, $secrets);
    }

    /**
     * Remove subscribers that were imported from MikroTik (this server or all).
     *
     * @return array{deleted: int}
     */
    public function purgeMikrotikImported(int $tenantId, ?int $mikrotikServerId = null): array
    {
        $query = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('import_source', 'mikrotik');

        if ($mikrotikServerId !== null) {
            $query->where('mikrotik_server_id', $mikrotikServerId);
        }

        $deleted = 0;
        foreach ($query->cursor() as $customer) {
            $customer->delete();
            $deleted++;
        }

        return ['deleted' => $deleted];
    }

    /**
     * Import CSV or Excel (.xlsx, .xls).
     *
     * Expected columns (flexible headers): secret_name|username|ppp|login, password, profile, name, phone, customer_code, disabled
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function sampleSpreadsheetFilename(): string
    {
        return MikrotikPppImportSampleBuilder::FILENAME;
    }

    public function sampleSpreadsheetBinary(): string
    {
        return (new MikrotikPppImportSampleBuilder)->buildBinary();
    }

    public function importFromFile(MikrotikServer $server, UploadedFile $file, array $options = []): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Could not read upload.']];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $rows = match ($extension) {
            'csv', 'txt' => $this->parseCsv($path),
            'xlsx', 'xls' => $this->parseSpreadsheet($path),
            default => [],
        };

        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Unsupported or empty file. Use CSV or Excel.']];
        }

        return $this->importRows(
            (int) $server->tenant_id,
            $server,
            $rows,
            $options['create_missing'] ?? true,
            $options['update_existing'] ?? true,
            array_merge($options, ['import_source' => 'excel']),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $options
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    private function importRows(
        int $tenantId,
        MikrotikServer $server,
        array $rows,
        bool $createMissing,
        bool $updateExisting,
        array $options,
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $customerBySecret = $this->preloadCustomersBySecret($tenantId, (int) $server->id);
        $options['_packages_by_profile'] = $options['_packages_by_profile'] ?? Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('mikrotik_server_id', $server->id)
            ->whereNotNull('mikrotik_profile_name')
            ->pluck('id', 'mikrotik_profile_name')
            ->all();

        foreach ($rows as $index => $row) {
            $secretName = trim((string) ($row['secret_name'] ?? $row['username'] ?? ''));
            if ($secretName === '') {
                $skipped++;

                continue;
            }

            try {
                $customer = $this->findCustomerFromPreload($customerBySecret, (int) $server->id, $secretName, $row['customer_code'] ?? null);

                if ($customer === null && ! $createMissing) {
                    $skipped++;

                    continue;
                }

                if ($customer === null) {
                    $customer = $this->createCustomerFromRow($tenantId, $server, $secretName, $row, $options);
                    $key = strtolower(trim($secretName));
                    $customerBySecret[CustomerPppLoginResolver::serverScopedKey((int) $server->id, $key)] = $customer;
                    $customerBySecret[$key] = $customer;
                    $created++;
                } elseif ($updateExisting) {
                    if (config('sync.import_skip_unchanged', true) && $this->rowMatchesCustomer($customer, $server, $secretName, $row)) {
                        $skipped++;

                        continue;
                    }
                    $this->updateCustomerFromRow($customer, $server, $secretName, $row, $options);
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = ($index + 1).": {$secretName} — ".$e->getMessage();
            }
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * @return array<string, Customer>
     */
    private function preloadCustomersBySecret(int $tenantId, int $mikrotikServerId): array
    {
        $map = [];
        Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->select(['id', 'tenant_id', 'customer_code', 'mikrotik_secret_name', 'radius_username', 'mikrotik_server_id', 'mikrotik_ppp_password', 'status', 'network_access_state', 'package_id', 'import_source'])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$map, $mikrotikServerId): void {
                foreach ($customers as $customer) {
                    foreach (CustomerPppLoginResolver::loginKeysForCustomer($customer) as $key) {
                        $homeServer = (int) ($customer->mikrotik_server_id ?? 0);
                        if ($homeServer === $mikrotikServerId) {
                            $map[CustomerPppLoginResolver::serverScopedKey($mikrotikServerId, $key)] = $customer;
                        }
                        if ($homeServer <= 0 && ! isset($map[$key])) {
                            $map[$key] = $customer;
                        }
                    }
                }
            });

        return $map;
    }

    /**
     * @param  array<string, Customer>  $preload
     */
    private function findCustomerFromPreload(array $preload, int $mikrotikServerId, string $secretName, mixed $explicitCode): ?Customer
    {
        $key = strtolower(trim($secretName));
        $scoped = CustomerPppLoginResolver::serverScopedKey($mikrotikServerId, $key);
        if (isset($preload[$scoped])) {
            return $preload[$scoped];
        }

        if (isset($preload[$key])) {
            $candidate = $preload[$key];
            $homeServer = (int) ($candidate->mikrotik_server_id ?? 0);
            if ($homeServer <= 0 || $homeServer === $mikrotikServerId) {
                return $candidate;
            }
        }

        if (filled($explicitCode)) {
            $code = strtolower(trim((string) $explicitCode));

            return $preload[$code] ?? null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowMatchesCustomer(Customer $customer, MikrotikServer $server, string $secretName, array $row): bool
    {
        if ((int) $customer->mikrotik_server_id !== (int) $server->id) {
            return false;
        }

        if (strtolower((string) $customer->mikrotik_secret_name) !== strtolower(trim($secretName))) {
            return false;
        }

        if (filled($row['password'] ?? null) && filled($customer->mikrotik_ppp_password)) {
            try {
                if ((string) $customer->mikrotik_ppp_password !== (string) $row['password']) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return $this->rowNetworkState($row) === ($customer->network_access_state ?? 'active');
    }

    private function findCustomer(int $tenantId, string $secretName, mixed $explicitCode): ?Customer
    {
        $normalized = strtolower(trim($secretName));

        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($secretName, $normalized, $explicitCode): void {
                $q->where('mikrotik_secret_name', $secretName)
                    ->orWhereRaw('LOWER(mikrotik_secret_name) = ?', [$normalized])
                    ->orWhere('customer_code', $secretName)
                    ->orWhereRaw('LOWER(customer_code) = ?', [$normalized])
                    ->orWhere('radius_username', $secretName)
                    ->orWhereRaw('LOWER(radius_username) = ?', [$normalized]);
                if (filled($explicitCode)) {
                    $q->orWhere('customer_code', trim((string) $explicitCode));
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $options
     */
    private function createCustomerFromRow(int $tenantId, MikrotikServer $server, string $secretName, array $row, array $options): Customer
    {
        $code = $this->resolveCustomerCode($tenantId, $secretName, $row['customer_code'] ?? null, $options);
        $displayName = trim((string) ($row['name'] ?? $secretName));
        $phone = $this->normalizePhone($row['phone'] ?? null) ?: '0000000000';

        return DB::transaction(function () use ($tenantId, $server, $secretName, $row, $code, $displayName, $phone, $options): Customer {
            $customer = Customer::createTrusted([
                'tenant_id' => $tenantId,
                'customer_code' => $code,
                'name' => $displayName !== '' ? $displayName : $secretName,
                'phone' => $phone,
                'status' => $this->rowStatus($row),
                'network_access_state' => $this->rowNetworkState($row),
                'mikrotik_secret_name' => $secretName,
                'mikrotik_server_id' => $server->id,
                'radius_username' => $this->resolveRadiusUsername($secretName),
                'mikrotik_ppp_password' => filled($row['password'] ?? null) ? (string) $row['password'] : null,
                'package_id' => $this->resolvePackageId($tenantId, $server, $row, $options),
                'area_id' => $options['default_area_id'] ?? null,
                'joined_at' => now()->toDateString(),
                'billing_day' => BillingDefaults::billingDay(),
                'mikrotik_synced_at' => now(),
                'import_source' => (string) ($options['import_source'] ?? 'mikrotik'),
            ]);

            return $customer;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $options
     */
    private function updateCustomerFromRow(Customer $customer, MikrotikServer $server, string $secretName, array $row, array $options): void
    {
        $attrs = [
            'mikrotik_secret_name' => $secretName,
            'mikrotik_server_id' => $server->id,
            'mikrotik_synced_at' => now(),
            'import_source' => (string) ($options['import_source'] ?? $customer->import_source ?? 'mikrotik'),
        ];

        if (config('subscriber.import_set_radius_username', true)) {
            $attrs['radius_username'] = $this->resolveRadiusUsername($secretName);
        }

        if (filled($row['password'] ?? null)) {
            $attrs['mikrotik_ppp_password'] = (string) $row['password'];
        }

        $packageId = $this->resolvePackageId((int) $customer->tenant_id, $server, $row, $options);
        if ($packageId !== null && $customer->package_id === null) {
            $attrs['package_id'] = $packageId;
        }

        if ($customer->status === CustomerStatus::ACTIVE || $customer->status === CustomerStatus::SUSPENDED) {
            $attrs['network_access_state'] = $this->rowNetworkState($row);
        }

        $customer->forceFill($attrs)->save();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $options
     */
    private function resolveCustomerCode(int $tenantId, string $secretName, mixed $explicit, array $options): string
    {
        if (filled($explicit)) {
            $code = CustomerCodeGenerator::sanitizeCode((string) $explicit);
            if (! CustomerCodeGenerator::isValidManualCode($code)) {
                throw new \InvalidArgumentException("Invalid subscriber code: {$code}");
            }
            if (Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('customer_code', $code)->exists()) {
                throw new \InvalidArgumentException("Subscriber code already exists: {$code}");
            }

            return $code;
        }

        $format = $options['code_format'] ?? config('subscriber.code_format', 'prefixed_monthly');
        $previous = config('subscriber.code_format');
        config(['subscriber.code_format' => $format]);
        try {
            return CustomerCodeGenerator::generate($tenantId, $secretName);
        } finally {
            config(['subscriber.code_format' => $previous]);
        }
    }

    private function resolveRadiusUsername(string $secretName): string
    {
        return config('subscriber.import_set_radius_username', true) ? $secretName : '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $options
     */
    private function resolvePackageId(int $tenantId, MikrotikServer $server, array $row, array $options): ?int
    {
        if (isset($options['default_package_id']) && $options['default_package_id']) {
            return (int) $options['default_package_id'];
        }

        $profile = trim((string) ($row['profile'] ?? ''));
        if ($profile === '') {
            return null;
        }

        $cached = $options['_packages_by_profile'][$profile] ?? null;
        if ($cached !== null) {
            return (int) $cached;
        }

        $pkg = Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('mikrotik_server_id', $server->id)
            ->where('mikrotik_profile_name', $profile)
            ->first();

        return $pkg?->id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowNetworkState(array $row): string
    {
        $disabled = $row['disabled'] ?? false;
        if (is_string($disabled)) {
            $disabled = in_array(strtolower($disabled), ['1', 'yes', 'true', 'disabled'], true);
        }

        return $disabled ? 'suspended' : 'active';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowStatus(array $row): string
    {
        return $this->rowNetworkState($row) === 'suspended'
            ? CustomerStatus::SUSPENDED
            : CustomerStatus::ACTIVE;
    }

    private function normalizePhone(mixed $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = null;
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = $this->normalizeHeader($line);
                if ($this->rowLooksLikeData($header)) {
                    $rows[] = $this->mapRow($header, $line);
                    $header = $this->defaultHeaderKeys(count($line));
                }

                continue;
            }
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }
            $rows[] = $this->mapRow($header, $line);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray();
        if ($matrix === []) {
            return [];
        }

        $header = $this->normalizeHeader((array) array_shift($matrix));
        if ($this->rowLooksLikeData($header)) {
            $header = $this->defaultHeaderKeys(count($matrix[0] ?? []));
            array_unshift($matrix, array_keys($header));
        }

        $rows = [];
        foreach ($matrix as $line) {
            if (! is_array($line) || $this->isEmptyLine($line)) {
                continue;
            }
            $rows[] = $this->mapRow($header, $line);
        }

        return $rows;
    }

    /**
     * @param  list<string|null>  $cells
     * @return array<string, int>
     */
    private function normalizeHeader(array $cells): array
    {
        $map = [];
        foreach ($cells as $i => $cell) {
            $key = $this->headerToKey((string) ($cell ?? ''));
            if ($key !== '') {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    private function headerToKey(string $label): string
    {
        $label = strtolower(trim($label));

        return match (true) {
            in_array($label, ['secret', 'secret_name', 'username', 'user', 'login', 'ppp', 'ppp_user', 'subscriber'], true) => 'secret_name',
            $label === 'password' || $label === 'pass' => 'password',
            $label === 'profile' || $label === 'ppp_profile' => 'profile',
            $label === 'phone' || $label === 'mobile' => 'phone',
            $label === 'customer_code' || $label === 'code' || $label === 'subscriber_id' => 'customer_code',
            in_array($label, ['name', 'full_name', 'customer_name', 'display_name'], true) => 'name',
            $label === 'disabled' || $label === 'status' => 'disabled',
            $label === 'comment' => 'comment',
            default => '',
        };
    }

    /**
     * @param  array<string, int>  $header
     * @param  list<mixed>  $line
     * @return array<string, mixed>
     */
    private function mapRow(array $header, array $line): array
    {
        $row = [];
        foreach ($header as $key => $index) {
            $row[$key] = $line[$index] ?? null;
        }
        if (isset($row['comment']) && empty($row['name'])) {
            $row['name'] = $row['comment'];
        }

        return $row;
    }

    /**
     * @param  array<string, int>  $header
     */
    private function rowLooksLikeData(array $header): bool
    {
        return isset($header['secret_name']) && ! isset($header['password']) && count($header) <= 2;
    }

    /**
     * @return array<string, int>
     */
    private function defaultHeaderKeys(int $cols): array
    {
        $keys = ['secret_name', 'password', 'profile', 'name', 'phone', 'customer_code', 'disabled'];
        $map = [];
        foreach ($keys as $i => $key) {
            if ($i < $cols) {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $line
     */
    private function isEmptyLine(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
