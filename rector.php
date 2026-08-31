<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/includes',
        __DIR__ . '/__loaded.php',
        __DIR__ . '/wp-tests-config.php',
    ])
    ->withSkip([
        ArrayToFirstClassCallableRector::class,
    ])
    ->withPhpSets(php85: true);
