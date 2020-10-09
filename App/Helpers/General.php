<?php

namespace App\Helpers;

use Slim\Psr7\Response;

trait General
{

    /**
     * Json success response
     *
     * @param Response $response
     * @param array $data
     * @return Response
     */
    public function onSuccess(Response $response, array $data = []): Response
    {
        $response->getBody()->write(json_encode(array_merge([
            'status' => 'success',
        ], $data)));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Json error response
     *
     * @param Response $response
     * @param array $data
     * @return Response
     */
    public function onError(Response $response, array $data = []): Response
    {
        $response->getBody()->write(json_encode(array_merge([
            'status' => 'error',
        ], $data)));

        return $response->withHeader('Content-Type', 'application/json');
    }
}