<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SeoException;
use App\Exceptions\SiteException;
use App\Helpers\Collection;
use DOMNodeList;

class SeoService
{
    public const HIGH_IMPORTANCE = 3;
    public const MEDIUM_IMPORTANCE = 2;
    public const LOW_IMPORTANCE = 1;
    public const MAX_META_DESCRIPTION_CHARS = 160;

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

        $this->validateHtmlTag();
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
     * Validate `lang` in <html> tag
     */
    private function validateHtmlTag(): void
    {
        if (!$this->site->getHtmlLang()) {
            $this->addProblem('html.lang', 'Language is missing', self::HIGH_IMPORTANCE);
        }
    }

    /**
     * Validate all meta tags
     */
    private function validateMetaTags(): void
    {
        // Todo: tu sa budu validatovt meta tagy ... ci description nie je velka atd.
        $meta = $this->site->getMetaTags();

        /** @var DOMNodeList $description */
        $description = $meta['description'];

        if ($description->count() === 1) {
            if (strlen($description->item(0)->nodeValue) > self::MAX_META_DESCRIPTION_CHARS) {
                $this->addProblem('meta.description', 'Multiple descriptions detected', self::MEDIUM_IMPORTANCE);
            }
        } else if ($description->count() > 1) {
            $this->addProblem('meta.description', 'Multiple descriptions detected', self::HIGH_IMPORTANCE);
        } else {
            $this->addProblem('meta.description', 'Description is missing', self::HIGH_IMPORTANCE);
        }

//        if ($meta['description'] > )

        if (!$meta['keywords']) {
            $this->addProblem('meta.keywords', 'Keywords are missing', self::HIGH_IMPORTANCE);
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