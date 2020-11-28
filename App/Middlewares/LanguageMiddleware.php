<?php

namespace App\Middlewares;

use Illuminate\Contracts\Translation\Translator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class LanguageMiddleware
{
    /** @var Translator $translator */
    private $translator;

    /** @var ContainerInterface $container */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->translator = $container->get('translator');
    }

    /**
     * Retrieves the current locale from (in the given order):
     *
     * - the HTTP request header
     * - the locale stored in the session
     * - the default locale
     *
     * The translator is set to the current locale and the locale is passed
     * as a request attribute.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return ResponseInterface
     */
    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {
        // $language = $_SESSION['language'] ?? null;
        $language = null;

        if (!$language) {
            if ($request->getHeaderLine('Accept-Language') !== '') {
                $acceptLanguage = $request->getHeaderLine('Accept-Language');
                $acceptLanguage = strtolower(substr($acceptLanguage, 0, 2));

                if (in_array($acceptLanguage, $this->container->get('settings')['allowedLanguages'], true)) {
                    $language = $acceptLanguage;
                }
            }

            $_SESSION['language'] = $language ?? $this->container->get('settings')['defaultLanguage'];
        }

        $this->translator->setLocale($language);
        $this->translator->setFallback($this->container->get('settings')['defaultLanguage']);

        $request = $request->withAttribute('language', $language);

        return $handler->handle($request);
    }
}