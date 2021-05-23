<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\SeoException;
use App\Exceptions\SiteException;
use App\Helpers\General;
use App\Services\SeoService;
use JsonException;
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

    /**
     * [POST] Analyze
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws JsonException
     * @throws SeoException
     * @throws SiteException
     */
    public function analyze(Request $request, Response $response): Response
    {
        $url = $request->getParsedBody()['url'] ?? null;

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Website is wrong.');
        }

        $checkAllLinks = $request->getParsedBody()['checkAllLinks'] ?? false;

        $seoService = new SeoService($url, [
            'checkAllLinks' => (bool)$checkAllLinks,
        ]);

        $seoService->scan();

        return $this->onSuccess($response, [
            'data' => $seoService->getResults(),
        ]);
    }
}
