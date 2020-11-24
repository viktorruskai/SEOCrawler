<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SeoException;

class SeoService
{
    /** @var string $url */
    private string $url;

    /** @var Site $site */
    private Site $site;

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
        $this->baseUrl = $parts['scheme'] . '://' . $parts['host'];

        $this->robotsPath = $this->baseUrl . '/robots.txt';
        $this->sitemapPath = $this->baseUrl . '/sitemap.xml';
        $this->client = $data['client'];
        $this->isCrawl = $data['isCrawl'] ?? false;
        $this->startAt = time();
    }

    public function scan() {
        $this->site = new Site($this->url);

        $this->validateMetaTags();
    }

    private function validateMetaTags()
    {
        // Todo: tu sa budu validatovt meta tagy ... ci description nie je velka atd.
        $this->site->getMetaTags();
    }

}