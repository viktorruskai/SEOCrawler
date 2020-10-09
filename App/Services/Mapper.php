<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MapperException;
use DOMDocument;
use DOMNodeList;
use DOMXPath;

/**
 * Class Mapper
 *
 * @package App\Services
 */
class Mapper
{

    /** @var DOMDocument $dom */
    private $dom;

    /**
     * @var DOMNodeList $aTags
     */
    private $aTags;

    /**
     * @var DOMNodeList $imgTags
     */
    private $imgTags;

    /**
     * @var DOMNodeList $scriptTags
     */
    private $scriptTags;

    /**
     * @var DOMNodeList $linkTags
     */
    private $linkTags;

    /**
     * @var DOMNodeList $iframeTags
     */
    private $iframeTags;

    /**
     * @var DOMNodeList $videoTags
     */
    private $videoTags;

    /**
     * @var DOMNodeList $audioTags
     */
    private $audioTags;

    /**
     * @var DOMNodeList $forms
     */
    private $forms;

    /**
     * @var DOMNodeList $h1Tags
     */
    private $h1Tags;

    /**
     * @var DOMNodeList $h2Tags
     */
    private $h2Tags;

    /**
     * @var DOMNodeList $h3Tags
     */
    private $h3Tags;

    /**
     * @var DOMNodeList|false $ids
     */
    private $ids;

    /**
     * @var DOMNodeList $title
     */
    private $title;

    /**
     * @var DOMNodeList $metaTags
     */
    private $metaTags;

    /**
     * Mapper constructor.
     *
     * @param string $page
     * @throws MapperException
     */
    public function __construct(string $page)
    {
        $this->dom = new DOMDocument();

        if (@$this->dom->loadHTML($page) === false) {
            throw new MapperException('Couldn\'t parse page content.');
        }
    }

    /**
     * Map the page
     *
     * @return void
     */
    public function do(): void
    {
        // `title`
        $this->title = $this->dom->getElementsByTagName('title');

        // `a` tags
        $this->aTags = $this->dom->getElementsByTagName('a');

        // `img` tags
        $this->imgTags = $this->dom->getElementsByTagName('img');

        // `script` tags
        $this->scriptTags = $this->dom->getElementsByTagName('script');

        // `link` tags
        $this->linkTags = $this->dom->getElementsByTagName('link');

        // `iframe` tags
        $this->iframeTags = $this->dom->getElementsByTagName('iframe');

        // `video` tags
        $this->videoTags = $this->dom->getElementsByTagName('video');

        // `audio` tags
        $this->audioTags = $this->dom->getElementsByTagName('audio');

        // `h1, h2, h3` tags
        $this->h1Tags = $this->dom->getElementsByTagName('h1');
        $this->h2Tags = $this->dom->getElementsByTagName('h2');
        $this->h3Tags = $this->dom->getElementsByTagName('h3');

        // `form` tags
        $this->forms = $this->dom->getElementsByTagName('form');

        // `meta` tags
        $this->metaTags = $this->dom->getElementsByTagName('meta');

        // All `ids`
        $xpath = new DOMXPath($this->dom);
        $this->ids = $xpath->query('//*[@id]');
    }

    /**
     * @return DOMNodeList
     */
    public function getATags(): DOMNodeList
    {
        return $this->aTags;
    }

    /**
     * @return DOMNodeList
     */
    public function getImgTags(): DOMNodeList
    {
        return $this->imgTags;
    }

    /**
     * @return DOMNodeList
     */
    public function getScriptTags(): DOMNodeList
    {
        return $this->scriptTags;
    }

    /**
     * @return DOMNodeList
     */
    public function getLinkTags(): DOMNodeList
    {
        return $this->linkTags;
    }

    /**
     * @return DOMNodeList
     */
    public function getIframeTags(): DOMNodeList
    {
        return $this->iframeTags;
    }

    /**
     * @return DOMNodeList
     */
    public function getVideoTags(): DOMNodeList
    {
        return $this->videoTags;
    }

    /**
     * @return DOMNodeList
     */
    public function getAudioTags(): DOMNodeList
    {
        return $this->audioTags;
    }

    /**
     * @return DOMNodeList
     */
    public function getForms(): DOMNodeList
    {
        return $this->forms;
    }

    /**
     * @return DOMNodeList
     */
    public function getH1Tags(): DOMNodeList
    {
        return $this->h1Tags;
    }

    /**
     * @return DOMNodeList
     */
    public function getH2Tags(): DOMNodeList
    {
        return $this->h2Tags;
    }

    /**
     * @return DOMNodeList
     */
    public function getH3Tags(): DOMNodeList
    {
        return $this->h3Tags;
    }

    /**
     * @return DOMNodeList|false
     */
    public function getIds()
    {
        return $this->ids;
    }

    /**
     * Get duplicate values
     *
     * @return array
     */
    public function getDuplicateIds(): array
    {
        if (!$this->ids) {
            return [];
        }

        $toCompare = [];

        foreach ($this->ids as $id) {
            $toCompare[] = $id->getAttribute('id');
        }

        $toReturn = [];

        foreach (array_count_values($toCompare) as $val => $c) {
            if ($c > 1) {
                $toReturn[$val] = $c;
            }
        }

        return $toReturn;
    }

    /**
     * @return DOMNodeList
     */
    public function getTitle(): DOMNodeList
    {
        return $this->title;
    }

    /**
     * @return DOMNodeList
     */
    public function getMetaTags(): DOMNodeList
    {
        return $this->metaTags;
    }
}
