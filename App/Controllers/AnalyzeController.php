<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\SeoException;
use App\Helpers\General;
use App\Services\SeoService;
use GuzzleHttp\Client;
use JsonException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Class IndexController
 *
 * @package App\Controllers
 */
class AnalyzeController
{
    use General;

    /** @var ContainerInterface $container */
    private ContainerInterface $container;

    /**
     * Set view for all controllers
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * [POST] Analyze
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws JsonException
     * @throws SeoException
     */
    public function analyze(Request $request, Response $response): Response
    {
        $url = $request->getParsedBody()['url'] ?? null;

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Website is wrong.');
        }

        $seoService = new SeoService($url);

        $seoService->scan();

        $seoService->getResults();

        return $this->onSuccess($response, [
            'data' => [
                'isSeoGood' => true,
                'websiteName' => $url,
                'problems' => [

                ],
            ],
        ]);

//        $url = 'https://www.google.com';
//
//        try {
//            $seoService = new SeoService($url, [
//                'client' => new Client(),
//                'isCrawl' => true,
//            ]);
//
//            // This function handles SEO on the page
//            $seoService->do();
//
//            // var_dump($seoService->getMessages());
//            // exit;
//
//        } catch (SeoException $e) {
//            $this->container->get('logger')->addError($e->getMessage(), [
//                'file' => $e->getFile(),
//                'line' => $e->getLine(),
//                'trace' => $e->getTraceAsString(),
//            ]);
//
//            return $this->onError($response, [
//                'message' => $e->getMessage(),
//            ]);
//        }
//
//        return $this->onSuccess($response);
    }
}
