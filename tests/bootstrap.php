<?php

declare(strict_types=1);

// Find the autoloader. The bundle is most often consumed via a path repo from a
// parent Symfony app, in which case we use that parent's vendor/. Fall back to
// the bundle's own vendor/ if `composer install` was run inside the bundle.
$candidates = [
    \dirname(__DIR__).'/vendor/autoload.php',
    \dirname(__DIR__, 3).'/vendor/autoload.php',
];

foreach ($candidates as $path) {
    if (is_file($path)) {
        require $path;

        return;
    }
}

fwrite(STDERR, "Could not locate vendor/autoload.php for the bundle tests.\n");
exit(1);
