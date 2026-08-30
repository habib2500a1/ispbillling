<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSaasOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomersInfo extends Model
{
    use BelongsToSaasOperator;
    use HasFactory;

    protected $fillable = [
        'saas_operator_id',
        'customer_unique_id',
        'customer_name',
        'contact_person',
        'parents_name',
        'spouse_name',
        'address',
        'email',
        'mobile',
        'alternative_mobile',
        'identification_no',
        'profession',
        'photo_url',
        'disable_count',
        'ppp_user_id',
        'connection_date',
        'portal_access_token_hash',
        'portal_access_token_at',
        'package_id',
        'status',
        'reseller_id',
        // 'auto_disabled',
        // 'expired_date',
        // 'auto_disabled_month',
        // 'extra_date',
    ];

    // In CustomersInfo model
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUnderDisableLimit($query, $limit)
    {
        return $query->where('disable_count', '<', $limit);
    }

    public function scopeHasPPPUser($query)
    {
        return $query->whereNotNull('ppp_user_id');
    }

    public function pppUser()
    {
        return $this->belongsTo(PPPSecrets::class, 'ppp_user_id', 'id');
    }

    public function package()
    {
        return $this->belongsTo(PackageList::class, 'package_id', 'id');
    }

    public function customerAddress()
    {
        return $this->hasMany(CustomersAddress::class, 'customer_address_unique_id', 'customer_unique_id');
    }

    public function paymentSummary()
    {
        return $this->hasMany(PaymentSummary::class, 'customer_payment_unique_id', 'customer_unique_id');
    }

    public function collectionSummary()
    {
        return $this->hasMany(CollectionSummary::class, 'customer_collection_unique_id', 'customer_unique_id');
    }

    public function billing()
    {
        return $this->belongsTo(BillingInfo::class, 'customer_unique_id', 'customer_bill_unique_id');
    }

    public function official()
    {
        return $this->belongsTo(OfficialInfo::class, 'customer_unique_id', 'customer_office_unique_id');
    }

    public function scopeSearch($query, $search)
    {
        $term = trim((string) $search);
        if ($term === '') {
            return $query;
        }

        $likes = array_values(array_unique(array_filter(array_merge([$term], $this->searchMobileVariants($term)))));
        $likes = array_map(fn (string $v) => str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $v), $likes);
        $safeTerm = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);

        return $query->where(function ($q) use ($likes, $safeTerm) {
            foreach ($likes as $like) {
                $q->orWhere('customer_unique_id', 'like', '%'.$like.'%')
                    ->orWhere('customer_name', 'like', '%'.$like.'%')
                    ->orWhere('contact_person', 'like', '%'.$like.'%')
                    ->orWhere('email', 'like', '%'.$like.'%')
                    ->orWhere('mobile', 'like', '%'.$like.'%')
                    ->orWhere('alternative_mobile', 'like', '%'.$like.'%')
                    ->orWhere('address', 'like', '%'.$like.'%');
            }

            $q->orWhereHas('pppUser', function ($inner) use ($safeTerm) {
                $inner->where('username', 'like', '%'.$safeTerm.'%');
            });
        });
    }

    /**
     * @return list<string>
     */
    private function searchMobileVariants(string $term): array
    {
        $digits = preg_replace('/\D+/', '', $term) ?: '';
        if ($digits === '' || strlen($digits) < 7) {
            return [];
        }

        $out = [$digits];
        if (str_starts_with($digits, '880') && strlen($digits) >= 13) {
            $out[] = substr($digits, 2);
            $out[] = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $out[] = '88'.$digits;
            $out[] = substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            $out[] = '0'.$digits;
            $out[] = '880'.$digits;
        }

        return $out;
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    public function onus()
    {
        return $this->hasMany(CustomerOnu::class, 'customers_info_id');
    }

    public function primaryOnu(): ?CustomerOnu
    {
        return $this->onus()->orderByDesc('last_polled_at')->orderByDesc('id')->first();
    }

    public function isVip(): bool
    {
        return strtolower((string) ($this->official?->customer_type ?? '')) === 'vip'
            || $this->status === 'vip';
    }

    public function isCorporate(): bool
    {
        return strtolower((string) ($this->official?->client_type ?? '')) === 'corporate';
    }

    public function gpsCoordinates(): ?array
    {
        foreach ($this->customerAddress as $address) {
            if ($address->latitude && $address->longitude) {
                return [
                    'lat' => (float) $address->latitude,
                    'lng' => (float) $address->longitude,
                ];
            }
        }

        return null;
    }

    public function joinDate(): ?\Carbon\Carbon
    {
        $date = $this->connection_date ?? $this->created_at;

        return $date ? \Carbon\Carbon::parse($date) : null;
    }

    public function joinDateLabel(string $format = 'd M Y'): string
    {
        return $this->joinDate()?->format($format) ?? '—';
    }
}
