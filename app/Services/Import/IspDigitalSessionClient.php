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

    private function extractVerificationToken(string $html): string
    {
        if (preg_match('/name="__RequestVerificationToken" type="hidden" value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }

        throw new RuntimeException('CSRF token not found on ISP Digital login page.');
    }
}
