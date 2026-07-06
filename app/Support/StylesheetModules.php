<?php

namespace App\Support;

/**
 * Shared helper for modular CSS under public/css/.
 */
final class StylesheetModules
{
    public static function shouldBundle(): bool
    {
        return (bool) config('isp.assets.bundle_css', false);
    }

    /**
     * @param  list<string>  $modules  Paths relative to public/css/
     */
    public static function version(array $modules, ?string $bundleFile = null): int
    {
        if ($bundleFile !== null && self::shouldBundle() && is_file(public_path('css/'.$bundleFile))) {
            return (int) ((@filemtime(public_path('css/'.$bundleFile)) ?: 1) + (int) config('isp.assets.version_salt', 0) * 1_000_000);
        }

        $max = 0;

        foreach ($modules as $file) {
            $max = max($max, (int) (@filemtime(public_path('css/'.$file)) ?: 0));
        }

        return $max > 0 ? $max + (int) config('isp.assets.version_salt', 0) * 1_000_000 : 1;
    }

    /**
     * @param  list<string>  $modules
     * @param  string|null  $bundleFile  e.g. admin-saas.css (relative to public/css/)
     */
    public static function html(
        array $modules,
        string $dataAttribute,
        string $idPrefix = '',
        ?string $bundleFile = null,
    ): string {
        if ($bundleFile !== null && self::shouldBundle() && is_file(public_path('css/'.$bundleFile))) {
            $v = self::version($modules, $bundleFile);
            $id = $idPrefix !== '' ? $idPrefix.'-bundle' : 'isp-css-bundle';
            $href = e(asset('css/'.$bundleFile).'?v='.$v);

            return '<link rel="stylesheet" href="'.$href.'" data-'.$dataAttribute.'="bundle" id="'.$id.'">'."\n";
        }

        $v = self::version($modules);
        $html = '';

        foreach ($modules as $file) {
            $path = public_path('css/'.$file);
            if (! is_file($path)) {
                continue;
            }

            $slug = basename($file, '.css');
            $id = $idPrefix !== '' ? $idPrefix.'-'.$slug : $slug;
            $href = e(asset('css/'.$file).'?v='.$v);
            $html .= '<link rel="stylesheet" href="'.$href.'" data-'.$dataAttribute.'="1" id="'.$id.'">'."\n";
        }

        return $html;
    }

    /**
     * @param  list<array{id: string, href: string}>  $assets
     */
    public static function navigatedScriptFromAssets(
        array $assets,
        string $dataAttribute,
        string $globalFunctionName,
    ): string {
        $json = json_encode($assets, JSON_UNESCAPED_SLASHES);
        $attr = e($dataAttribute);

        return <<<JS
<script data-cfasync="false">
(function () {
    var assets = {$json};

    function {$globalFunctionName}() {
        assets.forEach(function (asset) {
            var existing = document.getElementById(asset.id);
            if (existing && existing.getAttribute('href') === asset.href) {
                if (existing.parentNode !== document.head) {
                    document.head.appendChild(existing);
                }
                return;
            }
            if (existing) {
                existing.remove();
            }
            var link = document.createElement('link');
            link.id = asset.id;
            link.rel = 'stylesheet';
            link.href = asset.href;
            link.setAttribute('data-{$attr}', '1');
            document.head.appendChild(link);
        });
    }

    {$globalFunctionName}();
    document.addEventListener('livewire:navigated', {$globalFunctionName});
})();
</script>
JS;
    }

    /**
     * @param  list<string>  $modules
     */
    public static function navigatedScript(
        array $modules,
        string $dataAttribute,
        string $idPrefix,
        string $globalFunctionName,
        ?string $bundleFile = null,
    ): string {
        if ($bundleFile !== null && self::shouldBundle() && is_file(public_path('css/'.$bundleFile))) {
            $v = self::version($modules, $bundleFile);
            $id = $idPrefix !== '' ? $idPrefix.'-bundle' : 'isp-css-bundle';
            $assets = [[
                'id' => $id,
                'href' => asset('css/'.$bundleFile).'?v='.$v,
            ]];

            return self::navigatedScriptFromAssets($assets, $dataAttribute, $globalFunctionName);
        }

        $v = self::version($modules);
        $assets = [];

        foreach ($modules as $file) {
            $path = public_path('css/'.$file);
            if (! is_file($path)) {
                continue;
            }

            $slug = basename($file, '.css');
            $assets[] = [
                'id' => $idPrefix.'-'.$slug,
                'href' => asset('css/'.$file).'?v='.$v,
            ];
        }

        return self::navigatedScriptFromAssets($assets, $dataAttribute, $globalFunctionName);
    }

    /**
     * @param  list<string>  $modules
     * @return list<string> Missing paths (relative to public/css/)
     */
    public static function missing(array $modules): array
    {
        $missing = [];

        foreach ($modules as $file) {
            if (! is_file(public_path('css/'.$file))) {
                $missing[] = $file;
            }
        }

        return $missing;
    }

}
