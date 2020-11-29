<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SiteException;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXpath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;

class Site
{

    /** @var DOMDocument $dom */
    private DOMDocument $dom;

    /** @var DOMXpath $xPath */
    private DOMXpath $xPath;

    /** @var string $robotsUrl */
    private string $robotsUrl;

    /** @var string $sitemapUrl */
    private string $sitemapUrl;

    /**
     * Site constructor
     *
     * @param string $url
     * @throws SiteException
     */
    public function __construct(string $url)
    {
        $parts = parse_url($url);

        $baseUrl = $parts['scheme'] . '://' . $parts['host'];

        $this->robotsUrl = $baseUrl . '/robots.txt';
        $this->sitemapUrl = $baseUrl . '/sitemap.xml';

        $response = null;

        try {
            $client = new Client([
                'timeout' => 6,
                'connection_timeout' => 6,
                'read_timeout' => 6,
            ]);

            $response = $client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                throw new SiteException('Url: ' . $url . ' has returned `' . $response->getStatusCode() . '` code.');
            }

            // HTML code
            $response = $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new SiteException($e->getMessage());
        }

        if (!$response) {
            throw new SiteException('Response is empty.');
        }

        $this->dom = new DOMDocument();

        if (@$this->dom->loadHTML($response) === false) {
            throw new MapperException('Couldn\'t parse page content.');
        }

        $this->xPath = new DOMXpath($this->dom);
    }

    /**
     * Check if robots.txt and sitemap.xml exist
     */
    public function getRobotsAndSitemap(): array
    {
        $toReturn = [
            'hasRobots' => false,
            'hasSitemap' => false,
        ];

        try {
            $client = new Client([
                'timeout' => 6,
                'connection_timeout' => 6,
                'read_timeout' => 6,
            ]);

            $response = $client->request('GET', $this->robotsUrl);

            if ($response->getStatusCode() === 200) {
                $toReturn['hasRobots'] = true;
            }
        } catch (GuzzleException $e) {}

        // TODO: check for sitemap occurrence in robots.txt (It must be checked!!!) at the end of robots.txt

        try {
            $client = new Client([
                'timeout' => 6,
                'connection_timeout' => 6,
                'read_timeout' => 6,
            ]);

            $response = $client->request('GET', $this->sitemapUrl);

            if ($response->getStatusCode() === 200) {
                $toReturn['hasSitemap'] = true;
            }
        } catch (GuzzleException $e) {}

        return $toReturn;
    }

    /**
     * Return language
     *
     * @return string|null
     */
    public function getHtmlLang(): ?string
    {
        $lang = $this->xPath->query('//html[@lang]') ?: null;

        if (isset($lang) && $lang->item(0)) {
            /** @var DOMNode  $item */
            $item = $lang->item(0);

            $lang = $item ? $item->getAttribute('lang') : null;
        }

        return $lang;
    }

    /**
     * Parse meta tags
     *
     * @return array
     */
    public function getMetaTags(): array
    {


        // Todo: put more meta tags
        return [
            'description' => $this->xPath->query('//meta[@name="description"]'),
            'keywords' => $this->xPath->query('//meta[@name="keywords"]/@content'),
        ];
    }

//    private function mapFields(DOMNodeList $tags, $inputs)
//    {
//        var_dump($this->mapFields($metaTags, [
//            [
//                'name' => 'description',
//                'field' => 'content'
//            ],
//            [
//                'name' => 'keywords',
//                'field' => 'content'
//            ],
//        ]));
//
//        $toReturn = [];
//
//        foreach ($inputs as $field) {
//            /** @var DOMNode $tag */
//            foreach ($tags as $tag) {
//                $toReturn += [
//                    $field['name'] => null,
//                ];
//                $fieldName = $tag->attributes->getNamedItem($field['name']);
//                $fieldValue = $tag->attributes->getNamedItem($field['field']);
//
//
//                if (isset($fieldName)) {
//                    $toReturn[$field['name']] = $fieldValue->nodeValue ?? null;
//                }
//
//            }
//        }
//
//        return $toReturn;
//    }

    /**
     * Return title tag
     *
     * @return DOMNodeList|false
     */
    public function getTitleTag()
    {
        return $this->xPath->query('//title');
    }

    public function getHeadings(): array
    {
        return [
            'h1' => $this->dom->getElementsByTagName('h1'),
            'h2' => $this->dom->getElementsByTagName('h2'),
            'h3' => $this->dom->getElementsByTagName('h3'),
        ];
    }
}