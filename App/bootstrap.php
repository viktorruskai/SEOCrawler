<?php

use App\Middlewares\ErrorMiddleware;
use DI\ContainerBuilder;
use Illuminate\Database\Capsule\Manager;
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

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Error middleware
$errorMiddleware = new ErrorMiddleware([
    'dsn' => 'https://d18a4b7b04284e98bd8dc862326645f9@o338677.ingest.sentry.io/5523633',
    'environment' => 'local',
]);

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

// Boot database
$capsule = new Manager;
$capsule->addConnection($settings['settings']['db']);
$capsule->bootEloquent();
$capsule->setAsGlobal();

// Run the app
return $app;
