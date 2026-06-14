<?php

namespace App\Services\Import;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Support\BillingPortalLabel;
use RuntimeException;

/**
 * Authenticated HTTP session for the legacy online billing portal (pay.anetbd.com).
 */
final class LegacyPortalSessionClient
{
    private CookieJar $jar;

    private PendingRequest $http;

    private bool $loggedIn = false;

    private bool $billingIndexPrimed = false;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {
        $this->jar = new CookieJar;
        $this->http = $this->buildHttpClient();
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
            throw new RuntimeException(BillingPortalLabel::name().' login failed: '.($check->json('MSG') ?: 'invalid credentials'));
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
            throw new RuntimeException(BillingPortalLabel::name().' session cookie not set after login.');
        }

        $this->loggedIn = true;
    }

    public function isLoggedIn(): bool
    {
        return $this->loggedIn;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function credentials(): array
    {
        return [
            'url' => $this->baseUrl,
            'user' => $this->username,
            'password' => $this->password,
        ];
    }

    public static function authenticated(string $baseUrl, string $username, string $password): self
    {
        $client = new self($baseUrl, $username, $password);
        $client->login();

        return $client;
    }

    /**
     * Drop accumulated cookies (prevents HTTP 431) and rebuild the HTTP client.
     */
    public function resetSession(): void
    {
        $this->jar = new CookieJar;
        $this->http = $this->buildHttpClient();
        $this->loggedIn = false;
        $this->billingIndexPrimed = false;
    }

    public function primeBillingIndex(): void
    {
        if ($this->billingIndexPrimed) {
            return;
        }

        $this->http->get($this->baseUrl.'/Billing/Index');

        try {
            $this->fetchBillingListOtherData();
        } catch (\Throwable) {
            // KPI warm-up is best-effort.
        }

        $this->billingIndexPrimed = true;
    }

    /**
     * @return array{data: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchPaymentHistoryPage(int $customerHeaderId, int $start = 0, int $length = 200): array
    {
        $customerHeaderId = max(1, $customerHeaderId);

        try {
            $this->fetchCustomerDetailsHtml($customerHeaderId);
        } catch (\Throwable) {
            // Details warm-up is best-effort; Ajax may still work.
        }

        $payload = [
            'draw' => '1',
            'start' => (string) $start,
            'length' => (string) $length,
            'search[value]' => '',
            'search[regex]' => 'false',
        ];

        $response = $this->postAjax(
            $this->baseUrl.'/Customer/AjaxReceivedHistory/'.$customerHeaderId,
            $payload,
        );

        if (! $response->successful() && in_array($response->status(), [400, 431], true)) {
            $this->resetSession();
            $this->login();
            $this->fetchCustomerDetailsHtml($customerHeaderId);
            $response = $this->postAjax(
                $this->baseUrl.'/Customer/AjaxReceivedHistory/'.$customerHeaderId,
                $payload,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException('AjaxReceivedHistory failed: HTTP '.$response->status());
        }

        /** @var array{data?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();
        $data = $json['data'] ?? [];

        return [
            'data' => $data,
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? count($data)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchPaymentHistory(int $customerHeaderId, int $start = 0, int $length = 500): array
    {
        return $this->fetchPaymentHistoryPage($customerHeaderId, $start, $length)['data'];
    }

    /**
     * Per-customer service/product invoices (customer details → Invoices tab).
     *
     * @return array{aaData: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchCustomerProductInvoicesPage(int $customerHeaderId, int $start = 0, int $length = 100): array
    {
        $response = $this->http->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/Customer/Details/'.$customerHeaderId,
            ])
            ->post($this->baseUrl.'/Customer/AjaxServiceAndProductInvoices', [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
                'customerHeadId' => (string) $customerHeaderId,
            ]);

        if (! $response->successful()) {
            return ['aaData' => [], 'iTotalDisplayRecords' => 0];
        }

        /** @var array{aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();

        return [
            'aaData' => $json['aaData'] ?? [],
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? 0),
        ];
    }

    /**
     * HR employees (Employee → AjaxEmployees).
     *
     * @return array{aaData: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchEmployeesPage(int $start = 0, int $length = 500): array
    {
        $response = $this->http->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/Employee/Index',
            ])
            ->post($this->baseUrl.'/Employee/AjaxEmployees', [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AjaxEmployees failed: HTTP '.$response->status());
        }

        /** @var array{aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();

        return [
            'aaData' => $json['aaData'] ?? [],
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? 0),
        ];
    }

    /**
     * Customer details → Messages / SMS history tab.
     *
     * @return array{aaData: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchCustomerMessagesHistoryPage(int $customerHeaderId, int $start = 0, int $length = 100): array
    {
        $response = $this->http->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/Customer/Details/'.$customerHeaderId,
            ])
            ->post($this->baseUrl.'/Customer/AjaxMessagesHistory/'.$customerHeaderId, [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
                'customerHeadId' => (string) $customerHeaderId,
            ]);

        if (! $response->successful()) {
            return ['aaData' => [], 'iTotalDisplayRecords' => 0];
        }

        /** @var array{aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();

        return [
            'aaData' => $json['aaData'] ?? [],
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? 0),
        ];
    }

    /**
     * Application login users (collectors / staff with panel access).
     *
     * @return array{aaData: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchApplicationUsersPage(int $start = 0, int $length = 100): array
    {
        $response = $this->http->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->baseUrl.'/ApplicationUsers/Index',
            ])
            ->post($this->baseUrl.'/ApplicationUsers/AjaxApplicationUsers', [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AjaxApplicationUsers failed: HTTP '.$response->status());
        }

        /** @var array{aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();

        return [
            'aaData' => $json['aaData'] ?? [],
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? 0),
        ];
    }

    /**
     * MAC / bandwidth resellers list.
     *
     * @return array{data: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchMacResellersPage(int $start = 0, int $length = 100): array
    {
        $response = $this->postAjax(
            $this->baseUrl.'/MACReseller/AjaxMACResellers',
            [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
            ],
        );

        if (! $response->successful()) {
            throw new RuntimeException('AjaxMACResellers failed: HTTP '.$response->status());
        }

        /** @var array{data?: list<array<string, mixed>>, aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();
        $data = $json['data'] ?? $json['aaData'] ?? [];

        return [
            'data' => $data,
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? count($data)),
        ];
    }

    /**
     * Clients assigned to a MAC reseller.
     *
     * @return array{data: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchMacResellerClientsPage(int $macResellerId, int $start = 0, int $length = 200): array
    {
        $response = $this->postAjax(
            $this->baseUrl.'/MACReseller/AjaxMACResellerClientsByResellerID',
            [
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
                'id' => (string) $macResellerId,
            ],
        );

        if (! $response->successful()) {
            return ['data' => [], 'iTotalDisplayRecords' => 0];
        }

        /** @var array{data?: list<array<string, mixed>>, aaData?: list<array<string, mixed>>, iTotalDisplayRecords?: int} $json */
        $json = $response->json();
        $data = $json['data'] ?? $json['aaData'] ?? [];

        return [
            'data' => $data,
            'iTotalDisplayRecords' => (int) ($json['iTotalDisplayRecords'] ?? count($data)),
        ];
    }

    /**
     * MAC reseller wholesale / selling tariff table from Details page (TariffPackages JS).
     *
     * @return list<array<string, mixed>>
     */
    public function fetchMacResellerTariffPackages(int $macResellerId): array
    {
        $response = $this->fetchRawGet('/MACReseller/Details/'.max(1, $macResellerId));
        $html = (string) ($response['body'] ?? '');

        if (! preg_match('/TariffPackages\s*=\s*(\[.+?\]);/s', $html, $matches)) {
            return [];
        }

        /** @var list<array<string, mixed>>|null $decoded */
        $decoded = json_decode($matches[1], true);

        return is_array($decoded) ? $decoded : [];
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
     * Current-month billing grid (matches legacy portal dashboard totals).
     *
     * @return array{aaData: list<array<string, mixed>>, iTotalDisplayRecords: int}
     */
    public function fetchCustomerBillListPage(int $start = 0, int $length = 200): array
    {
        $this->primeBillingIndex();

        $payload = array_merge($this->billingGridPayload($start, $length), $this->billingGridFilters());

        $response = $this->postAjax(
            $this->baseUrl.'/Billing/AjaxCustomerBillList',
            $payload,
        );

        if (! $response->successful() && in_array($response->status(), [400, 431], true)) {
            $this->resetSession();
            $this->login();
            $this->primeBillingIndex();
            $response = $this->postAjax(
                $this->baseUrl.'/Billing/AjaxCustomerBillList',
                $payload,
            );
        }

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
     * Dashboard KPIs from legacy portal billing page (Monthly bill, collected, due).
     *
     * @return array{monthly_bill: float, collected_bill: float, due: float, discount: float, monthly_generated_bill: float, total_advanced: float, total_active: int, total_paid_clients: int, total_unpaid_clients: int}
     */
    public function fetchBillingListOtherData(): array
    {
        $response = $this->http
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
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

    /**
     * Generic read-only probe for legacy pages/endpoints that do not have a typed importer yet.
     *
     * @param  array<string, mixed>  $query
     * @return array{status: int, content_type: string|null, body: string, json: mixed}
     */
    public function fetchRawGet(string $path, array $query = [], ?string $referer = null): array
    {
        $response = $this->http
            ->withHeaders(array_filter([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $referer ?: $this->baseUrl.'/',
            ]))
            ->get($this->baseUrl.'/'.ltrim($path, '/'), $query);

        return $this->rawResponse($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, content_type: string|null, body: string, json: mixed}
     */
    public function fetchRawPost(string $path, array $payload = [], ?string $referer = null): array
    {
        $response = $this->http->asForm()
            ->withHeaders(array_filter([
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $referer ?: $this->baseUrl.'/',
            ]))
            ->post($this->baseUrl.'/'.ltrim($path, '/'), $payload);

        return $this->rawResponse($response);
    }

    /**
     * @return array{status: int, content_type: string|null, body: string, json: mixed}
     */
    private function rawResponse(Response $response): array
    {
        $body = (string) $response->body();
        $json = null;
        try {
            $json = $response->json();
        } catch (\Throwable) {
            $json = null;
        }

        return [
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'body' => $body,
            'json' => $json,
        ];
    }

    private function extractVerificationToken(string $html): string
    {
        if (preg_match('/name="__RequestVerificationToken" type="hidden" value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }

        throw new RuntimeException('CSRF token not found on '.BillingPortalLabel::name().' login page.');
    }

    private function buildHttpClient(): PendingRequest
    {
        return Http::withOptions([
            'cookies' => $this->jar,
            'allow_redirects' => true,
            'timeout' => 120,
        ])->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/json,*/*',
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function postAjax(string $url, array $payload, ?string $referer = null): Response
    {
        $request = $this->buildHttpClient()->asForm()->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        if ($referer !== null && $referer !== '') {
            $request = $request->withHeaders(['Referer' => $referer]);
        }

        return $request->post($url, $payload);
    }

    /**
     * @return array<string, string>
     */
    private function billingGridPayload(int $start, int $length): array
    {
        return [
            'draw' => '1',
            'start' => (string) $start,
            'length' => (string) $length,
            'search[value]' => '',
            'search[regex]' => 'false',
        ];
    }

    /**
     * Mirrors Billing/Index DataTables ajax "data" callback defaults.
     *
     * @return array<string, string>
     */
    private function billingGridFilters(): array
    {
        return [
            'zoneId' => '',
            'packageId' => '',
            'connectionType' => '',
            'paymentStatus' => '',
            'mikrotikStatus' => '',
            'receivedBy' => '',
            'billPeriodId' => '',
            'protocolId' => '',
            'createdId' => '',
            'ServerId' => '',
            'fromBillDate' => '',
            'toBillDate' => '',
            'subZoneId' => '',
            'boxId' => '',
            'customerType' => '',
            'customerStatus' => '',
            'fromDate' => '',
            'toDate' => '',
            'orderBy' => '',
            'fromEffectiveTo' => '',
            'toEffectiveTo' => '',
            'customQueryString' => '',
            'assignedCustomersForEmp' => '',
            'customStatus' => '',
            'fromNonEffectiveTo' => '',
            'toNonEffectiveTo' => '',
            'profile' => '',
            'permissionId' => '1',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dataTablesPayload(int $start, int $length): array
    {
        return [
            'draw' => '1',
            'start' => (string) $start,
            'length' => (string) $length,
            'search[value]' => '',
            'search[regex]' => 'false',
            'order[0][column]' => '0',
            'order[0][dir]' => 'asc',
            'columns[0][data]' => '0',
            'columns[0][name]' => '',
            'columns[0][searchable]' => 'true',
            'columns[0][orderable]' => 'true',
            'columns[0][search][value]' => '',
            'columns[0][search][regex]' => 'false',
        ];
    }
}
