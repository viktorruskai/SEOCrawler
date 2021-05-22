<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SeoException;
use App\Exceptions\SiteException;
use App\Helpers\Collection;
use DOMElement;
use DOMNode;
use DOMNodeList;

class SeoService
{
    public const HIGH_IMPORTANCE = 3;
    public const MEDIUM_IMPORTANCE = 2;
    public const LOW_IMPORTANCE = 1;
    public const MAX_META_DESCRIPTION_CHARS = 160;
    public const MAX_TITLE_CHARS = 63;
    public const MAX_INLINE_CHARACTERS_IN_SCRIPT_TAG = 1500;
    public const MAX_ALLOWED_SCRIPT_TAGS = 10;
    public const MAX_ALLOWED_INTERNAL_SCRIPT_TAGS = 4;
    public const MAX_ALLOWED_INLINE_SCRIPT_TAGS = 4;

    protected Collection $externalLinks;
    protected Collection $internalLinks;

    /** @var string $url */
    private string $url;

    /** @var Site $site */
    private Site $site;

    /** @var Collection $problems */
    private Collection $problems;

    private Collection $informations;

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

        $this->url = $url;
        $this->problems = new Collection();
        $this->informations = new Collection();
        $this->externalLinks = new Collection();
        $this->internalLinks = new Collection();
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

        $this->addInformation('requestTime', null, ['time' => round($this->site->requestTime, 2)]);

        $this->checkRobotsAndSitemap();
        $this->validateHtmlTag();
        $this->validateHeadTags();
        $this->validateOnPageStyle();
        $this->validateOnPageJavaScript();
        $this->validateHeadings();
        $this->validateImages();
        $this->checkLinks();
    }




    // TODO: https://medium.com/@devbattles/add-more-seo-in-user-created-html-with-php-dom-methods-790e818759a8 check functions

    // Todo: premenovat všetky priečinky na prve pismeno veľké


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
            'informations' => $this->informations->mapBy('type'),
            'results' => $this->problems->mapBy(null, 'importance'),
        ];
    }

    /**
     * Check if robots.txt and sitemap.xml exist
     */
    public function checkRobotsAndSitemap(): void
    {
        $site = $this->site->getRobotsAndSitemap();

        $this->addInformation('robots.txt', !$site['hasRobots'] ? 'Robots.txt is missing' : null, [
            'isMissing' => !$site['hasRobots'],
            'recommendation' => !$site['hasRobots'] ? 'You should create robots.txt.' : null,
        ]);

        $this->addInformation('sitemap.xml', !$site['hasSitemap'] ? 'Sitemap.xml is missing' : null, [
            'isMissing' => !$site['hasSitemap'],
            'recommendation' => !$site['hasSitemap'] ? 'You should create sitemap.xml.' : null,
        ]);
    }

    /**
     * Validate `lang` in <html> tag
     */
    private function validateHtmlTag(): void
    {
        if (!$this->site->getHtmlLang()) {
            $this->addProblem('html.lang', 'Language is missing', self::HIGH_IMPORTANCE, 1);
        }
    }

    /**
     * Validate all <meta>, <script>, <style> tags and title tag
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
            $this->addProblem('meta.description', 'Multiple meta descriptions detected.', self::HIGH_IMPORTANCE, 1);
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
            $this->addProblem('title', 'Multiple title tags detected.', self::HIGH_IMPORTANCE, 1);
        } else {
            $this->addProblem('title', 'Title tag is missing.', self::HIGH_IMPORTANCE);
        }
    }

    /**
     * Check if <style> tag exists
     */
    public function validateOnPageStyle(): void
    {
        $styles = $this->site->getStyle();

        if (!is_null($styles)) {
            $this->addProblem('style', /** @lang text */ 'You should not use <style> tag in HTML. (Found ' . $styles->count() . ($styles->count() === 1 ? ' time' : ' times') . ')', self::MEDIUM_IMPORTANCE);
        }
    }

    /**
     * Check if <script> tag exists
     */
    public function validateOnPageJavaScript(): void
    {
        $scripts = $this->site->getScript();

        if ($scripts === null) {
            return;
        }

        $countScriptTags = 0;
        $countInternalScriptTags = 0;
        $countInlineScriptTags = 0;

        // Get canonical URL
        $canonicalUrl = $this->validateCanonicalUrl(true);

        /** @var DOMNode|DOMElement $script */
        foreach ($scripts as $script) {
            $countScriptTags++;

            $javaScriptFile = $script->getAttribute('src');

            if ($javaScriptFile !== '') {
                if ($this->isExternalUrl($javaScriptFile, $this->site->baseUrl)) {
                    $this->externalLinks->append($javaScriptFile);
                } else {
                    $countInternalScriptTags++;
                    $this->internalLinks->append($this->joinUrlAndPath($canonicalUrl, $javaScriptFile));
                }
                continue;
            }

            $countInlineScriptTags++;

            if (strlen($script->nodeValue) >= self::MAX_INLINE_CHARACTERS_IN_SCRIPT_TAG) {
                $this->addProblem('script', /** @lang text */ 'Your <script> tag exceed recommended size. (' . self::MAX_INLINE_CHARACTERS_IN_SCRIPT_TAG . ' characters - yours is ' . strlen($script->nodeValue) . ')', self::LOW_IMPORTANCE, 5,['snippet' => substr($script->nodeValue, 0, 200) . '...']);
            }
        }

        if ($countScriptTags > self::MAX_ALLOWED_SCRIPT_TAGS) {
            $this->addProblem('script', /** @lang text */ 'You should not use too many <script> tags in HTML. (Found ' . $countScriptTags . ($countScriptTags === 1 ? ' time' : ' times') . ')', self::LOW_IMPORTANCE, 10);
        }

        if ($countInternalScriptTags > self::MAX_ALLOWED_INTERNAL_SCRIPT_TAGS) {
            $this->addProblem('script', /** @lang text */ 'You should not use too many internal <script> tags. Consider, joining them together. (Found ' . $countInternalScriptTags . ($countInternalScriptTags === 1 ? ' time' : ' times') . ')', self::LOW_IMPORTANCE, 10);
        }

        if ($countInlineScriptTags > self::MAX_ALLOWED_INLINE_SCRIPT_TAGS) {
            $this->addProblem('script', /** @lang text */ 'You should not use too many inline <script> tags. It is recommended to have one JavaScript file with multiple purposes. (Found ' . $countInlineScriptTags . ' inline <script> ' . ($countInlineScriptTags === 1 ? 'tag' : 'tags') . ')', self::LOW_IMPORTANCE, 10);
        }
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
            $this->addProblem('h3', 'H3 tags is missing.', self::LOW_IMPORTANCE);
        }
    }

    /**
     * Check if canonical url is set. If `$isResult` is set to true then return found canonical url(if not set then `baseUrl`)
     *
     * @param bool $isResult
     * @return string|null
     */
    public function validateCanonicalUrl(bool $isResult = false): ?string
    {
        $links = $this->site->getCanonicalUrl();

        // The only one problem is when using multiple canonical URLs. (tags)
        if ($links->count() > 1) {
            $this->addProblem('link.canonical', 'Multiple canonical URLs found.', self::HIGH_IMPORTANCE, 1);
        }

        if ($isResult) {
            if ($links->length === 0) {
                return $this->site->baseUrl;
            }

            $href = $links->item(0)->nodeValue;

            return $href !== '' ? $href : $this->site->baseUrl;
        }

        return null;
    }

    /**
     * Validate <img> tags
     */
    public function validateImages(): void
    {
        $images = $this->site->getImages();

        $canonicalUrl = $this->validateCanonicalUrl(true);

        /** @var DOMNode|DOMElement $image */
        foreach ($images as $image) {
            // Check for `alt` attribute
            if ($image->getAttribute('alt') === '') {
                $this->addProblem('img', /** @lang text */ '<img> tag should always has `alt` attribute. (and not empty)', self::LOW_IMPORTANCE, 2);
            }

            $imageFile = $image->getAttribute('src');

            if ($this->isExternalUrl($imageFile, $this->site->baseUrl)) {
                $this->externalLinks->append($imageFile);
            } else {
                $this->internalLinks->append($this->joinUrlAndPath($canonicalUrl, $imageFile));
            }
        }
    }

    /**
     * Check links if they are valid and return any valid response
     *
     * @throws SiteException
     */
    public function checkLinks(): void
    {
        $aTags = $this->site->getATags();

        $canonicalUrl = $this->validateCanonicalUrl(true);

        /** @var DOMNode|DOMElement $aTag */
        foreach ($aTags as $aTag) {
            $href = $aTag->getAttribute('href');

            if ($href === '') {
                $this->addProblem('a', /** @lang text */ '<a> tag does not contain any link in `href` attribute.', self::MEDIUM_IMPORTANCE,1);
                continue;
            }

            if ($this->isExternalUrl($href, $this->site->baseUrl) && !in_array($href, (array)$this->externalLinks, true)) {
                $this->externalLinks->append($href);
                continue;
            }

            $wholeUrl = $this->joinUrlAndPath($canonicalUrl, $href);
            $wholeUrl = $this->handleAnchor($wholeUrl);

            // The URL is same
            if ($wholeUrl === null) {
                continue;
            }

            if (!in_array($wholeUrl, (array)$this->internalLinks, true)) {
                $this->internalLinks->append($wholeUrl);
            }
        }

        $this->addInformation('externalLinks', null, ['links' => $this->externalLinks->getArrayCopy()]);
        $this->addInformation('internalLinks', null, ['links' => $this->internalLinks->getArrayCopy()]);

//        foreach ($this->externalLinks as $externalLink) {
//            $statusCode = Site::getStatusCodeOfUrl($externalLink);
//
//            if (!in_array($statusCode, Site::ALLOWED_STATUS_CODES, true)) {
//                $this->addProblem('externalLink', /** @lang text */ 'External link <a href="' . $externalLink . '" target="_blank">' . (strlen($externalLink) > 25 ? substr($externalLink, 0, 25) . '...' : $externalLink) . '</a> has returned ' . $statusCode . ' status code.', self::MEDIUM_IMPORTANCE, null);
//            }
//        }
//
//        foreach ($this->internalLinks as $internalLink) {
//            $statusCode = Site::getStatusCodeOfUrl($internalLink);
//
//            if (!in_array($statusCode, Site::ALLOWED_STATUS_CODES, true)) {
//                $this->addProblem('internalLink', /** @lang text */ 'Internal link <a href="' . $internalLink . '" target="_blank">' . (strlen($internalLink) > 25 ? substr($internalLink, 0, 25) . '...' : $internalLink) . '</a> has returned ' . $statusCode . ' status code.', self::MEDIUM_IMPORTANCE, null);
//            }
//        }
    }

    /**
     * Check if URL (relative or absolute) is external (different then base URL)
     *
     * @param string $url
     * @param string $baseUrl
     * @return bool
     */
    protected function isExternalUrl(string $url, string $baseUrl): bool
    {
        $components = parse_url($url);

        // we will treat url like '/relative.php' as relative
        if (empty($components['host'])) {
            return false;
        }

        // url host looks exactly like the local host
        if (strcasecmp($components['host'], $baseUrl) === 0) {
            return false;
        }

        $baseUrl = str_replace('www.', '', $baseUrl);

        // check if the url host is a subdomain
        return strripos($components['host'], '.' . $baseUrl) !== strlen($components['host']) - strlen('.' . $baseUrl);
    }

    /**
     * Join base URL and path
     *
     * @param string $base
     * @param string $path
     * @return string
     */
    protected function joinUrlAndPath(string $base, string $path): string
    {
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Handle fragments. Remove everything after `#`
     *
     * @param string $url
     * @return string|null
     */
    protected function handleAnchor(string $url): ?string
    {
        if (strpos($url, '#') === false) {
            return $url;
        }

        $url = substr($url, 0, strpos($url, '#'));

        if ($url === $this->site->url) {
            return null;
        }

        return $url;
    }

    /**
     * Add problem
     *
     * @param string $type
     * @param string|null $message
     * @param int $importance
     * @param int|null $maxSolvingTime
     * @param array $optionalData
     */
    protected function addProblem(string $type, ?string $message, int $importance, ?int $maxSolvingTime = 5, array $optionalData = []): void
    {
        $this->problems->append([
            'type' => $type,
            'message' => $message,
            'importance' => $importance,
            'maxSolvingTime' => $maxSolvingTime,
        ] + $optionalData);
    }

    /**
     * Add information
     *
     * @param string $type
     * @param string|null $message
     * @param array $optionalData
     */
    protected function addInformation(string $type, ?string $message, array $optionalData = []): void
    {
        $this->informations->append([
            'type' => $type,
            'message' => $message,
        ] + $optionalData);
    }
}