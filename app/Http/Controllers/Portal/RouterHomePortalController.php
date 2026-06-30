<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Mobile\MobileAiService;
use App\Services\Portal\RouterHomePortalService;
use App\Support\PublicTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RouterHomePortalController extends Controller
{
    public function index(Request $request, RouterHomePortalService $portal): View|Response
    {
        abort_unless($portal->enabled(), 404);

        $customer = $this->resolveCustomer($request, $portal);
        $payload = $customer !== null ? $portal->dashboardPayload($customer) : null;

        return response()
            ->view('portal.router-home', [
                'customer' => $customer,
                'dashboard' => $payload,
                'routerUrl' => $portal->portalUrl(),
                'identifiedBy' => $customer !== null ? session('router_home_identify', 'ip') : null,
            ])
            ->header('Content-Security-Policy', "frame-ancestors 'self' *");
    }

    public function identify(Request $request, RouterHomePortalService $portal): View|Response
    {
        abort_unless($portal->enabled(), 404);

        $data = $request->validate([
            'customer_code' => ['required', 'string', 'max:32'],
            'phone_tail' => ['required', 'string', 'max:8'],
        ]);

        $customer = $portal->identifyByCodeAndPhone(
            (string) $data['customer_code'],
            (string) $data['phone_tail'],
            PublicTenantContext::tenantId(),
        );

        if ($customer === null) {
            return response()
                ->view('portal.router-home', [
                    'customer' => null,
                    'dashboard' => null,
                    'routerUrl' => $portal->portalUrl(),
                    'identifiedBy' => null,
                    'identifyError' => 'Customer code বা phone match হয়নি।',
                ])
                ->header('Content-Security-Policy', "frame-ancestors 'self' *");
        }

        session(['router_home_customer_id' => $customer->id, 'router_home_identify' => 'form']);

        return $this->index($request, $portal);
    }

    public function ask(Request $request, RouterHomePortalService $portal, MobileAiService $ai): JsonResponse
    {
        abort_unless($portal->enabled(), 404);

        $customer = $this->resolveCustomer($request, $portal);
        abort_unless($customer instanceof Customer, 403, 'Identify your line first.');

        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $result = $ai->reply($customer, (string) $data['question']);

        return response()->json([
            'reply' => $result['reply'] ?? '',
            'hints' => $result['hints'] ?? [],
        ]);
    }

    private function resolveCustomer(Request $request, RouterHomePortalService $portal): ?Customer
    {
        $sessionId = session('router_home_customer_id');
        if (is_numeric($sessionId)) {
            $fromSession = Customer::withoutGlobalScopes()
                ->where('tenant_id', PublicTenantContext::tenantId())
                ->whereKey((int) $sessionId)
                ->first();
            if ($fromSession !== null) {
                return $fromSession;
            }
        }

        $fromIp = $portal->identifyFromRequest($request);
        if ($fromIp !== null) {
            session(['router_home_customer_id' => $fromIp->id, 'router_home_identify' => 'ip']);

            return $fromIp;
        }

        return null;
    }
}
