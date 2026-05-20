<?php

namespace App\Services\Import;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Authenticated HTTP session for ISP Digital (pay.anetbd.com).
 */
final class IspDigitalSessionClient
{
    private CookieJar $jar;

    private PendingRequest $http;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {
        $this->jar = new CookieJar;
        $this->http = Http::withOptions([
            'cookies' => $this->jar,
            'allow_redirects' => true,
            'timeout' => 120,
        ])->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/json,*/*',
        ]);
    }

    public function login(): void
    {
        $loginHtml = $this->http->get($this->baseUrl.'/Account/Login')->body();
        $token = $this->extractVerificationToken($loginHtml);

        $check = $this->http->asForm()
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post($this->baseUrl.'/Account/UserCredentialsCheck', [
                'usrName' => $this->username,
                'usrPassword' => $this->password,
            ]);

        if (! $check->json('CHKStatus')) {
            throw new RuntimeException('ISP Digital login failed: '.($check->json('MSG') ?: 'invalid credentials'));
        }

        $body = http_build_query([
            '__RequestVerificationToken' => $token,
            'Username' => $this->username,
            'Password' => $this->password,
            'RememberMe' => 'false',
            'VmAuthTracer.IPAddress' => '127.0.0.1',
            'VmAuthTracer.CountryName' => 'Bangladesh',
        ]);

        Http::withOptions([
            'cookies' => $this->jar,
            'allow_redirects' => false,
            'timeout' => 120,
        ])->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ])->withBody($body, 'application/x-www-form-urlencoded')
            ->withHeaders([
                'Content-Length' => (string) strlen($body),
                'Referer' => $this->baseUrl.'/Account/Login',
            ])
            ->post($this->baseUrl.'/Account/LoginChecker');

        $hasSession = false;
        foreach ($this->jar->toArray() as $cookie) {
            if (in_array($cookie['Name'] ?? '', ['UserIdUserRoleAndUserName', 'ASP.NET_SessionId', '.ASPXAUTH'], true)) {
                $hasSession = true;
                break;
            }
        }

        if (! $hasSession) {
            throw new RuntimeException('ISP Digital session cookie not set after login.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchPaymentHistory(int $customerHeaderId, int $start = 0, int $length = 500): array
    {
        $response = $this->http->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/Customer/Details?id='.$customerHeaderId,
            ])
            ->post($this->baseUrl.'/Customer/AjaxReceivedHistory/'.$customerHeaderId, [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AjaxReceivedHistory failed: HTTP '.$response->status());
        }

        /** @var array{data?: list<array<string, mixed>>} $json */
        $json = $response->json();

        return $json['data'] ?? [];
    }

    /**
     * @return array{aaData: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchServiceInvoicePage(int $start = 0, int $length = 100): array
    {
        $response = $this->http->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/serviceinvoice/index',
            ])
            ->post($this->baseUrl.'/ServiceInvoice/AjaxInvoiceList', [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AjaxInvoiceList failed: HTTP '.$response->status());
        }

        /** @var array{aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();

        return [
            'aaData' => $json['aaData'] ?? [],
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? 0),
        ];
    }

    /**
     * Current-month billing grid (matches ISP Digital dashboard totals).
     *
     * @return array{aaData: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchCustomerBillListPage(int $start = 0, int $length = 200): array
    {
        $response = $this->http->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/Billing/Index',
            ])
            ->post($this->baseUrl.'/Billing/AjaxCustomerBillList', [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
                'search[value]' => '',
                'search[regex]' => 'false',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AjaxCustomerBillList failed: HTTP '.$response->status());
        }

        /** @var array{aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();

        return [
            'aaData' => $json['aaData'] ?? [],
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? 0),
        ];
    }

    /**
     * Dashboard KPIs from ISP Digital billing page (Monthly bill, collected, due).
     *
     * @return array{monthly_bill: float, collected_bill: float, due: float, discount: float, monthly_generated_bill: float, total_advanced: float, total_active: int, total_paid_clients: int, total_unpaid_clients: int}
     */
    public function fetchBillingListOtherData(): array
    {
        $response = $this->http
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/Billing/Index',
            ])
            ->get($this->baseUrl.'/Billing/GetBillingListOtherData');

        if (! $response->successful()) {
            throw new RuntimeException('GetBillingListOtherData failed: HTTP '.$response->status());
        }

        /** @var array<string, mixed> $raw */
        $raw = $response->json() ?? [];

        $monthlyBill = (float) ($raw['MonthlyBill'] ?? 0);
        $generated = (float) ($raw['MonthlyGeneratedBill'] ?? 0);

        return [
            'monthly_bill' => round($monthlyBill, 2),
            'collected_bill' => round((float) ($raw['PaidAmount'] ?? 0), 2),
            'due' => round((float) ($raw['DueAmount'] ?? 0), 2),
            'discount' => round(max(0, $generated - $monthlyBill), 2),
            'monthly_generated_bill' => round($generated, 2),
            'total_advanced' => round((float) ($raw['TotalAdvancedBill'] ?? 0), 2),
            'total_active' => (int) ($raw['TotalActiveClinetForBilling'] ?? 0),
            'total_paid_clients' => (int) ($raw['TotalPaidClient'] ?? 0),
            'total_unpaid_clients' => (int) ($raw['TotalUnpaidClient'] ?? 0),
        ];
    }

    /**
     * @return array{aaData: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchCustomerPage(int $start = 0, int $length = 10, string $query = 'alloverclients'): array
    {
        $response = $this->http->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/Customer/Index?query='.$query,
            ])
            ->post($this->baseUrl.'/Customer/AjaxCustomerList', [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
                'search[value]' => '',
                'search[regex]' => 'false',
                'customQueryString' => $query,
                'orderBy' => '',
                'permissionId' => '0',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AjaxCustomerList failed: HTTP '.$response->status());
        }

        /** @var array{aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();

        return [
            'aaData' => $json['aaData'] ?? [],
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? 0),
        ];
    }

    public function fetchCustomerDetailsHtml(int $customerHeaderId): string
    {
        $response = $this->http
            ->withHeaders([
                'Referer' => $this->baseUrl.'/Customer/Index',
            ])
            ->get($this->baseUrl.'/Customer/Details/'.$customerHeaderId);

        if (! $response->successful()) {
            $response = $this->http
                ->withHeaders(['Referer' => $this->baseUrl.'/Customer/Index'])
                ->get($this->baseUrl.'/Customer/Details', ['id' => $customerHeaderId]);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Customer details failed: HTTP '.$response->status());
        }

        return (string) $response->body();
    }

    private function extractVerificationToken(string $html): string
    {
        if (preg_match('/name="__RequestVerificationToken" type="hidden" value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }

        throw new RuntimeException('CSRF token not found on ISP Digital login page.');
    }
}
