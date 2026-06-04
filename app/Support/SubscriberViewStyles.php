<?php

namespace App\Support;

/**
 * Subscriber 360 view page — public/css/admin/subscriber-view/.
 */
final class SubscriberViewStyles
{
    public const BUNDLE_FILE = 'subscriber-view-pro.css';

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [
            'admin/subscriber-view/01-shell-hero.css',
            'admin/subscriber-view/02-panels-cards.css',
            'admin/subscriber-view/03-network-diagnostics.css',
            'admin/subscriber-view/04-contact-location.css',
        ];
    }

    public static function version(): int
    {
        return StylesheetModules::version(self::modules(), self::BUNDLE_FILE);
    }

    public static function html(): string
    {
        return StylesheetModules::html(self::modules(), 'isp-subscriber-view', 'subscriber-view', self::BUNDLE_FILE);
    }

    public static function navigatedScript(): string
    {
        return StylesheetModules::navigatedScript(
            self::modules(),
            'isp-subscriber-view',
            'subscriber-view',
            'ensureSubscriberViewCss',
            self::BUNDLE_FILE,
        );
    }

    /** OLT hub + subscriber 360 modules (view page SPA navigation). */
    public static function navigatedScriptWithOlt(): string
    {
        $assets = [
            [
                'id' => 'subscriber-olt-hub-css',
                'href' => asset('css/olt-hub-pro.css').'?v='.(@filemtime(public_path('css/olt-hub-pro.css')) ?: 1),
            ],
        ];

        if (StylesheetModules::shouldBundle() && is_file(public_path('css/'.self::BUNDLE_FILE))) {
            $assets[] = [
                'id' => 'subscriber-view-bundle',
                'href' => asset('css/'.self::BUNDLE_FILE).'?v='.self::version(),
            ];
        } else {
            $v = self::version();
            foreach (self::modules() as $file) {
                if (! is_file(public_path('css/'.$file))) {
                    continue;
                }
                $slug = basename($file, '.css');
                $assets[] = [
                    'id' => 'subscriber-view-'.$slug,
                    'href' => asset('css/'.$file).'?v='.$v,
                ];
            }
        }

        return StylesheetModules::navigatedScriptFromAssets(
            $assets,
            'isp-subscriber-view',
            'ensureSubscriberViewCss',
        );
    }

}
