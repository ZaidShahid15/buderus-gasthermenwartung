<?php

use App\Support\StaticPageRenderer;
use App\Support\LiveStyleSitemap;
use Illuminate\Support\Facades\Route;

$staticPages = [
    '' => 'home',
    'datenschutz' => 'datenschutz',
    'elementor-hf/19' => 'elementor-hf-19',
    'elementor-hf/footer' => 'elementor-hf-footer',
];

Route::get('/sitemap_index.xml', static fn () => LiveStyleSitemap::indexResponse());
Route::get('/wp-sitemap.xml', static fn () => LiveStyleSitemap::legacyIndexResponse());
Route::get('/page-sitemap.xml', static fn () => LiveStyleSitemap::pageResponse());
Route::get('/elementor-hf-sitemap.xml', static fn () => LiveStyleSitemap::elementorResponse());
Route::get('/robots.txt', static fn () => response()->file(public_path('robots.txt'), ['Content-Type' => 'text/plain; charset=UTF-8']));
Route::get('/llms.txt', static fn () => response()->file(public_path('llms.txt'), ['Content-Type' => 'text/plain; charset=UTF-8']));

Route::get('/{path?}', function (?string $path = null) use ($staticPages) {
    $normalized = trim($path ?? '', '/');

    abort_unless(isset($staticPages[$normalized]), 404);

    return response(StaticPageRenderer::render($staticPages[$normalized]));
})->where('path', '.*');
