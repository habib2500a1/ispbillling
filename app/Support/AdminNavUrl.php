<?php

namespace App\Support;

use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Route;

final class AdminNavUrl
{
    /**
     * @param  class-string<Page|Resource>  $class
     */
    public static function for(string $class, string $name = 'index', array $parameters = []): string
    {
        try {
            if (is_subclass_of($class, Page::class)) {
                return $class::getUrl($parameters);
            }

            if (is_subclass_of($class, Resource::class)) {
                return $class::getUrl($name, $parameters);
            }
        } catch (\Throwable) {
            // Fall through to path guess.
        }

        return url('/admin');
    }

    public static function hasRoute(string $name): bool
    {
        return Route::has($name);
    }
}
