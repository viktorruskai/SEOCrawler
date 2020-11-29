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
    public const MAX_TITLE_CHARS = 63;

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

        $this->checkRobotsAndSitemap();
        $this->validateHtmlTag();
        $this->validateHeadTags();
//        $this->validateOnPageStyle();
//        $this->validateOnPageJavaSript();
//        $this->validateBody();
        $this->validateHeadings();
//        $this->validateImages();
//        $this->checkLinks();
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
            'problems' => $this->problems->mapByType(),
        ];
    }

    /**
     * Check if robots.txt and sitemap.xml exist
     */
    public function checkRobotsAndSitemap(): void
    {
        $site = $this->site->getRobotsAndSitemap();

        if (!$site['hasRobots']) {
            $this->addProblem('robots.txt', 'Robots.txt is missing', self::HIGH_IMPORTANCE);
        }

        if (!$site['hasSitemap']) {
            $this->addProblem('sitemap.xml', 'Sitemap.txt is missing', self::HIGH_IMPORTANCE);
        }
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
     * Validate all meta, script, style tags and title tag
     */
    private function validateHeadTags(): void
    {
        // Meta tags
        $meta = $this->site->getMetaTags();

        /** @var DOMNodeList $description */
        $description = $meta['description'];

        if ($description->count() === 1) {
            if (strlen($description->item(0)->nodeValue) > self::MAX_META_DESCRIPTION_CHARS) {
                $this->addProblem('meta.description', '', self::MEDIUM_IMPORTANCE);
            }
        } else if ($description->count() > 1) {
            $this->addProblem('meta.description', 'Multiple meta descriptions detected.', self::HIGH_IMPORTANCE);
        } else {
            $this->addProblem('meta.description', 'Meta description is missing.', self::HIGH_IMPORTANCE);
        }

        /** @var DOMNodeList $keywords */
        $keywords = $meta['keywords'];

        if ($keywords->count() === 1) {
            if ($keywords->item(0)->nodeValue === '') {
                $this->addProblem('meta.keywords', 'Meta keywords can\'t be empty.', self::MEDIUM_IMPORTANCE);
            }
        } else if ($keywords->count() > 1) {
            $this->addProblem('meta.keywords', 'Multiple meta keywords tags detected.', self::HIGH_IMPORTANCE);
        } else {
            $this->addProblem('meta.keywords', 'Meta tag with keywords is missing.', self::HIGH_IMPORTANCE);
        }

        // Title tag
        $title = $this->site->getTitleTag();

        if ($title->count() === 1) {
            if (strlen($title->item(0)->nodeValue) > self::MAX_TITLE_CHARS) {
                $this->addProblem('title', 'Title should have max. ' . self::MAX_TITLE_CHARS . ' characters (You have ' . strlen($title->item(0)->nodeValue) . ' characters)', self::MEDIUM_IMPORTANCE);
            }
        } else if ($title->count() > 1) {
            $this->addProblem('title', 'Multiple title tags detected.', self::HIGH_IMPORTANCE);
        } else {
            $this->addProblem('title', 'Title tag is missing.', self::HIGH_IMPORTANCE);
        }
    }

    public function validateBody()
    {

    }

    /**
     * Validate headings
     */
    public function validateHeadings(): void
    {
        $headings = $this->site->getHeadings();

        if ($headings['h1']->count() > 1) {
            $this->addProblem('h1', 'Multiple h1 tags detected.', self::HIGH_IMPORTANCE);
        } else if ($headings['h1']->count() !== 1) {
            $this->addProblem('h1', 'H1 tag is missing.', self::HIGH_IMPORTANCE);
        }

        if ($headings['h2']->count() === 0) {
            $this->addProblem('h2', 'H2 tags is missing.', self::HIGH_IMPORTANCE);
        }

        if ($headings['h3']->count() === 0) {
            $this->addProblem('h3', 'H3 tags is missing.', self::HIGH_IMPORTANCE);
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