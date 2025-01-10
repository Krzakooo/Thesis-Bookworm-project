<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;



$cacheDirectory = __DIR__ . '/cache';

$psr6CachePool = new FilesystemAdapter(
    namespace: '',
    defaultLifetime: 3600,
    directory: $cacheDirectory
);

$cache = new Psr16Cache($psr6CachePool);

return $cache;
