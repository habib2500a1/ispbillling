<?php

namespace App\Services\Portal;

use App\Models\Customer;
use App\Models\PppSessionLog;
use App\Support\PublicTenantContext;
use Illuminate\Http\Request;

final class RouterHomePortalService
{
    public function enabled(): bool
    {
        return (bool) config('portal.router_home.enabled', true)
            && (bool) config('portal.enabled', true);
    }

    public function portalUrl(): string
    {
        return route('portal.router-home');
    }

    public function identifyFromRequest(Request $request, ?int $tenantId = null): ?Customer
    {
        $tenantId = $tenantId ?? PublicTenantContext::tenantId();

        foreach ($this->candidatePublicIps($request) as $ip) {
            $customer = $this->customerByActiveSessionIp($ip, $tenantId);
            if ($customer !== null) {
                return $customer;
            }
        }

        return null;
    }

    public function identifyByCodeAndPhone(string $customerCode, string $phoneTail, ?int $tenantId = null): ?Customer
    {
        $tenantId = $tenantId ?? PublicTenantContext::tenantId();
        $code = trim($customerCode);
        $tail = preg_replace('/\D+/', '', $phoneTail) ?? '';

        if ($code === '' || strlen($tail) < 4) {
            return null;
        }

        $tail = substr($tail, -4);

        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_code', $code)
            ->where('phone', 'like', '%'.$tail)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(Customer $customer): array
    {
        $customer->loadMissing('package');
        $live = app(CustomerBandwidthService::class)->liveStats($customer);
        $due = (float) $customer->openInvoiceBalance();

        return [
            'customer_code' => $customer->customer_code,
            'name' => $customer->name,
            'package' => $customer->package?->name,
            'due' => $due,
            'due_formatted' => number_format($due, 2).' BDT',
            'online' => (bool) ($live['online'] ?? false),
            'framed_ip' => $live['framed_ip'] ?? null,
            'download_human' => isset($live['download_mbps']) ? $live['download_mbps'].' Mbps' : null,
            'pay_url' => url('/pay?code='.urlencode((string) $customer->customer_code)),
            'portal_url' => route('portal.login'),
            'full_portal_url' => route('portal.dashboard'),
        ];
    }

    /**
     * @return list<string>
     */
    private function candidatePublicIps(Request $request): array
    {
        $ips = [];
        foreach ([$request->ip(), ...$this->forwardedIps($request)] as $ip) {
            $ip = trim((string) $ip);
            if ($ip !== '' && $this->isPublicIp($ip) && ! in_array($ip, $ips, true)) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }

    /**
     * @return list<string>
     */
    private function forwardedIps(Request $request): array
    {
        $raw = (string) $request->header('X-Forwarded-For', '');
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function customerByActiveSessionIp(string $ip, int $tenantId): ?Customer
    {
        $session = PppSessionLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('framed_ip', $ip)
            ->orderByDesc('started_at')
            ->first();

        if ($session === null) {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($session->customer_id)
            ->first();
    }
}
