<?php

namespace App\Models;

use App\Services\Saas\SaasContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MainSiteData extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'value',
    ];

    public static function tenantKey(int $tenantId, string $type): string
    {
        return 't'.$tenantId.':'.$type;
    }

    public static function settingsTenantId(): ?int
    {
        return SaasContext::tenantId();
    }

    /**
     * Get a specific value by its type name, returning the decoded array or string.
     */
    public static function getValue(string $type, $default = null)
    {
        $tenantId = self::settingsTenantId();
        if ($tenantId) {
            return self::getTenantValue($tenantId, $type, $default);
        }

        try {
            $active = self::getActive();
            if (property_exists($active, $type)) {
                return $active->$type;
            }
        } catch (\Throwable $e) {
            // Fallback to database query if cache fails
        }

        $record = self::where('type', $type)->first();

        if (! $record) {
            return $default;
        }

        return self::decodedRecord($record);
    }

    /**
     * Update or create a specific key-value record
     */
    public static function setValue(string $type, $value): self
    {
        // Encode to JSON if array
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $type = self::bareType($type);
        $tenantId = self::settingsTenantId();
        $key = $tenantId ? self::tenantKey($tenantId, $type) : $type;

        if ($tenantId && $key === $type) {
            $key = self::tenantKey($tenantId, $type);
        }

        $row = self::updateOrCreate(
            ['type' => $key],
            ['value' => $value]
        );
        self::forgetSiteCache();

        return $row;
    }

    public static function platformValue(string $type, $default = null)
    {
        $record = self::query()->where('type', self::bareType($type))->first();

        return $record ? self::decodedRecord($record) : $default;
    }

    public static function setValueForTenant(int $tenantId, string $type, $value): self
    {
        $previous = SaasContext::forcedTenantId();
        SaasContext::forceTenant($tenantId);
        try {
            return self::setValue($type, $value);
        } finally {
            SaasContext::forceTenant($previous);
        }
    }

    private static function bareType(string $type): string
    {
        return preg_replace('/^t\d+:/', '', $type) ?: $type;
    }

    private static function getTenantValue(int $tenantId, string $type, $default = null)
    {
        $type = self::bareType($type);
        $record = self::query()->where('type', self::tenantKey($tenantId, $type))->first();
        if ($record) {
            return self::decodedRecord($record);
        }

        if (in_array($type, ['payment_bkash_enabled', 'payment_nagad_enabled', 'payment_sslcommerz_enabled'], true)) {
            return $default !== null ? $default : 1;
        }

        if (in_array($type, ['site_name', 'portal_name'], true)) {
            $operator = SaasContext::operator() ?? SaasContext::hostOperator();
            if ($operator && filled($operator->company)) {
                return $operator->company;
            }
        }

        return $default;
    }

    private static function decodedRecord(self $record): mixed
    {
        $raw = $record->getRawOriginal('value');
        $decoded = @json_decode($raw, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $record->value;
    }

    public static function forgetSiteCache(): void
    {
        Cache::forget('main_site_data_active');
        $tenantId = self::settingsTenantId();
        if ($tenantId) {
            Cache::forget('main_site_data_active_t'.$tenantId);
        }
    }

    /**
     * Get all active data and return it as an object
     * so that existing Blade templates ($siteData->hero_title) don't break.
     */
    public static function getActive()
    {
        $tenantId = self::settingsTenantId();
        $cacheKey = $tenantId ? 'main_site_data_active_t'.$tenantId : 'main_site_data_active';

        return Cache::rememberForever($cacheKey, function () use ($tenantId) {
            $data = new \stdClass;
            $prefix = $tenantId ? self::tenantKey($tenantId, '') : null;
            $records = $tenantId
                ? self::query()->where('type', 'like', $prefix.'%')->get()
                : self::query()->where('type', 'not like', 't%:%')->get();

            foreach ($records as $record) {
                $raw = $record->getRawOriginal('value');
                $decoded = @json_decode($raw, true);
                $value = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $record->value;
                $key = $tenantId ? substr($record->type, strlen($prefix)) : $record->type;
                if ($key === '' || str_contains($key, ':')) {
                    continue;
                }
                $data->$key = $value;
            }

            // Defaults in case they don't exist in DB
            $defaults = [
                'is_active' => true,
                'hero_title' => null,
                'hero_subtitle' => null,
                'hero_slides' => [],
                'about_tagline' => null,
                'about_title' => null,
                'about_body' => null,
                'services' => [],
                'gallery_items' => [],
                'packages_section_title' => null,
                'packages_section_subtitle' => null,
                'registration_link' => null,
                'team_title' => null,
                'team_subtitle' => null,
                'team_members' => [],
                'blog_title' => null,
                'blog_subtitle' => null,
                'blog_posts' => [],
                'testimonial_title' => null,
                'testimonial_subtitle' => null,
                'testimonials' => [],
                'contact_title' => null,
                'contact_subtitle' => null,
                'google_map_embed' => null,
                'footer_copyright' => null,
                'social_facebook' => null,
                'social_twitter' => null,
                'social_instagram' => null,
                'social_youtube' => null,
                'social_whatsapp' => null,
                'valuable_clients' => [],
                'theme_preset' => 'fintech',
                'theme_name' => 'ocean_blue',
                'theme_primary_color' => '#0284c7',
                'theme_accent_color' => '#38bdf8',
                'theme_card_style' => 'glass',
                'theme_border_radius' => '16px',
                'theme_font_size' => 'medium',
                'theme_font_family' => 'Outfit',
                'theme_nav_style' => 'sidebar',
                'theme_widget_style' => 'glass',
                'theme_mode' => 'dark',
                'theme_transparency' => '0.5',
                'theme_blur' => '16px',
                'theme_animations' => '1.0',
                'theme_gradient_intensity' => '0.7',
            ];

            foreach ($defaults as $key => $defaultValue) {
                if (! property_exists($data, $key)) {
                    $data->$key = $defaultValue;
                }
            }

            if ($tenantId && empty($data->site_name)) {
                $operator = SaasContext::operator() ?? SaasContext::hostOperator();
                if ($operator && filled($operator->company)) {
                    $data->site_name = $operator->company;
                }
            }

            return $data;
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetSiteCache());
        static::updated(fn () => self::forgetSiteCache());
        static::deleted(fn () => self::forgetSiteCache());
    }
}
