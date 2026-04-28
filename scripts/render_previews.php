<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$targets = [
    '/' => __DIR__ . '/../storage/app/preview-home.html',
    '/datenschutz/' => __DIR__ . '/../storage/app/preview-datenschutz.html',
];

foreach ($targets as $uri => $outputPath) {
    $request = Illuminate\Http\Request::create($uri);
    $response = $kernel->handle($request);

    file_put_contents($outputPath, $response->getContent());

    $kernel->terminate($request, $response);
}

echo "Rendered previews.\n";
