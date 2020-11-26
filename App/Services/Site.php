<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SiteException;
use DOMDocument;
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

    /**
     * Site constructor
     *
     * @param string $url
     * @throws SiteException
     */
    public function __construct(string $url)
    {
        $response = null;

        $request = new Request('GET', $url, [
            'timeout' => 6,
            'connection_timeout' => 6,
        ]);

        try {
            $client = new Client();
            $response = $client->send($request);

            if ($response->getStatusCode() !== 200) {
                throw new SiteException('Url: ' . $url . ' has returned `' . $response->getStatusCode() . '` code.');
            }

            // HTML code
            $response = $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new SiteException('Something bad happened.');
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
     * Parse meta tags
     *
     * @return array
     */
    public function getMetaTags(): array
    {
        // Todo: put more meta tags
        return [
            'description' => $this->xPath->evaluate('string(//meta[@name="description"]/@content)') ?: null,
            'keywords' => $this->xPath->evaluate('string(//meta[@name="keywords"]/@content)') ?: null,
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
}