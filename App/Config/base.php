<?php

use Monolog\Logger;

$cachePath = __DIR__ . '/../../App/Cache';

$config = [
    'settings' => [
        'displayErrorDetails' => true,
        'addContentLengthHeader' => false,

        'determineRouteBeforeAppMiddleware' => true,
        'cachePath' => $cachePath,
        'routerCacheFile' => $cachePath . '/routers.php',

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../../Logs/app.log',
            'level' => Logger::DEBUG,
        ],

        'sentry' => [
            'dsn' => '',
            'environment' => '',
        ],
    ],
];

if (file_exists(__DIR__ . '/local.php')) {
    $local = require __DIR__ . '/local.php';

    $config = array_replace_recursive($config, $local);
}

return $config;
