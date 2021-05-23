<?php

use App\Controllers\AnalyzeController;
use Slim\App;

return static function (App $app) {
    $app->post('/analyze', [AnalyzeController::class, 'analyze'])->setName('analyze');

    $app->options('/{routes:.+}', function ($request, $response) {
        return $response;
    });
};