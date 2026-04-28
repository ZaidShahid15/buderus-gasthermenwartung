<?php

declare(strict_types=1);

const BASE_URL = 'https://buderus-gasthermenwartung.at/';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0 Safari/537.36';
const REQUEST_TIMEOUT = 30;

$root = dirname(__DIR__);
$publicRoot = $root . DIRECTORY_SEPARATOR . 'public';
$viewsRoot = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
$storageRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'buderus-import';
$assetsRoot = $publicRoot . DIRECTORY_SEPARATOR . 'assets';

$assetDirectories = [
    'css' => $assetsRoot . DIRECTORY_SEPARATOR . 'css',
    'js' => $assetsRoot . DIRECTORY_SEPARATOR . 'js',
    'images' => $assetsRoot . DIRECTORY_SEPARATOR . 'images',
    'fonts' => $assetsRoot . DIRECTORY_SEPARATOR . 'fonts',
];

foreach ([$storageRoot, ...array_values($assetDirectories)] as $directory) {
    ensureDirectory($directory);
}

$pageUrls = collectPageUrls(BASE_URL);
sort($pageUrls);

$assetManifest = [];
$pageManifest = [];

foreach ($pageUrls as $pageUrl) {
    fwrite(STDOUT, "Fetching page: {$pageUrl}\n");
    try {
        $response = fetchUrl($pageUrl);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, "Warning: {$exception->getMessage()}\n");
        continue;
    }
    $pagePath = urlPath($pageUrl);
    $viewName = viewNameFromPath($pagePath);

    file_put_contents(
        $storageRoot . DIRECTORY_SEPARATOR . $viewName . '.source.html',
        $response['body']
    );

    [$bladeHtml, $pageAssets] = localizePageHtml(
        $response['body'],
        $pageUrl,
        $assetDirectories,
        $assetManifest,
        $pageUrls
    );

    file_put_contents(
        $viewsRoot . DIRECTORY_SEPARATOR . $viewName . '.blade.php',
        $bladeHtml
    );

    $pageManifest[] = [
        'url' => $pageUrl,
        'path' => $pagePath,
        'view' => $viewName,
        'assets' => array_values(array_unique($pageAssets)),
    ];
}

file_put_contents(
    $storageRoot . DIRECTORY_SEPARATOR . 'pages.json',
    json_encode($pageManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

file_put_contents(
    $storageRoot . DIRECTORY_SEPARATOR . 'assets.json',
    json_encode($assetManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

fwrite(STDOUT, sprintf("Imported %d page(s) and %d asset(s).\n", count($pageManifest), count($assetManifest)));

function collectPageUrls(string $baseUrl): array
{
    $base = normalizeUrl($baseUrl);
    $host = parse_url($base, PHP_URL_HOST);

    $queue = [];
    $seen = [];

    foreach (discoverSitemapPages($base) as $url) {
        $queue[] = $url;
    }

    $queue[] = $base;

    while ($queue !== []) {
        $current = array_shift($queue);
        $normalized = normalizeUrl($current);

        if ($normalized === null || isset($seen[$normalized])) {
            continue;
        }

        $seen[$normalized] = true;

        try {
            $response = fetchUrl($normalized);
        } catch (RuntimeException $exception) {
            fwrite(STDERR, "Warning: {$exception->getMessage()}\n");
            continue;
        }

        foreach (discoverInternalPageLinks($response['body'], $normalized, $host) as $link) {
            $normalizedLink = normalizeUrl($link);

            if ($normalizedLink !== null && !isset($seen[$normalizedLink])) {
                $queue[] = $normalizedLink;
            }
        }
    }

    $pages = array_keys($seen);
    sort($pages);

    return $pages;
}

function discoverSitemapPages(string $baseUrl): array
{
    $candidates = [
        rtrim($baseUrl, '/') . '/wp-sitemap.xml',
        rtrim($baseUrl, '/') . '/sitemap_index.xml',
    ];

    $pageUrls = [];

    foreach ($candidates as $candidate) {
        try {
            $response = fetchUrl($candidate);
        } catch (RuntimeException) {
            continue;
        }

        $xml = @simplexml_load_string($response['body']);

        if ($xml === false) {
            continue;
        }

        $namespaces = $xml->getNamespaces(true);

        if (isset($xml->sitemap)) {
            foreach ($xml->sitemap as $sitemap) {
                $loc = trim((string) $sitemap->loc);
                if ($loc === '') {
                    continue;
                }

                try {
                    $nestedResponse = fetchUrl($loc);
                } catch (RuntimeException $exception) {
                    fwrite(STDERR, "Warning: {$exception->getMessage()}\n");
                    continue;
                }

                $nestedXml = @simplexml_load_string($nestedResponse['body']);
                if ($nestedXml === false) {
                    continue;
                }

                foreach ($nestedXml->url as $urlNode) {
                    $pageUrl = trim((string) $urlNode->loc);
                    if ($pageUrl !== '' && isInternalPageUrl($pageUrl, parse_url($baseUrl, PHP_URL_HOST))) {
                        $pageUrls[] = $pageUrl;
                    }
                }
            }
        }

        if (isset($xml->url)) {
            foreach ($xml->url as $urlNode) {
                $pageUrl = trim((string) $urlNode->loc);
                if ($pageUrl !== '' && isInternalPageUrl($pageUrl, parse_url($baseUrl, PHP_URL_HOST))) {
                    $pageUrls[] = $pageUrl;
                }
            }
        }
    }

    return array_values(array_unique(array_map(static fn (string $url): string => normalizeUrl($url) ?? $url, $pageUrls)));
}

function discoverInternalPageLinks(string $html, string $pageUrl, string $host): array
{
    [$dom, $xpath] = createDom($html);
    $links = [];

    foreach ($xpath->query('//a[@href]') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $resolved = absolutizeUrl($node->getAttribute('href'), $pageUrl);

        if ($resolved !== null && isInternalPageUrl($resolved, $host)) {
            $links[] = $resolved;
        }
    }

    return array_values(array_unique($links));
}

function localizePageHtml(
    string $html,
    string $pageUrl,
    array $assetDirectories,
    array &$assetManifest,
    array $pageUrls
): array {
    [$dom, $xpath] = createDom($html);
    $pageAssets = [];
    $pagePathMap = buildPagePathMap($pageUrls);

    foreach ($xpath->query('//*[@style]') as $node) {
        if ($node instanceof DOMElement) {
            $node->setAttribute('style', rewriteCssText($node->getAttribute('style'), $pageUrl, $assetDirectories, $assetManifest, $pageAssets));
        }
    }

    foreach ($xpath->query('//style') as $node) {
        if ($node instanceof DOMElement && $node->firstChild !== null) {
            $node->nodeValue = rewriteCssText($node->textContent, $pageUrl, $assetDirectories, $assetManifest, $pageAssets);
        }
    }

    foreach ($xpath->query('//link[@href]') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $rel = strtolower(trim($node->getAttribute('rel')));
        $href = $node->getAttribute('href');
        $absolute = absolutizeUrl($href, $pageUrl);

        if ($absolute === null) {
            continue;
        }

        if (str_contains($rel, 'stylesheet') || str_contains($rel, 'icon') || str_contains($rel, 'apple-touch-icon')) {
            $node->setAttribute('href', htmlAssetReference(downloadAsset($absolute, $assetDirectories, $assetManifest, $pageAssets)));
        }
    }

    foreach ($xpath->query('//script[@src]') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $absolute = absolutizeUrl($node->getAttribute('src'), $pageUrl);
        if ($absolute === null) {
            continue;
        }

        $node->setAttribute('src', htmlAssetReference(downloadAsset($absolute, $assetDirectories, $assetManifest, $pageAssets)));
    }

    foreach ([
        ['//img[@src]', 'src'],
        ['//img[@srcset]', 'srcset'],
        ['//source[@src]', 'src'],
        ['//source[@srcset]', 'srcset'],
        ['//video[@poster]', 'poster'],
        ['//video[@src]', 'src'],
        ['//audio[@src]', 'src'],
    ] as [$query, $attribute]) {
        foreach ($xpath->query($query) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $value = $node->getAttribute($attribute);

            if ($attribute === 'srcset') {
                $node->setAttribute($attribute, rewriteSrcset($value, $pageUrl, $assetDirectories, $assetManifest, $pageAssets));
                continue;
            }

            $absolute = absolutizeUrl($value, $pageUrl);
            if ($absolute === null) {
                continue;
            }

            $node->setAttribute($attribute, htmlAssetReference(downloadAsset($absolute, $assetDirectories, $assetManifest, $pageAssets)));
        }
    }

    foreach (['data-src', 'data-lazy-src', 'data-elementor-open-lightbox', 'data-background-lazyload'] as $attribute) {
        foreach ($xpath->query('//*[@' . $attribute . ']') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $absolute = absolutizeUrl($node->getAttribute($attribute), $pageUrl);
            if ($absolute === null) {
                continue;
            }

            $node->setAttribute($attribute, htmlAssetReference(downloadAsset($absolute, $assetDirectories, $assetManifest, $pageAssets)));
        }
    }

    foreach ($xpath->query('//a[@href]') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $resolved = absolutizeUrl($node->getAttribute('href'), $pageUrl);
        if ($resolved === null) {
            continue;
        }

        $normalized = normalizeUrl($resolved);
        if ($normalized !== null && isset($pagePathMap[$normalized])) {
            $node->setAttribute('href', $pagePathMap[$normalized]);
        }
    }

    foreach ($xpath->query('//form[@action]') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $resolved = absolutizeUrl($node->getAttribute('action'), $pageUrl);
        if ($resolved === null) {
            continue;
        }

        $normalized = normalizeUrl($resolved);
        if ($normalized !== null && isset($pagePathMap[$normalized])) {
            $node->setAttribute('action', $pagePathMap[$normalized]);
        }
    }

    $rewrittenHtml = $dom->saveHTML();
    if ($rewrittenHtml === false) {
        throw new RuntimeException("Unable to serialize DOM for {$pageUrl}.");
    }

    $rewrittenHtml = str_replace(['<?xml encoding="utf-8" ?>', '<html><body>', '</body></html>'], '', $rewrittenHtml);
    $rewrittenHtml = localizeRemainingAssetUrls($rewrittenHtml, $assetDirectories, $assetManifest, $pageAssets);
    $rewrittenHtml = localizeGoogleTagManagerUrls($rewrittenHtml, $assetDirectories, $assetManifest, $pageAssets);
    $rewrittenHtml = rewriteEscapedAssetUrls($rewrittenHtml, $assetManifest);
    $rewrittenHtml = decodeEncodedBladeMarkup($rewrittenHtml);

    return [$rewrittenHtml, $pageAssets];
}

function localizeRemainingAssetUrls(
    string $html,
    array $assetDirectories,
    array &$assetManifest,
    array &$pageAssets
): string {
    $pattern = '/https?:\\\\?\\/\\\\?\\/[^\\s"\'>()]+?\\.(?:css|js|mjs|png|jpe?g|webp|gif|svg|ico|woff2?|ttf|otf|eot|aspx)(?:\\?[^\\s"\'>()]*)?/i';

    return preg_replace_callback(
        $pattern,
        static function (array $matches) use ($assetDirectories, &$assetManifest, &$pageAssets): string {
            $matchedUrl = $matches[0];
            $normalizedUrl = str_replace('\\/', '/', $matchedUrl);
            $localPath = downloadAsset($normalizedUrl, $assetDirectories, $assetManifest, $pageAssets);
            $replacement = publicAssetReference($localPath);

            return str_contains($matchedUrl, '\\/')
                ? str_replace('/', '\\/', $replacement)
                : $replacement;
        },
        $html
    ) ?? $html;
}

function localizeGoogleTagManagerUrls(
    string $html,
    array $assetDirectories,
    array &$assetManifest,
    array &$pageAssets
): string {
    $pattern = "/'https:\\/\\/www\\.googletagmanager\\.com\\/gtm\\.js\\?id='\\+i\\+dl;(?:(?!<\\/script>).)*?\\)\\(window,document,'script','dataLayer','([A-Z0-9-]+)'\\);/is";

    return preg_replace_callback(
        $pattern,
        static function (array $matches) use ($assetDirectories, &$assetManifest, &$pageAssets): string {
            $assetUrl = 'https://www.googletagmanager.com/gtm.js?id=' . $matches[1];
            $localPath = downloadAsset($assetUrl, $assetDirectories, $assetManifest, $pageAssets);
            $replacementUrl = "'" . publicAssetReference($localPath) . "';";

            return preg_replace(
                "/'https:\\/\\/www\\.googletagmanager\\.com\\/gtm\\.js\\?id='\\+i\\+dl;/",
                $replacementUrl,
                $matches[0],
                1
            ) ?? $matches[0];
        },
        $html
    ) ?? $html;
}

function rewriteEscapedAssetUrls(string $html, array $assetManifest): string
{
    foreach ($assetManifest as $source => $target) {
        $html = str_replace($source, publicAssetReference($target), $html);
        $html = str_replace(str_replace('/', '\\/', $source), addslashes(publicAssetReference($target)), $html);
    }

    $html = str_replace([
        'https://buderus-gasthermenwartung.at/wp-admin/admin-ajax.php',
        'https:\/\/buderus-gasthermenwartung.at\/wp-admin\/admin-ajax.php',
        'https://buderus-gasthermenwartung.at/wp-json/',
        'https:\/\/buderus-gasthermenwartung.at\/wp-json\/',
    ], '#', $html);

    return $html;
}

function rewriteCssText(
    string $css,
    string $baseUrl,
    array $assetDirectories,
    array &$assetManifest,
    array &$pageAssets
): string {
    return preg_replace_callback(
        '/url\(([^)]+)\)/i',
        static function (array $matches) use ($baseUrl, $assetDirectories, &$assetManifest, &$pageAssets): string {
            $raw = trim($matches[1], " \t\n\r\0\x0B'\"");

            if ($raw === '' || str_starts_with($raw, 'data:') || str_starts_with($raw, '#')) {
                return $matches[0];
            }

            $absolute = absolutizeUrl($raw, $baseUrl);
            if ($absolute === null) {
                return $matches[0];
            }

            $localPath = downloadAsset($absolute, $assetDirectories, $assetManifest, $pageAssets);

            return 'url(' . publicAssetReference($localPath) . ')';
        },
        $css
    ) ?? $css;
}

function rewriteSrcset(
    string $srcset,
    string $baseUrl,
    array $assetDirectories,
    array &$assetManifest,
    array &$pageAssets
): string {
    $candidates = array_filter(array_map('trim', explode(',', $srcset)));
    $rewritten = [];

    foreach ($candidates as $candidate) {
        $parts = preg_split('/\s+/', $candidate, 2);
        $url = $parts[0] ?? '';
        $descriptor = $parts[1] ?? '';
        $absolute = absolutizeUrl($url, $baseUrl);

        if ($absolute === null) {
            $rewritten[] = $candidate;
            continue;
        }

        $localPath = downloadAsset($absolute, $assetDirectories, $assetManifest, $pageAssets);
        $rewritten[] = trim(htmlAssetReference($localPath) . ' ' . $descriptor);
    }

    return implode(', ', $rewritten);
}

function downloadAsset(
    string $assetUrl,
    array $assetDirectories,
    array &$assetManifest,
    array &$pageAssets
): string {
    $normalized = normalizeAssetUrl($assetUrl);

    if (isset($assetManifest[$normalized])) {
        $pageAssets[] = $assetManifest[$normalized];
        return $assetManifest[$normalized];
    }

    try {
        $response = fetchUrl($normalized);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, "Warning: {$exception->getMessage()}\n");
        return $normalized;
    }
    $localRelativePath = classifyAssetPath($normalized, $response['headers'], $assetDirectories);
    $targetPath = dirname(dirname($assetDirectories['css'])) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localRelativePath);

    ensureDirectory(dirname($targetPath));
    file_put_contents($targetPath, $response['body']);

    if (str_ends_with(strtolower($localRelativePath), '.css')) {
        $css = file_get_contents($targetPath);
        if ($css !== false) {
            file_put_contents($targetPath, rewriteCssText($css, $normalized, $assetDirectories, $assetManifest, $pageAssets));
        }
    }

    $assetManifest[$normalized] = $localRelativePath;
    $pageAssets[] = $localRelativePath;

    return $localRelativePath;
}

function classifyAssetPath(string $assetUrl, array $headers, array $assetDirectories): string
{
    $path = parse_url($assetUrl, PHP_URL_PATH) ?: '';
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $contentType = strtolower($headers['content-type'] ?? '');

    $category = match (true) {
        str_contains($contentType, 'text/css') || $extension === 'css' => 'css',
        str_contains($contentType, 'javascript'), str_contains($contentType, 'ecmascript'), in_array($extension, ['js', 'mjs'], true) => 'js',
        str_contains($contentType, 'font'), in_array($extension, ['woff', 'woff2', 'ttf', 'otf', 'eot'], true) => 'fonts',
        default => 'images',
    };

    $basename = basename($path);
    if ($basename === '' || $basename === '/') {
        $basename = $category === 'js' ? 'script.js' : ($category === 'css' ? 'style.css' : 'asset.bin');
    }

    $basename = sanitizeFilename(rawurldecode($basename));
    $name = pathinfo($basename, PATHINFO_FILENAME);
    $ext = pathinfo($basename, PATHINFO_EXTENSION);
    $hash = substr(sha1($assetUrl), 0, 10);
    $filename = $name . '-' . $hash . ($ext !== '' ? '.' . $ext : '');

    return 'assets/' . $category . '/' . $filename;
}

function buildPagePathMap(array $pageUrls): array
{
    $map = [];

    foreach ($pageUrls as $url) {
        $normalized = normalizeUrl($url);
        if ($normalized === null) {
            continue;
        }

        $path = urlPath($url);
        $map[$normalized] = $path;
    }

    return $map;
}

function fetchUrl(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: " . USER_AGENT . "\r\nAccept: */*\r\n",
            'timeout' => REQUEST_TIMEOUT,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    if ($body === false) {
        throw new RuntimeException("Failed to fetch {$url}");
    }

    $statusLine = $responseHeaders[0] ?? '';
    if (!preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
        throw new RuntimeException("Unable to determine response status for {$url}");
    }

    $statusCode = (int) $matches[1];
    if ($statusCode >= 400) {
        throw new RuntimeException("Received HTTP {$statusCode} for {$url}");
    }

    return [
        'body' => $body,
        'headers' => normalizeHeaders($responseHeaders),
    ];
}

function normalizeHeaders(array $headers): array
{
    $normalized = [];

    foreach ($headers as $header) {
        $parts = explode(':', $header, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $normalized[strtolower(trim($parts[0]))] = trim($parts[1]);
    }

    return $normalized;
}

function createDom(string $html): array
{
    $previous = libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return [$dom, new DOMXPath($dom)];
}

function absolutizeUrl(string $url, string $baseUrl): ?string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, 'javascript:')) {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    if (str_starts_with($url, '//')) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $url;
    }

    $baseParts = parse_url($baseUrl);
    if ($baseParts === false || !isset($baseParts['scheme'], $baseParts['host'])) {
        return null;
    }

    $basePath = $baseParts['path'] ?? '/';
    $baseDir = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';

    if (str_starts_with($url, '/')) {
        return $baseParts['scheme'] . '://' . $baseParts['host'] . $url;
    }

    return $baseParts['scheme'] . '://' . $baseParts['host'] . normalizePath($baseDir . $url);
}

function normalizeUrl(string $url): ?string
{
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }

    $path = $parts['path'] ?? '/';
    $path = normalizePath($path);

    if ($path !== '/') {
        $path = strtolower(rtrim($path, '/') . '/');
    }

    $normalized = strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . $path;

    if (isset($parts['query']) && $parts['query'] !== '') {
        $normalized .= '?' . $parts['query'];
    }

    return $normalized;
}

function normalizeAssetUrl(string $url): string
{
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return $url;
    }

    $path = normalizePath($parts['path'] ?? '/');
    $normalized = strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . $path;

    if (isset($parts['query']) && $parts['query'] !== '') {
        $normalized .= '?' . $parts['query'];
    }

    return $normalized;
}

function normalizePath(string $path): string
{
    $segments = [];

    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    return '/' . implode('/', $segments);
}

function isInternalPageUrl(string $url, ?string $host): bool
{
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['host']) || strtolower($parts['host']) !== strtolower((string) $host)) {
        return false;
    }

    $path = strtolower($parts['path'] ?? '/');

    if (preg_match('/\.(css|js|json|xml|jpg|jpeg|png|gif|webp|svg|ico|woff|woff2|ttf|otf|eot|pdf|mp4)$/', $path)) {
        return false;
    }

    if (str_contains($path, '/wp-admin/') || str_contains($path, '/wp-json/') || str_contains($path, '/feed/')) {
        return false;
    }

    if (str_contains($path, '/wp-content/') || str_contains($path, '/wp-includes/')) {
        return false;
    }

    return true;
}

function urlPath(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $path = normalizePath($path);

    return $path === '/' ? '/' : rtrim($path, '/') . '/';
}

function viewNameFromPath(string $path): string
{
    if ($path === '/') {
        return 'home';
    }

    return trim(str_replace('/', '-', $path), '-');
}

function sanitizeFilename(string $filename): string
{
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? $filename;
    $filename = trim($filename, '-');

    return $filename === '' ? 'asset' : $filename;
}

function bladeAsset(string $relativePath): string
{
    return "{{ asset('{$relativePath}') }}";
}

function htmlAssetReference(string $pathOrUrl): string
{
    return publicAssetReference($pathOrUrl);
}

function publicAssetReference(string $pathOrUrl): string
{
    if (str_starts_with($pathOrUrl, 'assets/')) {
        return '/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $pathOrUrl), '/');
    }

    return $pathOrUrl;
}

function decodeEncodedBladeMarkup(string $html): string
{
    return preg_replace_callback(
        '/%7B%7B.*?%7D%7D/i',
        static fn (array $matches): string => urldecode($matches[0]),
        $html
    ) ?? $html;
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create directory: {$directory}");
    }
}
