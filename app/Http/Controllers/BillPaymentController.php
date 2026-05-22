<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\BillPayment\BillPaymentOtpService;
use App\Services\BillPayment\PaymentLinkService;
use App\Services\BillPayment\PublicBillPaymentService;
use App\Services\Payments\PublicPaymentOrchestrator;
use App\Support\PaymentGateway;
use App\Support\PortalPaymentGateways;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillPaymentController extends Controller
{
    private const SESSION_CUSTOMER = 'bill_pay.customer_id';

    private const SESSION_VERIFIED = 'bill_pay.verified';

    private const SESSION_LINK_AMOUNT = 'bill_pay.link_amount';

    private const SESSION_LINK_INVOICE = 'bill_pay.link_invoice_id';

    private const SESSION_LINK_ID = 'bill_pay.link_id';

    public function index(Request $request): View
    {
        $prefill = $request->query('code', $request->query('client'));

        return view('bill-payment.index', [
            'companyName' => config('isp.company_name'),
            'prefillCode' => is_string($prefill) ? $prefill : '',
            'notification' => $request->query('msg'),
            'otpEnabled' => app(BillPaymentOtpService::class)->isEnabled(),
        ]);
    }

    public function openLink(string $token, PaymentLinkService $links): RedirectResponse
    {
        $link = $links->resolve($token);
        if ($link === null) {
            return redirect()->route('bill-payment.index')
                ->with('msg', 'This payment link is invalid or has expired.');
        }

        session([
            self::SESSION_CUSTOMER => $link->customer_id,
            self::SESSION_VERIFIED => true,
            self::SESSION_LINK_ID => $link->id,
        ]);

        if ($link->amount !== null) {
            session([self::SESSION_LINK_AMOUNT => (float) $link->amount]);
        }
        if ($link->invoice_id) {
            session([self::SESSION_LINK_INVOICE => $link->invoice_id]);
        }

        if ($link->purpose === PaymentLink::PURPOSE_WALLET) {
            return redirect()->route('bill-payment.invoice', ['tab' => 'wallet']);
        }

        return redirect()->route('bill-payment.invoice');
    }

    public function lookup(Request $request, PublicBillPaymentService $service, BillPaymentOtpService $otp): RedirectResponse
    {
        $request->validate([
            'client_code' => ['required', 'string', 'max:64'],
        ]);

        $customer = $service->findByClientCode($request->string('client_code')->toString());
        if ($customer === null) {
            return back()->withInput()->withErrors([
                'client_code' => 'Client code not found. Check your bill or contact support.',
            ]);
        }

        $hasDue = $service->totalDue($customer) > 0;
        $canWallet = config('bill_payment.wallet_topup_enabled', true);

        if (! $hasDue && ! $canWallet) {
            return back()->withInput()->withErrors([
                'client_code' => 'No due bill found for this client code.',
            ]);
        }

        $request->session()->put(self::SESSION_CUSTOMER, $customer->id);
        $request->session()->forget([self::SESSION_VERIFIED, self::SESSION_LINK_AMOUNT, self::SESSION_LINK_INVOICE, self::SESSION_LINK_ID]);

        if ($otp->isEnabled() && filled($customer->phone)) {
            try {
                $otp->startChallenge($customer);
            } catch (\Throwable) {
                return back()->withInput()->withErrors([
                    'client_code' => 'Could not send verification code. Try again or contact support.',
                ]);
            }

            return redirect()->route('bill-payment.verify');
        }

        $request->session()->put(self::SESSION_VERIFIED, true);

        return redirect()->route('bill-payment.invoice');
    }

    public function verify(Request $request, BillPaymentOtpService $otp): View|RedirectResponse
    {
        $customer = $this->sessionCustomer($request);
        if ($customer === null) {
            return redirect()->route('bill-payment.index');
        }

        if ($request->session()->get(self::SESSION_VERIFIED)) {
            return redirect()->route('bill-payment.invoice');
        }

        if (! $otp->isEnabled()) {
            $request->session()->put(self::SESSION_VERIFIED, true);

            return redirect()->route('bill-payment.invoice');
        }

        return view('bill-payment.verify', [
            'companyName' => config('isp.company_name'),
            'maskedPhone' => $otp->maskPhone($customer->phone),
            'customerCode' => $customer->customer_code,
        ]);
    }

    public function verifySubmit(Request $request, BillPaymentOtpService $otp): RedirectResponse
    {
        $customer = $this->sessionCustomer($request);
        if ($customer === null) {
            return redirect()->route('bill-payment.index');
        }

        $request->validate([
            'verification_code' => ['required', 'string', 'max:16'],
        ]);

        if (! $otp->verify((int) $customer->id, $request->string('verification_code')->toString())) {
            return back()->withErrors([
                'verification_code' => 'Invalid or expired code.',
            ]);
        }

        $request->session()->put(self::SESSION_VERIFIED, true);

        return redirect()->route('bill-payment.invoice');
    }

    public function resendOtp(Request $request, BillPaymentOtpService $otp): RedirectResponse
    {
        $customer = $this->sessionCustomer($request);
        if ($customer === null) {
            return redirect()->route('bill-payment.index');
        }

        try {
            $otp->startChallenge($customer);
        } catch (\Throwable) {
            return back()->with('danger', 'Could not resend code.');
        }

        return back()->with('status', 'A new verification code was sent.');
    }

    public function invoice(Request $request, PublicBillPaymentService $service): View|RedirectResponse
    {
        $customer = $this->verifiedCustomer($request);
        if ($customer === null) {
            return redirect()->route('bill-payment.index');
        }

        $summary = $service->customerSummary($customer);
        $linkAmount = session(self::SESSION_LINK_AMOUNT);
        $linkInvoiceId = session(self::SESSION_LINK_INVOICE);
        $activeTab = in_array($request->query('tab'), ['invoices', 'wallet', 'link'], true)
            ? $request->query('tab')
            : 'invoices';

        $recentLinks = PaymentLink::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(3)
            ->get();

        $gateways = PortalPaymentGateways::forPublicBillPay();

        return view('bill-payment.invoice', [
            'companyName' => config('isp.company_name'),
            'summary' => $summary,
            'bkashEnabled' => $gateways['bkash'],
            'sslcommerzEnabled' => $gateways['sslcommerz'],
            'nagadEnabled' => $gateways['nagad'],
            'rocketEnabled' => $gateways['rocket'],
            'piprapayEnabled' => $gateways['piprapay'],
            'anyGatewayEnabled' => $gateways['any'],
            'allowPartial' => (bool) config('bill_payment.allow_partial', true),
            'minAmount' => (float) config('bill_payment.min_amount', 10),
            'walletTopupEnabled' => (bool) config('bill_payment.wallet_topup_enabled', true),
            'walletMin' => (float) config('bill_payment.wallet_topup_min', 100),
            'linkAmount' => $linkAmount,
            'linkInvoiceId' => $linkInvoiceId,
            'activeTab' => $activeTab,
            'recentLinks' => $recentLinks,
        ]);
    }

    public function pay(Request $request, Invoice $invoice, PublicPaymentOrchestrator $payments): RedirectResponse
    {
        $customer = $this->verifiedCustomer($request);
        if ($customer === null) {
            return redirect()->route('bill-payment.index');
        }

        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);

        $balance = $invoice->balanceDue();
        $min = (float) config('bill_payment.min_amount', 10);

        $validated = $request->validate([
            'gateway' => ['required', 'in:'.implode(',', [PaymentGateway::BKASH, PaymentGateway::SSLCOMMERZ, PaymentGateway::NAGAD, PaymentGateway::ROCKET, PaymentGateway::PIPRAPAY])],
            'amount' => ['prohibited'],
        ]);

        $amount = $balance;

        return $payments->startInvoicePayment($invoice, $amount, $validated['gateway']);
    }

    public function walletTopup(Request $request, PublicPaymentOrchestrator $payments): RedirectResponse
    {
        $customer = $this->verifiedCustomer($request);
        if ($customer === null) {
            return redirect()->route('bill-payment.index');
        }

        $min = (float) config('bill_payment.wallet_topup_min', 100);
        $validated = $request->validate([
            'gateway' => ['required', 'in:'.implode(',', [PaymentGateway::BKASH, PaymentGateway::SSLCOMMERZ, PaymentGateway::NAGAD, PaymentGateway::ROCKET, PaymentGateway::PIPRAPAY])],
            'amount' => ['required', 'numeric', 'min:'.$min, 'max:500000'],
        ]);

        return $payments->startWalletTopup($customer, round((float) $validated['amount'], 2), $validated['gateway']);
    }

    public function invoicePdf(Request $request, Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $customer = $this->verifiedCustomer($request);
        if ($customer === null || (int) $invoice->customer_id !== (int) $customer->id) {
            abort(403);
        }

        return app(InvoicePdfController::class)->show($invoice);
    }

    public function createPaymentLink(Request $request, PaymentLinkService $links): RedirectResponse
    {
        $customer = $this->verifiedCustomer($request);
        if ($customer === null) {
            return redirect()->route('bill-payment.index');
        }

        $validated = $request->validate([
            'purpose' => ['required', 'in:invoice,wallet'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['nullable', 'numeric', 'min:10', 'max:500000'],
            'send_sms' => ['nullable', 'boolean'],
        ]);

        $invoice = null;
        if (! empty($validated['invoice_id'])) {
            $invoice = Invoice::query()
                ->withoutGlobalScopes()
                ->where('customer_id', $customer->id)
                ->findOrFail($validated['invoice_id']);
        }

        $purpose = $validated['purpose'] === 'wallet'
            ? PaymentLink::PURPOSE_WALLET
            : PaymentLink::PURPOSE_INVOICE;

        $link = $links->create(
            $customer,
            $purpose,
            $invoice,
            isset($validated['amount']) ? (float) $validated['amount'] : null,
            auth('web')->id(),
        );

        $smsSent = false;
        if ($request->boolean('send_sms')) {
            $smsSent = $links->sendSms($link);
        }

        return redirect()->route('bill-payment.invoice', ['tab' => 'link'])
            ->with('status', 'Payment link created.'.($smsSent ? ' SMS sent.' : ''))
            ->with('payment_link_url', $link->publicUrl());
    }

    public function sendPaymentLinkSms(PaymentLink $paymentLink, PaymentLinkService $links): RedirectResponse
    {
        $customer = $this->verifiedCustomer(request());
        if ($customer === null || (int) $paymentLink->customer_id !== (int) $customer->id) {
            abort(403);
        }

        if (! $links->sendSms($paymentLink)) {
            return back()->with('danger', 'Could not send SMS. Check phone number.');
        }

        return back()->with('status', 'Payment link sent via SMS.');
    }

    public function receipt(Request $request, Payment $payment): View|RedirectResponse
    {
        $customer = $this->sessionCustomer($request);
        if ($customer === null || (int) $payment->customer_id !== (int) $customer->id) {
            return redirect()->route('bill-payment.index');
        }

        $payment->load(['invoice', 'customer']);

        return view('bill-payment.receipt', [
            'companyName' => config('isp.company_name'),
            'payment' => $payment,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget([
            self::SESSION_CUSTOMER,
            self::SESSION_VERIFIED,
            self::SESSION_LINK_AMOUNT,
            self::SESSION_LINK_INVOICE,
        ]);

        return redirect()->route('bill-payment.index');
    }

    private function sessionCustomer(Request $request): ?Customer
    {
        $id = $request->session()->get(self::SESSION_CUSTOMER);

        return $id ? Customer::query()->withoutGlobalScopes()->find($id) : null;
    }

    private function verifiedCustomer(Request $request): ?Customer
    {
        $customer = $this->sessionCustomer($request);
        if ($customer === null) {
            return null;
        }

        if ($request->session()->get(self::SESSION_VERIFIED)) {
            return $customer;
        }

        if (! app(BillPaymentOtpService::class)->isEnabled()) {
            $request->session()->put(self::SESSION_VERIFIED, true);

            return $customer;
        }

        return null;
    }
}
