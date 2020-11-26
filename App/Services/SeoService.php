<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SeoException;
use App\Exceptions\SiteException;
use App\Helpers\Collection;

class SeoService
{
    public const HIGH_IMPORTANCE = 3;
    public const MEDIUM_IMPORTANCE = 2;
    public const LOW_IMPORTANCE = 1;

    /** @var string $url */
    private string $url;

    /** @var Site $site */
    private Site $site;

    /** @var Collection $problems */
    private Collection $problems;

    /**
     * SeoService constructor
     *
     * @param string $url
     * @param array $data
     * @throws SeoException
     */
    public function __construct(string $url, array $data = [])
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new SeoException('Website is wrong.');
        }

        $parts = parse_url($url);

        $this->url = $url;
        $this->problems = new Collection();
//        $this->baseUrl = $parts['scheme'] . '://' . $parts['host'];
//
//        $this->robotsPath = $this->baseUrl . '/robots.txt';
//        $this->sitemapPath = $this->baseUrl . '/sitemap.xml';
//        $this->client = $data['client'];
//        $this->isCrawl = $data['isCrawl'] ?? false;
//        $this->startAt = time();
    }

    /**
     * Scan the page
     *
     * @throws SiteException
     */
    public function scan(): void
    {
        $this->site = new Site($this->url);

        $this->validateMetaTags();
//        $this->validateOnPageStyle();
//        $this->validateOnPageJavaSript();
//        $this->validateBody();
//        $this->validateHeadings();
//        $this->valdiateImages();
    }

    /**
     * Return results for response
     *
     * @return array
     */
    public function getResults(): array
    {
        return [
            'isSeoGood' => true, // Todo: to decide
            'seoRate' => $this->problems->averageImportance(),
            'websiteName' => $this->url,
            'problems' => $this->problems,
        ];
    }

    /**
     * Validate all meta tags
     */
    private function validateMetaTags(): void
    {
        // Todo: tu sa budu validatovt meta tagy ... ci description nie je velka atd.
        $meta = $this->site->getMetaTags();

        if (!$meta['description']) {
            $this->addProblem('meta.description', 'Description is missing', self::HIGH_IMPORTANCE);
        }
    }

    /**
     * Add problem
     *
     * @param string $type
     * @param string $message
     * @param int $importance
     * @param array $optionalData
     */
    private function addProblem(string $type, string $message, int $importance, array $optionalData = []): void
    {
        $this->problems->append([
            'type' => $type,
            'message' => $message,
            'importance' => $importance,
        ] + $optionalData);
    }
}