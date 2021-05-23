<?php

use App\Middlewares\CorsMiddleware;
use App\Middlewares\ErrorMiddleware;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;

// Autoloader
require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session
//session_start();

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

// Should be set to true in production
if (isset($_ENV['environment']) && $_ENV['environment'] === 'production') {
    $containerBuilder->enableCompilation(__DIR__ . '/Cache');
}

// Set up settings
$settings = require __DIR__ . '/Config/base.php';
$containerBuilder->addDefinitions($settings);

//Set up dependencies
$dependencies = static function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        'logger' => static function (ContainerInterface $c) {
            $settings = $c->get('settings')['logger'];
            $logger = new Logger($settings['name']);
            $logger::setTimezone(new DateTimeZone('Europe/Bratislava'));
            $logger->pushProcessor(new UidProcessor());
            $logger->pushHandler(new StreamHandler($settings['path'], $settings['level']));
            return $logger;
        },
    ]);
};

$dependencies($containerBuilder);

// Build PHP-DI Container instance
try {
    $container = $containerBuilder->build();
} catch (Exception $e) {
    return json_encode([
        'error' => 'Container',
        'code' => 500,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// CORS middleware
$app->add(new CorsMiddleware());

// Error middleware
$errorMiddleware = new ErrorMiddleware($settings['settings']['sentry'] ?? []);

// Error handler
$errorHandler = $app->addErrorMiddleware(true, true, true);
$errorHandler->setDefaultErrorHandler($errorMiddleware);

// App routes
$routes = require __DIR__ . '/routes.php';
$routes($app);

if (isset($_ENV['environment']) && $_ENV['environment'] === 'production') {
    $routeCollector = $app->getRouteCollector();
    $routeCollector->setCacheFile($settings['settings']['routerCacheFile']);
}

// Run the app
return $app;
