<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerContact;

final class SubscriberContactLinks
{
    /**
     * E.164-style digits for wa.me (Bangladesh), e.g. 8801712345678.
     */
    public static function toWhatsAppDigits(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '880'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '880')) {
            $digits = '880'.$digits;
        }

        return strlen($digits) >= 12 ? $digits : null;
    }

    public static function whatsAppChatUrl(?string $phone, ?string $message = null): ?string
    {
        $digits = self::toWhatsAppDigits($phone);
        if ($digits === null) {
            return null;
        }

        $url = 'https://wa.me/'.$digits;
        if ($message !== null && trim($message) !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }

    public static function googleMapsUrl(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return 'https://www.google.com/maps?q='.rawurlencode($lat.','.$lng);
    }

    public static function formatCoordinates(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return rtrim(rtrim(sprintf('%.6f', $lat), '0'), '.')
            .', '
            .rtrim(rtrim(sprintf('%.6f', $lng), '0'), '.');
    }

    /**
     * @return array{
     *     phone: ?string,
     *     whatsapp_phone: ?string,
     *     whatsapp_chat: ?string,
     *     whatsapp_bill: ?string,
     *     google_maps: ?string,
     *     fiber_map: ?string,
     *     gps_lat: ?float,
     *     gps_lng: ?float,
     *     gps_display: ?string,
     *     has_gps: bool,
     *     district: ?string,
     *     thana: ?string,
     * }
     */
    public static function forCustomer(Customer $customer, float $openBalance = 0): array
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $lat = self::parseCoordinate($meta['gps_lat'] ?? null);
        $lng = self::parseCoordinate($meta['gps_lng'] ?? null);

        $whatsappPhone = self::resolveWhatsAppPhone($customer);
        $company = (string) config('isp.company_name', 'ISP');
        $payUrl = url('/pay?code='.urlencode((string) $customer->customer_code));
        $due = $openBalance > 0
            ? ' Due: '.number_format($openBalance, 2).' BDT.'
            : '';
        $billMessage = "{$company}: Bill reminder for {$customer->name} ({$customer->customer_code}).{$due} Pay online: {$payUrl}";

        return [
            'phone' => $customer->phone ?: null,
            'whatsapp_phone' => $whatsappPhone,
            'whatsapp_chat' => self::whatsAppChatUrl($whatsappPhone),
            'whatsapp_bill' => self::whatsAppChatUrl($whatsappPhone, $billMessage),
            'google_maps' => self::googleMapsUrl($lat, $lng),
            'fiber_map' => \App\Filament\Pages\FiberPlantMap::getUrl().'?customer='.$customer->getKey(),
            'gps_lat' => $lat,
            'gps_lng' => $lng,
            'gps_display' => self::formatCoordinates($lat, $lng),
            'has_gps' => $lat !== null && $lng !== null,
            'district' => $customer->district?->name
                ?? (filled($meta['district'] ?? null) ? (string) $meta['district'] : null),
            'thana' => $customer->upazila?->name
                ?? (filled($meta['thana'] ?? null) ? (string) $meta['thana'] : null),
        ];
    }

    private static function resolveWhatsAppPhone(Customer $customer): ?string
    {
        $contacts = $customer->relationLoaded('contacts')
            ? $customer->contacts
            : $customer->contacts()->get();

        /** @var CustomerContact|null $wa */
        $wa = $contacts->first(fn (CustomerContact $c): bool => $c->is_whatsapp && filled($c->phone));

        return $wa?->phone ?? $customer->phone;
    }

    private static function parseCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
