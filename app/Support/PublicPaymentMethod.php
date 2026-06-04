<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Services\Payments\PipraPayCheckoutService;

/**
 * Payment options on public bill pay / customer portal (enabled gateways only).
 *
 * @phpstan-type PublicPaymentMethodArray array{
 *   gateway: string,
 *   mode: string,
 *   label: string,
 *   badge: string,
 *   hint: string,
 *   tone: string,
 * }
 */
final class PublicPaymentMethod
{
    public const MODE_PERSONAL = 'personal';

    public const MODE_MERCHANT = 'merchant';

    /**
     * @return list<PublicPaymentMethodArray>
     */
    public static function collect(?string $bkashChannel = BkashSettings::CHANNEL_PORTAL): array
    {
        AppSetting::syncPublicPaymentGatewayFlags();

        $flags = config('bill_payment.gateways', []);
        $methods = [];

        if (($flags['bkash'] ?? false) && self::bkashChannelAllowed($bkashChannel)) {
            array_push($methods, ...self::bkashDisplayMethods($bkashChannel ?? BkashSettings::CHANNEL_PORTAL));
        }

        if ($flags['nagad'] ?? false) {
            $methods[] = self::nagadDisplayMethod();
        }

        if ($flags['piprapay'] ?? false) {
            $methods[] = self::merchant(
                PaymentGateway::PIPRAPAY,
                'PipraPay',
                'bKash · Nagad · Card — official checkout',
                'pipra',
            );
        }

        if ($flags['sslcommerz'] ?? false) {
            $methods[] = self::merchant(
                PaymentGateway::SSLCOMMERZ,
                'SSLCommerz',
                'Card · mobile banking',
                'ssl',
            );
        }

        if ($flags['rocket'] ?? false) {
            $methods[] = self::personal(
                PaymentGateway::ROCKET,
                'Rocket',
                'Send money + TrxID',
                'rocket',
            );
        }

        return $methods;
    }

    private static function bkashChannelAllowed(?string $channel): bool
    {
        return $channel === null || BkashSettings::isEnabledForChannel($channel);
    }

    /**
     * @return list<PublicPaymentMethodArray>
     */
    private static function bkashDisplayMethods(string $channel): array
    {
        $methods = [];

        if (BkashSettings::isPersonalActiveForChannel($channel)) {
            $methods[] = array_merge(
                self::personal(PaymentGateway::BKASH, 'bKash', 'Send money to our number', 'bkash'),
                ['checkout' => PaymentGateway::BKASH_PERSONAL],
            );
        }

        if (BkashSettings::isMerchantActiveForChannel($channel)) {
            $methods[] = array_merge(
                self::merchant(PaymentGateway::BKASH, 'bKash', 'Official checkout (redirect)', 'bkash'),
                ['checkout' => PaymentGateway::BKASH_MERCHANT],
            );
        }

        return $methods;
    }

    /**
     * @return PublicPaymentMethodArray
     */
    private static function nagadDisplayMethod(): array
    {
        $type = (string) config('nagad.gateway_type', PersonalMfsGateway::MODE_API);

        if ($type === PersonalMfsGateway::MODE_PERSONAL) {
            return self::personal(PaymentGateway::NAGAD, 'Nagad', 'Send money to our number', 'nagad');
        }

        return self::merchant(PaymentGateway::NAGAD, 'Nagad', 'Nagad PG checkout', 'nagad');
    }

    /**
     * @return PublicPaymentMethodArray
     */
    private static function personal(string $gateway, string $label, string $hint, string $tone): array
    {
        return [
            'gateway' => $gateway,
            'mode' => self::MODE_PERSONAL,
            'label' => $label,
            'badge' => 'Personal',
            'hint' => $hint,
            'tone' => $tone,
        ];
    }

    /**
     * @return PublicPaymentMethodArray
     */
    private static function merchant(string $gateway, string $label, string $hint, string $tone): array
    {
        return [
            'gateway' => $gateway,
            'mode' => self::MODE_MERCHANT,
            'label' => $label,
            'badge' => 'Merchant',
            'hint' => $hint,
            'tone' => $tone,
        ];
    }

    /**
     * @param  list<PublicPaymentMethodArray>  $methods
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function legacyFlags(array $methods): array
    {
        $gateways = array_column($methods, 'gateway');

        return [
            'bkash' => in_array(PaymentGateway::BKASH, $gateways, true),
            'sslcommerz' => in_array(PaymentGateway::SSLCOMMERZ, $gateways, true),
            'nagad' => in_array(PaymentGateway::NAGAD, $gateways, true),
            'rocket' => in_array(PaymentGateway::ROCKET, $gateways, true),
            'piprapay' => in_array(PaymentGateway::PIPRAPAY, $gateways, true),
            'any' => $methods !== [],
        ];
    }
}
