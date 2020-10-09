<?php

use Slim\App;
use App\Controllers\IndexController;

return static function (App $app) {
    $app->get('/', IndexController::class .':home')->setName('home');
    $app->get('/test', function ($request, $response) {
        return $response->withStatus(200);
    })->setName('home');
};