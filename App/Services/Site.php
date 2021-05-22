<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SiteException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXpath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class Site
{

    public const ALLOWED_STATUS_CODES = [
        200,
        201,
        202,
        304,
    ];

    public string $baseUrl;

    public string $url;

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

        $this->url = $url;
        $this->baseUrl = $parts['scheme'] . '://' . $parts['host'];
        $this->robotsUrl = $this->baseUrl . '/robots.txt';
        $this->sitemapUrl = $this->baseUrl . '/sitemap.xml';

        $response = self::makeRequest($url);

        if (!$response) {
            throw new SiteException('Response is empty.');
        }

        // HTML code
        $response = $response->getBody()->getContents();

        $this->dom = new DOMDocument();

        if (@$this->dom->loadHTML($response) === false) {
            throw new MapperException('Could not parse page content.');
        }

        $this->xPath = new DOMXpath($this->dom);
    }

    /**
     * Return status code of URL
     *
     * @param string $url
     * @return int|null
     * @throws SiteException
     */
    public static function getStatusCodeOfUrl(string $url): ?int
    {
        $response = self::makeRequest($url);

        return $response ? $response->getStatusCode() : null;
    }

    /**
     * Make request
     *
     * @param string $url
     * @return mixed|ResponseInterface
     * @throws SiteException
     */
    public static function makeRequest(string $url)
    {
        try {
            $client = new Client([
                'timeout' => 6,
                'connection_timeout' => 6,
                'read_timeout' => 6,
            ]);

            return $client->request('GET', $url);
        } catch (GuzzleException $e) {
            throw new SiteException($e->getMessage());
        }
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
            if (in_array($response->getStatusCode(), self::ALLOWED_STATUS_CODES, true)) {
                $toReturn['hasRobots'] = true;
            }
        } catch (GuzzleException $e) {}

        // TODO: check for sitemap occurrence in robots.txt (It must be checked!!!) and get that url and check it, if it doesn't exist
        //   then check regular one at the end of robots.txt

        try {
            $client = new Client([
                'timeout' => 6,
                'connection_timeout' => 6,
                'read_timeout' => 6,
            ]);

            $response = $client->request('GET', $this->sitemapUrl);

            if (in_array($response->getStatusCode(), self::ALLOWED_STATUS_CODES, true)) {
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
            /** @var DOMNode|DOMElement  $item */
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

    public function getCanonicalUrl(): DOMNodeList
    {
        return $this->xPath->query('//link[@rel="canonical"]/@href');
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

    /**
     * Return `styles` on website
     *
     * @return DOMNodeList|null
     */
    public function getStyle(): ?DOMNodeList
    {
        $elements = $this->dom->getElementsByTagName('style');

        return $elements->length > 0 ? $elements : null;
    }

    /**
     * Return `scripts` on website
     *
     * @return DOMNodeList|null
     */
    public function getScript(): ?DOMNodeList
    {
        $elements = $this->dom->getElementsByTagName('script');

        return $elements->length > 0 ? $elements : null;
    }

    /**
     * Return images
     *
     * @return DOMNodeList
     */
    public function getImages(): DOMNodeList
    {
        return $this->dom->getElementsByTagName('img');
    }

    /**
     * Return <a> tags
     *
     * @return DOMNodeList
     */
    public function getATags(): DOMNodeList
    {
        return $this->dom->getElementsByTagName('a');
    }
}