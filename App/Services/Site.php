<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SiteException;
use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;

class Site
{

    /** @var DOMDocument $dom */
    private DOMDocument $dom;

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

        // this would be mapper function
        $this->dom = new DOMDocument();

        if (@$this->dom->loadHTML($response) === false) {
            throw new MapperException('Couldn\'t parse page content.');
        }
    }


    public function getMetaTags()
    {
        // Todo: asi rozbitie do array s 'description', keywords atd...
        return $this->dom->getElementsByTagName('meta');
    }
}