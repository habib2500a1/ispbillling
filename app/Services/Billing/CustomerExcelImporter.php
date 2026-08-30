<?php

namespace App\Services\Billing;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\OfficialInfo;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use App\Services\Saas\SaasContext;
use App\Services\Saas\SaasQuotaException;
use App\Services\Saas\SaasQuotaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;

final class CustomerExcelImporter
{
    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'customer_name',
            'mobile',
            'customer_id',
            'username',
            'password',
            'package',
            'monthly_rent',
            'billing_day',
            'expire_date',
            'address',
            'email',
            'alternative_mobile',
            'status',
            'due_amount',
            'router_name',
            'notes',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function demoRows(): array
    {
        return [
            [
                'customer_name' => 'Habib Demo',
                'mobile' => '01841558023',
                'customer_id' => '',
                'username' => 'habibdemo',
                'password' => 'Pass1234',
                'package' => '10Mbps',
                'monthly_rent' => 500,
                'billing_day' => 1,
                'expire_date' => now()->addMonth()->format('Y-m-d'),
                'address' => 'Dhaka',
                'email' => 'demo@example.com',
                'alternative_mobile' => '',
                'status' => 'active',
                'due_amount' => 0,
                'router_name' => '',
                'notes' => 'SAMPLE — delete this row before real upload',
            ],
            [
                'customer_name' => 'Second Client',
                'mobile' => '01700000011',
                'customer_id' => '',
                'username' => 'client2',
                'password' => 'Pass1234',
                'package' => '',
                'monthly_rent' => 800,
                'billing_day' => 15,
                'expire_date' => now()->addDays(20)->format('Y-m-d'),
                'address' => 'Chattogram',
                'email' => '',
                'alternative_mobile' => '',
                'status' => 'active',
                'due_amount' => 800,
                'router_name' => '',
                'notes' => 'SAMPLE — empty customer_id means auto ID',
            ],
        ];
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(string $path): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $allocator = app(CustomerIdAllocator::class);
        $seed = $allocator->highestNumber();
        $quota = app(SaasQuotaService::class);
        $operator = SaasContext::operator();
        $line = 1;

        $rows = SimpleExcelReader::create($path)->getRows();

        foreach ($rows as $row) {
            $line++;
            $norm = $this->normalize($row);
            $name = trim((string) ($this->first($norm, ['customername', 'name', 'clientname']) ?? ''));
            if ($name === '') {
                $stats['skipped']++;

                continue;
            }

            try {
                $result = DB::transaction(function () use ($norm, $name, $allocator, &$seed, $quota, $operator) {
                    return $this->upsertRow($norm, $name, $allocator, $seed, $quota, $operator);
                });
                $stats[$result]++;
            } catch (SaasQuotaException $e) {
                $stats['skipped']++;
                $stats['errors'][] = __('Row :n: :msg', ['n' => $line, 'msg' => $e->getMessage()]);

                break;
            } catch (\Throwable $e) {
                $stats['skipped']++;
                $stats['errors'][] = __('Row :n (:name): :msg', [
                    'n' => $line,
                    'name' => $name,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $norm
     */
    private function upsertRow(
        array $norm,
        string $name,
        CustomerIdAllocator $allocator,
        int &$seed,
        SaasQuotaService $quota,
        mixed $operator,
    ): string {
        $uid = trim((string) ($this->first($norm, ['customeruniqueid', 'customerid', 'uniqueid', 'clientid', 'cid', 'id']) ?? ''));
        $username = trim((string) ($this->first($norm, ['username', 'pppoeusername', 'pppoeuser', 'user']) ?? ''));
        $mobile = $this->normalizeMobile($this->first($norm, ['mobile', 'phone', 'mobileno', 'phoneno', 'contact', 'contactno']));

        $customer = null;
        if ($uid !== '') {
            $customer = CustomersInfo::query()->where('customer_unique_id', $uid)->first();
        }
        if (! $customer && $username !== '') {
            $secret = PPPSecrets::query()->where('username', $username)->first();
            if ($secret) {
                $customer = CustomersInfo::query()->where('ppp_user_id', $secret->id)->first();
            }
        }

        $packageName = trim((string) ($this->first($norm, ['package', 'packagename', 'profile']) ?? ''));
        $package = $packageName !== ''
            ? PackageList::query()->whereRaw('LOWER(package) = ?', [mb_strtolower($packageName)])->first()
            : null;

        $rent = $this->first($norm, ['monthlyrent', 'rent', 'bill', 'billamount']);
        $monthlyRent = $rent !== null && $rent !== ''
            ? (float) $rent
            : (float) ($package?->price ?? 0);

        $billingDay = max(1, min(28, (int) ($this->first($norm, ['billingday', 'billday']) ?: now()->day)));
        $expire = $this->parseDate($this->first($norm, ['expiredate', 'autodisabledate', 'disabledate']));
        $due = $this->first($norm, ['dueamount', 'due', 'previousdue']);
        $dueAmount = $due !== null && $due !== '' ? max(0, (float) $due) : 0;
        $status = strtolower(trim((string) ($this->first($norm, ['status']) ?? 'active')));
        if (! in_array($status, ['active', 'disable', 'disabled', 'pending'], true)) {
            $status = 'active';
        }
        if ($status === 'disabled') {
            $status = 'disable';
        }
        if (auth()->user()?->hasRole('Reseller')) {
            $status = 'pending';
        }

        $router = trim((string) ($this->first($norm, ['routername', 'router']) ?? ''));
        if ($router === '') {
            $router = (string) (RouterList::query()->value('router_name') ?? '');
        }

        if ($customer) {
            $customer->fill([
                'customer_name' => $name,
                'mobile' => $mobile ?: $customer->mobile,
                'address' => $this->first($norm, ['address', 'customeraddress', 'location']) ?: $customer->address,
                'email' => $this->first($norm, ['email']) ?: $customer->email,
                'alternative_mobile' => $this->normalizeMobile($this->first($norm, ['alternativemobile', 'altmobile', 'alternativephone', 'altphone'])) ?: $customer->alternative_mobile,
                'status' => $status,
                'package_id' => $package?->id ?? $customer->package_id,
            ]);
            $customer->save();
            $this->syncBilling($customer, $monthlyRent, $billingDay, $expire, $dueAmount);
            $this->syncOfficial($customer, $this->first($norm, ['notes', 'note', 'comment']));
            $this->syncPpp($customer, $username, $norm, $package, $router);

            return 'updated';
        }

        if ($operator && ! SaasContext::isPlatformOwner()) {
            $quota->assert('customers', $operator);
        }

        $newId = $uid !== '' ? $uid : $allocator->next($seed);
        if (CustomersInfo::withoutGlobalScope('saas_tenant')->where('customer_unique_id', $newId)->exists()) {
            throw new \RuntimeException(__('Customer ID :id already exists.', ['id' => $newId]));
        }

        $customer = new CustomersInfo;
        if (\Illuminate\Support\Facades\Schema::hasColumn('customers_infos', 'saas_operator_id')) {
            $customer->saas_operator_id = SaasContext::operatorId();
        }
        if (auth()->user()?->hasRole('Reseller') && auth()->user()->reseller) {
            $customer->reseller_id = auth()->user()->reseller->id;
        }
        $customer->fill([
            'customer_unique_id' => $newId,
            'customer_name' => $name,
            'mobile' => $mobile,
            'address' => $this->first($norm, ['address', 'customeraddress', 'location']),
            'email' => $this->first($norm, ['email']),
            'alternative_mobile' => $this->normalizeMobile($this->first($norm, ['alternativemobile', 'altmobile', 'alternativephone', 'altphone'])),
            'status' => $status,
            'package_id' => $package?->id,
            'connection_date' => now()->toDateString(),
        ]);
        $customer->save();

        $this->syncBilling($customer, $monthlyRent, $billingDay, $expire, $dueAmount);
        $this->syncOfficial($customer, $this->first($norm, ['notes', 'note', 'comment']));
        $this->syncPpp($customer, $username, $norm, $package, $router);

        return 'created';
    }

    private function syncBilling(CustomersInfo $customer, float $monthlyRent, int $billingDay, ?Carbon $expire, float $dueAmount): void
    {
        $billing = BillingInfo::query()->where('customer_bill_unique_id', $customer->customer_unique_id)->first()
            ?: new BillingInfo(['customer_bill_unique_id' => $customer->customer_unique_id]);

        $billing->billing_type = $billing->billing_type ?: 'prepaid';
        $billing->monthly_rent = $monthlyRent;
        $billing->billing_day = $billingDay;
        $billing->auto_disable = true;
        $billing->auto_disable_month = $billing->auto_disable_month ?: 1;
        if ($expire) {
            $billing->auto_disable_date = $expire->toDateString();
        } elseif (! $billing->auto_disable_date) {
            $billing->auto_disable_date = now()->addMonthNoOverflow()->day(min($billingDay, 28))->toDateString();
        }
        $total = max(0, $monthlyRent);
        $billing->total_amount = $total;
        if ($dueAmount > 0 || ! $billing->exists) {
            $billing->due_amount = $dueAmount;
        }
        $billing->save();
    }

    private function syncOfficial(CustomersInfo $customer, mixed $note): void
    {
        $official = OfficialInfo::query()->where('customer_office_unique_id', $customer->customer_unique_id)->first()
            ?: new OfficialInfo(['customer_office_unique_id' => $customer->customer_unique_id]);
        $official->continue_bill = true;
        $official->bill_create = true;
        if ($note) {
            $official->note = (string) $note;
        }
        $official->save();
    }

    /**
     * @param  array<string, mixed>  $norm
     */
    private function syncPpp(CustomersInfo $customer, string $username, array $norm, ?PackageList $package, string $router): void
    {
        if ($username === '') {
            return;
        }

        $secret = PPPSecrets::query()->where('username', $username)->first();
        $linked = $secret
            ? CustomersInfo::query()->where('ppp_user_id', $secret->id)->where('id', '!=', $customer->id)->exists()
            : false;
        if ($linked) {
            throw new \RuntimeException(__('PPPoE username :user is already used.', ['user' => $username]));
        }

        if (! $secret) {
            $secret = new PPPSecrets;
            $secret->username = $username;
        }
        $password = trim((string) ($this->first($norm, ['password', 'pppoepassword', 'secret']) ?? ''));
        if ($password !== '') {
            $secret->password = $password;
        } elseif (! $secret->exists) {
            $secret->password = 'Pass'.random_int(1000, 9999);
        }
        $secret->router_name = $router ?: $secret->router_name;
        $secret->service = $secret->service ?: 'pppoe';
        $secret->profile = $package?->package ?: ($secret->profile ?: 'default');
        $secret->package_name = $package?->package ?: $secret->package_name;
        $secret->status = $secret->status ?: 'active';
        $secret->save();

        if ($customer->ppp_user_id !== $secret->id) {
            $customer->ppp_user_id = $secret->id;
            $customer->save();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $out = [];
        foreach ($row as $key => $val) {
            $out[strtolower(str_replace([' ', '_', '-'], '', (string) $key))] = is_string($val) ? trim($val) : $val;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $norm
     * @param  list<string>  $keys
     */
    private function first(array $norm, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $norm) && $norm[$key] !== null && $norm[$key] !== '') {
                return $norm[$key];
            }
        }

        return null;
    }

    private function normalizeMobile(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $raw) ?: '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '880') && strlen($digits) >= 13) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '88'.$digits;
        }

        return $digits;
    }

    private function parseDate(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
