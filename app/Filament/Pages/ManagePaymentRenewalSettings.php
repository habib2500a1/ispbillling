<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Support\PaymentRenewalPolicy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManagePaymentRenewalSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static string $view = 'filament.pages.manage-payment-renewal-settings';

    protected static ?string $slug = 'payment-renewal-settings';

    protected static ?string $navigationLabel = 'Payment renew rules';

    protected static ?string $title = 'Payment renew rules';

    protected static ?string $navigationGroup = 'Billing';

    protected static bool $shouldRegisterNavigation = false;

    public string $payment_renewal_base = PaymentRenewalPolicy::SMART;

    public string $payment_renewal_late_grace_days = '7';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'admin']);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->payment_renewal_base = PaymentRenewalPolicy::systemDefault();
        $this->payment_renewal_late_grace_days = (string) PaymentRenewalPolicy::lateGraceDays();
    }

    public function save(): void
    {
        $this->validate([
            'payment_renewal_base' => 'required|in:'.implode(',', [
                PaymentRenewalPolicy::SMART,
                PaymentRenewalPolicy::FROM_PAYMENT_DATE,
                PaymentRenewalPolicy::FROM_PREVIOUS_EXPIRY,
            ]),
            'payment_renewal_late_grace_days' => 'required|integer|min:0|max:90',
        ]);

        AppSetting::putValue(
            'billing.payment_renewal_base',
            PaymentRenewalPolicy::normalize($this->payment_renewal_base),
        );
        AppSetting::putValue(
            'billing.payment_renewal_late_grace_days',
            (string) max(0, min(90, (int) $this->payment_renewal_late_grace_days)),
        );
        AppSetting::syncToRuntimeConfig();

        Notification::make()
            ->title('Payment renew rules saved')
            ->body('Bill collection desk and auto-activation will use these defaults.')
            ->success()
            ->send();
    }
}
