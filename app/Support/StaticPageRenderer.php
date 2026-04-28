<?php

namespace App\Support;

class StaticPageRenderer
{
    public static function render(string $view): string
    {
        $viewPath = resource_path("views/{$view}.blade.php");

        abort_unless(is_file($viewPath), 404);

        $html = file_get_contents($viewPath);

        abort_if($html === false, 500, 'Unable to read the requested page.');

        $html = SeoSupport::injectPageEnhancements($view, $html);
        $html = self::resolveAssetUrls($html);

        return $html;
    }

    private static function resolveAssetUrls(string $html): string
    {
        $html = preg_replace_callback(
            '#(^|[^A-Za-z0-9:\\\\])(/assets/([A-Za-z0-9/_\.-]+))#',
            static function (array $matches): string {
                return $matches[1] . asset('assets/' . ltrim($matches[3], '/'));
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '#\\\\/assets\\\\/([A-Za-z0-9/_\.-]+)#',
            static function (array $matches): string {
                return str_replace('/', '\\/', asset('assets/' . ltrim($matches[1], '/')));
            },
            $html
        ) ?? $html;
    }
}
