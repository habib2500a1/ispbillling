<?php

namespace App\View\Composers;

use App\Services\BillPayment\BillPaymentOtpService;
use App\Support\ResellerBranding;
use Illuminate\View\View;

final class BillPaymentViewComposer
{
    public function __construct(
        private readonly BillPaymentOtpService $otp,
    ) {}

    public function compose(View $view): void
    {
        $customer = ResellerBranding::customerFromContext();

        $view->with(array_merge(
            ResellerBranding::forCustomer($customer),
            ['otpEnabled' => $this->otp->isEnabled()],
        ));
    }
}
