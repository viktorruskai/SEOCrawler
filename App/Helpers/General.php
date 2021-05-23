<?php
declare(strict_types=1);

namespace App\Helpers;

use JsonException;
use Psr\Http\Message\ResponseInterface as Response;

trait General
{

    /**
     * Json success response
     *
     * @param Response $response
     * @param array $data
     * @param string|null $message
     * @return Response
     * @throws JsonException
     */
    public function onSuccess(Response $response, array $data = [], ?string $message = null): Response
    {
        $toReturn = [
            'status' => 'success',
        ];

        if ($message) {
            $toReturn['message'] = $message;
        }

        $response->getBody()->write(json_encode(array_merge($toReturn, $data), JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Json error response
     *
     * @param Response $response
     * @param array $data
     * @param string|null $message
     * @return Response
     * @throws JsonException
     */
    public function onError(Response $response, array $data = [], ?string $message = null): Response
    {
        $toReturn = [
            'status' => 'error',
        ];

        if ($message) {
            $toReturn['message'] = $message;
        }

        $response->getBody()->write(json_encode(array_merge($toReturn, $data), JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}