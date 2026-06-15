<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

$_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = '1';
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'test';

$kernel = new ITKDev\EntityBundle\Tests\App\Kernel('test', true);
$kernel->boot();
