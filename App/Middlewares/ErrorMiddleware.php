<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Exceptions\SeoException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Throwable;
use function Sentry\captureException;
use function Sentry\init;

class ErrorMiddleware
{
    private const NOT_LOGGED_EXCEPTIONS = [
        SeoException::class,
    ];

    /** @var array $options */
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Handle error
     *
     * @param ServerRequestInterface $request
     * @param Throwable $exception
     * @param bool $displayErrorDetails
     * @param bool $logErrors
     * @param bool $logErrorDetails
     * @param LoggerInterface|null $logger
     * @return Response
     * @throws JsonException
     */
    public function __invoke(ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails, ?LoggerInterface $logger = null)
    {
        $isLogged = true;

        if (in_array(get_class($exception), self::NOT_LOGGED_EXCEPTIONS, true)) {
            $isLogged = false;
        }

        if ($logger && $isLogged) {
            $logger->error($exception->getMessage());
        }

        init($this->options);
        captureException($exception);

        $response = new Response();
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $exception->getMessage(),
            'code' => $exception->getCode() ?: 500,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response;
    }
}