<?php

namespace App\View\Composers;

use App\Services\BillPayment\BillPaymentOtpService;
use Illuminate\View\View;

final class BillPaymentViewComposer
{
    public function __construct(
        private readonly BillPaymentOtpService $otp,
    ) {}

    public function compose(View $view): void
    {
        $view->with('otpEnabled', $this->otp->isEnabled());
    }
}
