<?php

namespace App\Services\Billing;

use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class PublicPayCustomer
{
    public static function findByLookup(string $lookup): ?CustomersInfo
    {
        $lookup = trim($lookup);
        if ($lookup === '') {
            return null;
        }

        $mobile = preg_replace('/\D+/', '', $lookup) ?? '';
        if (strlen($mobile) === 11 && str_starts_with($mobile, '0')) {
            $mobile = '88'.$mobile;
        }

        return CustomersInfo::query()
            ->with(['billing', 'pppUser', 'package', 'onus'])
            ->where(function ($q) use ($lookup, $mobile) {
                $q->where('customer_unique_id', $lookup)
                    ->orWhereHas('pppUser', fn ($p) => $p->where('username', $lookup));
                if (strlen($mobile) >= 11) {
                    $q->orWhere('mobile', $mobile)->orWhere('mobile', $lookup);
                }
            })
            ->first();
    }

    public static function current(): ?CustomersInfo
    {
        $ppp = Auth::guard('ppp')->user();
        if ($ppp instanceof PPPSecrets && $ppp->customer) {
            return $ppp->customer;
        }

        $web = Auth::guard('web')->user();
        if ($web && method_exists($web, 'customer') && $web->customer) {
            return $web->customer;
        }

        $id = session('public_pay_customer_id');

        return $id ? CustomersInfo::query()->find($id) : null;
    }

    public static function remember(CustomersInfo $customer): void
    {
        session([
            'public_pay_customer_id' => $customer->id,
            'public_pay_uid' => $customer->customer_unique_id,
        ]);
    }

    public static function isPublic(): bool
    {
        return (bool) session('public_pay_customer_id');
    }

    public static function afterPayment(CustomersInfo $customer, string $message, bool $ok = true): RedirectResponse
    {
        if (self::isPublic() || ! Auth::guard('ppp')->check()) {
            return redirect()
                ->route('pay.show', $customer->customer_unique_id)
                ->with($ok ? 'success' : 'error', $message);
        }

        $route = $ok ? 'filament.portal.pages.dashboard' : 'filament.portal.pages.pay-bill';

        return redirect()->route($route)->with($ok ? 'success' : 'error', $message);
    }

    public static function failRedirect(string $message): RedirectResponse
    {
        if (self::isPublic()) {
            $uid = session('public_pay_uid');

            return $uid
                ? redirect()->route('pay.show', $uid)->with('error', $message)
                : redirect()->route('pay.lookup')->with('error', $message);
        }

        return redirect()->route('filament.portal.pages.pay-bill')->with('error', $message);
    }
}
