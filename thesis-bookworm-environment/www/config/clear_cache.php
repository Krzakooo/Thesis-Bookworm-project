<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/cache.php';

$cache = require __DIR__ . '/cache.php';

if ($cache->clear()) {
    echo "Cache cleared successfully.\n";
} else {
    echo "Failed to clear cache.\n";
}