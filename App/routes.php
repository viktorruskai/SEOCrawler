<?php

use App\Controllers\AnalyzeController;
use Slim\App;
use App\Controllers\IndexController;

return static function (App $app) {
    $app->get('/', IndexController::class . ':home')->setName('home');
    $app->post('/analyze', AnalyzeController::class . ':analyze')->setName('analyze');
};