<?php declare(strict_types=1);

$paths = [
    './bootstrap/app.php',
    __DIR__ . '/../../../bootstrap/app.php',
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        $app = require $path;
    }
}

if (!isset($app)) {
    throw new Exception('Could not find app boostrap, tried: ' . implode(', ', $paths));
}

if ($app instanceof Illuminate\Foundation\Application) {
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}
