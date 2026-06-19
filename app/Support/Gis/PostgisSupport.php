<?php

namespace App\Support\Gis;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PostgisSupport
{
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return self::$enabled = false;
        }

        return self::$enabled = (bool) Cache::remember('gis.postgis_enabled', 300, function (): bool {
            try {
                $row = DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'postgis' LIMIT 1");

                return $row !== null;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public static function forgetCache(): void
    {
        self::$enabled = null;
        Cache::forget('gis.postgis_enabled');
    }

    public static function customersHaveGeom(): bool
    {
        return self::enabled() && Schema::hasColumn('customers', 'geom');
    }
}
