<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MapperException;
use App\Exceptions\SeoException;
use App\Models\Page;
use DOMDocument;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;


const DOMAINURL = 'https://www.example.com'; // domain URL: value must be absolute - every URL must include it at the beginning
const DEFAULTPRIORITY = '0.5'; // default priority for URLs not included in $fullUrlPriority and $partialUrlPriority
const DBHOST = "***********"; // database host
const DBUSER = "***********"; // database user (warning: user must have permissions to create / alter table)
const DBPASS = "***********"; // database password
const DBNAME = "***********"; // database name
const GETSITEMAPPATH = '/example/example/getSeoSitemap/'; // getSeoSitemap path into server
const SITEMAPPATH = '/example/example/'; // sitemap path inside server
const PRINTSKIPURLS = false; // set to true to print the list of URLs out of sitemap into log file
##### end of user constants

/**
 * Class SeoService
 * @package App\Services
 */
class SeoService
{

##### start of user parameters
// priority values must be 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0. other values are not accepted.
    private $fullUrlPriority = [ // set priority of particular URLs that are equal these values (values must be absolute)
        '1.0' => [
            'https://www.example.com'
        ],
        '0.9' => [
            'https://www.example.com/en/example.php',
            'https://www.example.com/it/example.php'
        ],
    ];
    private $partialUrlPriority = [ // set priority of particular URLs that start with these values (values must be absolute)
        '0.8' => [
            'https://www.example.com/example/in/',
            'https://www.example.com/example/out/',
        ],
        '0.7' => [
            'https://www.example.com/example/ext/',
            'https://www.example.com/example/ins/',
        ],
        '0.6' => [
            'https://www.example.com/example.php?p=',
        ],
    ];
    private $printChangefreqList = false; // set to true to print URLs list following changefreq
    private $printPriorityList = false; // set to true to print URLs list following priority
    private $printTypeList = false; // set to true to print URLs list following type
    private $extUrlsTest = true; // set to false to skip external URLs test (default value is true)
    private $printSitemapSizeList = false; // set to true to print a size list of all sitemaps
    private $printMalfUrls = true; // set to true to print a malformed URL list following a standard good practice
    private $checkH2 = true; // set to true to check if h2 is present in all pages
    private $checkH3 = true; // set to true to check if h3 is present in all pages
##### end of user parameters

#################################################
##### WARNING: DO NOT CHANGE ANYTHING BELOW #####
#################################################

    private $version = 'v3.9.0';
    // private $url = null; // an aboslute URL ( ex. https://www.example.com/test/test1.php )
    private $size = null; // size of file in Kb
    private $titleLength = [5, 101]; // min, max title length
    private $descriptionLength = [50, 160]; // min, max description length
    private $md5 = null; // md5 of string (hexadecimal)
    private $changefreq = null; // change frequency of file (values: daily, weekly, monthly, yearly)
    private $lastmod = null; // timestamp of last modified date of URL
    private $state = null; // state of URL (values: old = URL of previous scan, new = new URL to scan,
// scan = new URL already scanned, skip = new skipped URL, rSkip = new skipped URL because of robots.txt rules)
    private $insUrl = null;
    private $mysqli = null; // mysqli connection
    private $ch = null; // curl connection
    private $row = []; // array that includes row from query
    private $pageLinks = []; // it includes all links inside a page
    private $pageBody = null; // the page including header
    private $httpCode = null; // the http response code
    private $rowNum = null; // number of rows into dbase
    private $count = null; // count of rows (ex. 125)
    private $query = null; // query
    private $stmt = null; // statement for prepared query
    private $stmt2 = null; // statement 2 for prepared query
    private $stmt3 = null; // statement 3 for prepared query
    private $stmt4 = null; // statement 4 for prepared query
    private $stmt5 = null; // statement 5 for prepared query
    private $startTime = null; // start timestamp
    private $doNotFollowLinksIn = [ // do not follow links inside these file types
        'pdf',
    ];
    private $seoExclusion = [ // file type to exclude from seo functions
        'pdf',
    ];
    private $changefreqArr = ['daily', 'weekly', 'monthly', 'yearly']; // changefreq accepted values
    private $priorityArr = ['1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1']; // priority accepted values
    private $exec = 'n'; // execution value (could be y or n)
    private $errCounter = 0; // error counter
    private $maxErr = 20; // max number of errors to stop execution
    private $errMsg = [
        'C01' => 'cURL error for multiple choices server response'
    ];
    private $escapeCodeArr = [ // escape code conversions
        '&' => '&amp;',
        "'" => "&apos;",
        '"' => '&quot;',
        '>' => '&gt;',
        '<' => '&lt;',
    ];
    private $maxUrlsInSitemap = 50000; // max number of URLs into a single sitemap
    private $maxTotalUrls = 2500000000; // max total number of URLs
    private $totUrls = null; // total URLs at the end
    private $sitemapMaxSize = 52428800; // max sitemap size (bytes)
    private $sitemapNameArr = []; // includes names of all saved sitemaps at the end of the process
    private $txtToAddOnMysqliErr = ' - fix it remembering to set exec to n in getSeoSitemapExec table.'; // additional error text
    private $pageMaxSize = 135168; // page max file size in byte. this param is only for SEO
    private $maxUrlLength = 767; // max URL length
    private $malfChars = [' ']; // list of characters to detect malformed URLs following a standard good practice
    private $multipleSitemaps = null; // when multiple sitemaps are avaialble is true
    private $logPath = null; // log path

    private $dBaseVerNum = null; // version number of database
    private $countUrlWithoutDesc = 0; // counter of URLs without description
    private $countUrlWithMultiDesc = 0; // counter of URLs with multiple description
    private $countUrlWithoutTitle = 0; // counter of URLs without title
    private $countUrlWithMultiTitle = 0; // counter of URLs with multiple title
    private $countUrlWithoutH1 = 0; // counter of URLs without h1
    private $countUrlWithMultiH1 = 0; // counter of URLs with multiple h1
    private $countUrlWithoutH2 = 0; // counter of URLs without h2
    private $countUrlWithoutH3 = 0; // counter of URL without h3
    private $callerUrl = null; // caller URL of normal URL
    private $skipCallerUrl = null; // caller URL of skipped URL

    // CUSTOM

    /** @var string $url */
    private $url;

    /** @var string $baseUrl */
    private $baseUrl;

    /** @var int $startAt */
    private $startAt;

    /** @var string $userAgent */
    private $userAgent = 'SEOCrawler';

    /** @var Client $client */
    private $client;

    /** @var string $key */
    private $key;

    /** @var array $counters */
    private $counters = [
        'multiH1' => 0,
        'withoutH1' => 0,
        'withoutH2' => 0,
        'withoutH3' => 0,
        'withoutTitle' => 0,
        'multipleTitle' => 0,
    ];

    /** @var array $messages */
    private $messages = [];

    /** @var string|null $robotsPath */
    private $robotsPath;

    /** @var string|null $sitemapPath */
    private $sitemapPath;

    /** @var array $robotsLines */
    private $robotsLines = [];

    /** @var array $skipUrl */
    private $skipUrl = [];

    /** @var array $allowUrl */
    private $allowUrl = [];

    public const DEFAULT_OPTIONS = [
        'checkH2' => true,
        'checkH3' => true,
    ];

    /**
     * SeoService constructor.
     *
     * @param string $url
     * @param array $data
     * @throws SeoException
     */
    public function __construct(string $url, array $data = [])
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new SeoException('Url is wrong.');
        }

        $parts = parse_url($url);

        $this->url = $url;
        $this->baseUrl = $parts['scheme'] . '://' . $parts['host'];

        $this->robotsPath = $this->baseUrl . '/robots.txt';
        $this->sitemapPath = $this->baseUrl . '/sitemap.xml';
        $this->client = $data['client'];
        $this->isCrawl = $data['isCrawl'] ?? false;
        $this->startAt = time();

        // TODO: check `id` if exists in db (maybe generate some words not ids)
        $this->key = $this->generateRandomString();

        //create row in table
    }

    /**
     * Generate readable random string
     *
     * @param int $length
     * @return string
     * @throws Exception
     */
    public function generateRandomString(int $length = 6): string
    {
        $string = '';
        $vowels = ['a', 'e', 'i', 'o', 'u'];
        $consonants = ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z',];

        mt_srand((int)microtime() * 1000000 + random_int(0, 9999));

        $max = $length / 2;

        for ($i = 1; $i <= $max; $i++) {
            $string .= $consonants[random_int(0, 19)];
            $string .= $vowels[random_int(0, 4)];
        }

        return $string;
    }


    /**
     * Map the whole content of the page
     *
     * @param array $options
     * @throws SeoException
     */
    public function do(array $options = self::DEFAULT_OPTIONS): void
    {
        $parts = parse_url($this->url);

        $hasRobots = $this->handleRobotsData();
        $hasSitemap = $this->hasSitemap();

        $pageModel = new Page([
            'code' => $this->generateRandomString(),
            'baseUrl' => $parts['host'],
            'hasRobots' => (int)$hasRobots,
            'hasSitemap' => (int)$hasSitemap,
        ]);
        // $pageModel->save();

        $this->startScan();
    }

    /**
     * Start scanning
     *
     * @throws SeoException
     */
    private function startScan(): void
    {
        $page = $this->getPage($this->url);

        try {
            $site = new Mapper($page);
            $site->do();
        } catch (MapperException $e) {
            throw new SeoException($e->getMessage(), $e->getCode(), $e);
        }

        // HERE: Start mappping all tags
        $metaJson = [];

        $descriptionCount = 0;
        $keywordsCount = 0;

        foreach ($site->getMetaTags() as $val) {
            if (strtolower($val->getAttribute('name')) === 'description') {
                $descriptionCount++;
            }

            if (strtolower($val->getAttribute('name')) === 'keywords') {
                $keywordsCount++;
            }
        }





//        TODO:
//          1. Urobit funkciu, ktora by ukladala dane problemy na stranke ... addError(TYPE, message, EXTRA_PARAMS)
//          2. Pridat funkciu, ktora by najprv zhrnala vsetky odkazy na obrazky (mozno js, css) a nasledne po volani nejakej dalsej funkcie by spustilo kontrolu 404-iek










        if ($descriptionCount > 1) {
            $this->addMessage('There are ' . $descriptionCount . ' descriptions (description has not been registered into dBase because is not single) - URL ' . $url);
            // $this->countUrlWithMultiDesc++;
        } elseif ($descriptionCount === 0) {
            $this->addMessage('Description does not exist (SEO: description should be present) - URL ' . $url);
            // $this->countUrlWithoutDesc++;
        }

        if ($keywordsCount > 1) {
            $this->addMessage('There are ' . $keywordsCount . ' keyword tags (description has not been registered into dBase because is not single) - URL ' . $url);
            // $this->countUrlWithMultiDesc++;
        } elseif ($keywordsCount === 0) {
            $this->addMessage('Keywords do not exist (SEO: description should be present) - URL ' . $url);
            // $this->countUrlWithoutDesc++;
        }

    }

    /**
     * Check if sitemap exists
     *
     * @return bool
     */
    private function hasSitemap(): bool
    {
        // TODO: handle timed out -> use $this->client
        if (file($this->sitemapPath, FILE_IGNORE_NEW_LINES) === true) {
            return true;
        }

        return false;
    }
    // var_dump($mapper);
    // exit;

    // $h1Count = $mapper->getH1Tags()->length;
    //
    // if ($h1Count > 1) {
    //     $this->addMessage('There are ' . $h1Count . ' h1 (SEO: h1 should be single) - URL ' . $url);
    //     $this->counters['multiH1']++;
    //     // $this->countUrlWithMultiH1++;
    // } elseif ($h1Count === 0) {
    //     $this->addMessage('H1 does not exist (SEO: h1 should be present) - URL ' . $url);
    //     $this->counters['withoutH1']++;
    //     // $this->countUrlWithoutH1++;
    // }
    //
    // if ($options['checkH2'] && ($mapper->getH2Tags()->length === 0)) {
    //     $this->addMessage('H2 does not exist (SEO: h2 should be present) - URL ' . $url);
    //     $this->counters['withoutH2']++;
    //     // $this->countUrlWithoutH2++;
    // }
    //
    // if ($options['checkH3'] && ($mapper->getH3Tags()->length === 0)) {
    //     $this->addMessage('H3 does not exist (SEO: h3 should be present) - URL ' . $url);
    //     $this->counters['withoutH3']++;
    //     // $this->countUrlWithoutH3++;
    // }
    //
    // // `title` tag
    // $titleCount = $mapper->getTitle()->length;
    //
    // if ($titleCount === 1) {
    //     $title = $mapper->getTitle()->item(0)->textContent;
    //     $titleLength = strlen($title);
    //
    //     if ($titleLength > 300) {
    //         $this->addMessage('Title length: ' . $titleLength . ' characters (title has not been registered into dBase because of its length is more than 300 characters) - URL ' . $url, false);
    //         $title = null;
    //     }
    // } elseif ($titleCount > 1) {
    //     $this->addMessage('There are ' . $titleCount . ' titles (title has not been registered into dBase because is not single) - URL ' . $url, false);
    //     $title = null;
    //     $this->counters['multipleTitle']++;
    //     // $this->countUrlWithMultiTitle++;
    // } elseif ($titleCount === 0) {
    //     $this->addMessage('Title does not exist (SEO: title should be present) - URL ' . $url);
    //     $title = null;
    //     $this->counters['withoutTitle']++;
    //     // $this->countUrlWithoutTitle++;
    // }
    //
    //
    //
    //
    // // TODO: insSkipUrl() -> function to be rewritten because it has very important functionality
    //
    //
    // // TODO: Save to DB!
    // // if ($this->stmt5->bind_param('sss', $title, $description, $url) !== true) {
    // //     $this->writeLog('Execution has been stopped because of MySQL error binding parameters: ' . lcfirst($this->stmt5->error));
    // //
    // //     $this->stopExec();
    // // }
    // //
    // // if ($this->stmt5->execute() !== true) {
    // //     $this->writeLog('Execution has been stopped because of MySQL execute error: ' . lcfirst($this->stmt5->error));
    // //
    // //     $this->stopExec();
    // // }
    //
    // // set skipCallerUrl to prepare pageTest in case of calling insSkipUrl from pageTest
    // $this->skipCallerUrl = $url;
    //
    // // TODO: this functionality would be after `isCrawl` would be enabled
    //
    //
    // $this->handleRobots();
    //
    // // Parse `a` tags
    // foreach ($mapper->getATags() as $a) {
    //     $aHrefs = $this->getAbsoluteUrl($a->getAttribute('href'), $url);
    //
    //     // Add only links to include
    //     if ($this->isPageUrl($aHrefs)) {
    //         $this->pageLinks[] = $aHrefs;
    //     }
    // }
    //
    // // Parse `img` tags
    // foreach ($mapper->getImgTags() as $img) {
    //     $absImg = $this->getAbsoluteUrl($img->getAttribute('src'), $url);
    //
    //     if ($img->getAttribute('title') === '') {
    //         $this->addMessage('Image without title: ' . $absImg . ' - URL: ' . $url);
    //     }
    //
    //     if ($img->getAttribute('alt') === '') {
    //         $this->addMessage('Image without alt: ' . $absImg . ' - URL: ' . $url);
    //     }
    //
    //     // TODO: insert img URL as skipped...in that way the class will check http response code
    //     $this->insSkipUrl($absImg);
    // }
    //
    // // Parse `script` tags
    // foreach ($mapper->getScriptTags() as $script) {
    //     $scriptSrc = $script->getAttribute('src');
    //
    //     if ($scriptSrc !== '') {
    //         // TODO: insert acript URL as skipped...in that way the class will check http response code
    //         $this->insSkipUrl($this->getAbsoluteUrl($scriptSrc, $url));
    //     }
    // }
    //
    // // Parse `link` tags
    // foreach ($mapper->getLinkTags() as $link) {
    //     // TODO: insSkipUrl
    //     $this->insSkipUrl($this->getAbsoluteUrl($link->getAttribute('href'), $url));
    // }
    //
    // // Parse `iframe` tags
    // foreach ($mapper->getIframeTags() as $iframe) {
    //     // TODO
    //     $this->insSkipUrl($this->getAbsoluteUrl($iframe->getAttribute('src'), $url));
    // }
    //
    // // Parse `video` tags
    // foreach ($mapper->getVideoTags() as $video) {
    //     // TODO
    //     $this->insSkipUrl($this->getAbsoluteUrl($video->getAttribute('src'), $url));
    // }
    //
    // // Parse `audio` tags
    // foreach ($mapper->getAudioTags() as $audio) {
    //     // TODO
    //     $this->insSkipUrl($this->getAbsoluteUrl($audio->getAttribute('src'), $url));
    // }
    //
    // // Parse `form` tags
    // foreach ($mapper->getForms() as $form) {
    //     // Check and scan form with get method only
    //     if ($form->getAttribute('method') === 'get') {
    //         $absForm = $this->getAbsoluteUrl($form->getAttribute('action'), $url);
    //
    //         if ($this->isPageUrl($absForm)) {
    //             $this->addUrl($absForm);
    //         }
    //     }
    // }

    /**
     * Add url to the stack of all urls
     *
     * @param string $url
     */
    private function addUrl(string $url): void
    {
        $this->pageLinks[] = $url;
    }

    /**
     * Add report
     *
     * @param string $message
     * @param string $type
     * @param string $url
     * @param array|null $data
     */
    private function addReport(string $message, string $type, string $url, ?array $data = []): void
    {

    }

    /**
     * Add message
     *
     * @param string $message
     * @param bool $isSeo
     * @param array|null $data
     */
    private function addMessage(string $message, bool $isSeo = true, ?array $data = []): void
    {
        $this->messages[$isSeo ? 'seo' : 'general'][] = $message;
    }

    /**
     * Return all messages
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get page via request
     *
     * @param string $url
     * @return string
     * @throws SeoException
     */
    private function getPage(string $url): string
    {
        // TODO: remove
        return '<!doctype html><html itemscope="" itemtype="http://schema.org/WebPage" lang="sk"><head><meta content="text/html; charset=UTF-8" http-equiv="Content-Type"><meta content="/images/branding/googleg/1x/googleg_standard_color_128dp.png" itemprop="image"><title>Google</title><script nonce="E/jaNmgKSrGW70jZ+bkzog==">(function(){window.google={kEI:\'1CDxXMakIcj76QTpiZyABw\',kEXPI:\'0,1353747,57,1958,2422,698,527,731,223,528,1047,1257,1894,58,320,207,1017,176,1023,149,75,2332170,329531,1294,12383,4855,32691,15248,867,7505,4658,16521,363,3320,1262,4243,2436,5373,575,835,284,2,579,727,2432,1361,4325,4965,774,2251,4743,6,1145,2,1745,210,2605,3601,669,1050,1808,1397,81,7,491,620,29,10304,1288,2,4007,796,1221,37,920,753,120,1217,1364,1611,2736,49,2606,315,91,2,631,2403,159,2,4,2,670,44,370,4413,1161,1446,632,2228,656,338,1592,389,228,2,1159,777,1,3,365,1017,300,705,756,98,36,2,354,30,399,578,414,509,598,10,168,8,109,175,843,139,96,810,450,174,887,80,48,553,11,14,10,574,83,1841,76,526,381,25,177,167,158,3,1253,156,141,542,324,164,29,531,371,274,24,227,63,16,16,141,17,1670,488,37,128,237,1,100,431,190,818,260,1168,21,2,7,7,1154,341,554,606,453,185,140,11,6,169,4,542,1,243,76,9,184,595,510,1192,21,92,243,4,158,147,86,307,18,232,36,197,275,149,229,306,194,29,125,79,12,71,341,764,75,4,75,328,1284,56,177,924,3,1117,617,49,45,5928726,2960,2,8797382,189,60,1323,549,333,444,1,2,80,1,900,583,7,306,1,8,1,2,2132,1,1,1,1,1,414,1,748,141,59,726,3,7,563,1,2283,32,5,4,8,1,11,11,3,8,45\',authuser:0,kscs:\'c9c918f0_1CDxXMakIcj76QTpiZyABw\',kGL:\'SK\'};google.sn=\'webhp\';google.kHL=\'sk\';})();(function(){google.lc=[];google.li=0;google.getEI=function(a){for(var b;a&&(!a.getAttribute||!(b=a.getAttribute("eid")));)a=a.parentNode;return b||google.kEI};google.getLEI=function(a){for(var b=null;a&&(!a.getAttribute||!(b=a.getAttribute("leid")));)a=a.parentNode;return b};google.https=function(){return"https:"==window.location.protocol};google.ml=function(){return null};google.time=function(){return(new Date).getTime()};google.log=function(a,b,e,c,g){if(a=google.logUrl(a,b,e,c,g)){b=new Image;var d=google.lc,f=google.li;d[f]=b;b.onerror=b.onload=b.onabort=function(){delete d[f]};google.vel&&google.vel.lu&&google.vel.lu(a);b.src=a;google.li=f+1}};google.logUrl=function(a,b,e,c,g){var d="",f=google.ls||"";e||-1!=b.search("&ei=")||(d="&ei="+google.getEI(c),-1==b.search("&lei=")&&(c=google.getLEI(c))&&(d+="&lei="+c));c="";!e&&google.cshid&&-1==b.search("&cshid=")&&"slh"!=a&&(c="&cshid="+google.cshid);a=e||"/"+(g||"gen_204")+"?atyp=i&ct="+a+"&cad="+b+d+f+"&zx="+google.time()+c;/^http:/i.test(a)&&google.https()&&(google.ml(Error("a"),!1,{src:a,glmm:1}),a="");return a};}).call(this);(function(){google.y={};google.x=function(a,b){if(a)var c=a.id;else{do c=Math.random();while(google.y[c])}google.y[c]=[a,b];return!1};google.lm=[];google.plm=function(a){google.lm.push.apply(google.lm,a)};google.lq=[];google.load=function(a,b,c){google.lq.push([[a],b,c])};google.loadAll=function(a,b){google.lq.push([a,b])};}).call(this);google.f={};var a=window.location,b=a.href.indexOf("#");if(0<=b){var c=a.href.substring(b+1);/(^|&)q=/.test(c)&&-1==c.indexOf("#")&&a.replace("/search?"+c.replace(/(^|&)fp=[^&]*/g,"")+"&cad=h")};</script><style>#gbar,#guser{font-size:13px;padding-top:1px !important;}#gbar{height:22px}#guser{padding-bottom:7px !important;text-align:right}.gbh,.gbd{border-top:1px solid #c9d7f1;font-size:1px}.gbh{height:0;position:absolute;top:24px;width:100%}@media all{.gb1{height:22px;margin-right:.5em;vertical-align:top}#gbar{float:left}}a.gb1,a.gb4{text-decoration:underline !important}a.gb1,a.gb4{color:#00c !important}.gbi .gb4{color:#dd8e27 !important}.gbf .gb4{color:#900 !important}</style><style>body,td,a,p,.h{font-family:arial,sans-serif}body{margin:0;overflow-y:scroll}#gog{padding:3px 8px 0}td{line-height:.8em}.gac_m td{line-height:17px}form{margin-bottom:20px}.h{color:#36c}.q{color:#00c}.ts td{padding:0}.ts{border-collapse:collapse}em{font-weight:bold;font-style:normal}.lst{height:25px;width:496px}.gsfi,.lst{font:18px arial,sans-serif}.gsfs{font:17px arial,sans-serif}.ds{display:inline-box;display:inline-block;margin:3px 0 4px;margin-left:4px}input{font-family:inherit}a.gb1,a.gb2,a.gb3,a.gb4{color:#11c !important}body{background:#fff;color:black}a{color:#11c;text-decoration:none}a:hover,a:active{text-decoration:underline}.fl a{color:#36c}a:visited{color:#551a8b}a.gb1,a.gb4{text-decoration:underline}a.gb3:hover{text-decoration:none}#ghead a.gb2:hover{color:#fff !important}.sblc{padding-top:5px}.sblc a{display:block;margin:2px 0;margin-left:13px;font-size:11px}.lsbb{background:#eee;border:solid 1px;border-color:#ccc #999 #999 #ccc;height:30px}.lsbb{display:block}.ftl,#fll a{display:inline-block;margin:0 12px}.lsb{background:url(/images/nav_logo229.png) 0 -261px repeat-x;border:none;color:#000;cursor:pointer;height:30px;margin:0;outline:0;font:15px arial,sans-serif;vertical-align:top}.lsb:active{background:#ccc}.lst:focus{outline:none}.tiah{width:458px}</style><script nonce="E/jaNmgKSrGW70jZ+bkzog=="></script></head><body bgcolor="#fff"><script nonce="E/jaNmgKSrGW70jZ+bkzog==">(function(){var src=\'/images/nav_logo229.png\';var iesg=false;document.body.onload = function(){window.n && window.n();if (document.images){new Image().src=src;}if (!iesg){document.f&&document.f.q.focus();document.gbqf&&document.gbqf.q.focus();}}})();</script><div id="mngb"> <div id=gbar><nobr><b class=gb1>Vyhľad�vanie</b> <a class=gb1 href="https://www.google.sk/imghp?hl=sk&tab=wi">Obr�zky</a> <a class=gb1 href="https://maps.google.sk/maps?hl=sk&tab=wl">Mapy</a> <a class=gb1 href="https://www.youtube.com/?gl=SK&tab=w1">YouTube</a> <a class=gb1 href="https://news.google.sk/nwshp?hl=sk&tab=wn">Spr�vy</a> <a class=gb1 href="https://mail.google.com/mail/?tab=wm">Gmail</a> <a class=gb1 href="https://drive.google.com/?tab=wo">Disk</a> <h1>Google</h1> <a class=gb1 href="https://www.google.com/calendar?tab=wc">Kalend�r</a> <a class=gb1 style="text-decoration:none" href="https://www.google.sk/intl/sk/about/products?tab=wh"><u>Ďalšie</u> »</a></nobr></div><div id=guser width=100%><nobr><span id=gbn class=gbi></span><span id=gbf class=gbf></span><span id=gbe></span><a href="http://www.google.sk/history/optout?hl=sk" class=gb4>Hist�ria hľadania</a> | <a  href="/preferences?hl=sk" class=gb4>Nastavenia</a> | <a target=_top id=gb_70 href="https://accounts.google.com/ServiceLogin?hl=sk&passive=true&continue=https://www.google.com/" class=gb4>Prihl�siť sa</a></nobr></div><div class=gbh style=left:0></div><div class=gbh style=right:0></div> </div><center><br clear="all" id="lgpd"><div id="lga"><img alt="Google" height="92" src="/images/branding/googlelogo/1x/googlelogo_white_background_color_272x92dp.png" style="padding:28px 0 14px" width="272" id="hplogo" onload="window.lol&&lol()"><br><br></div><form action="/search" name="f"><table cellpadding="0" cellspacing="0"><tr valign="top"><td width="25%"> </td><td align="center" nowrap=""><input name="ie" value="ISO-8859-1" type="hidden"><input value="sk" name="hl" type="hidden"><input name="source" type="hidden" value="hp"><input name="biw" type="hidden"><input name="bih" type="hidden"><div class="ds" style="height:32px;margin:4px 0"><div style="position:relative;zoom:1"><input style="color:#000;margin:0;padding:5px 8px 0 6px;vertical-align:top;padding-right:38px" autocomplete="off" class="lst tiah" value="" title="Hľadať Googlom" maxlength="2048" name="q" size="57"><img src="/textinputassistant/tia.png" style="position:absolute;cursor:pointer;right:5px;top:4px;z-index:300" data-script-url="/textinputassistant/11/sk_tia.js" alt="" height="23" onclick="var s=document.createElement(\'script\');s.src=this.getAttribute(\'data-script-url\');(document.getElementById(\'xjsc\')||document.body).appendChild(s);" width="27"></div></div><br style="line-height:0"><span class="ds"><span class="lsbb"><input class="lsb" value="Hľadať Googlom" name="btnG" type="submit"></span></span><span class="ds"><span class="lsbb"><input class="lsb" value="Sk�sim šťastie" name="btnI" onclick="if(this.form.q.value)this.checked=1; else top.location=\'/doodles/\'" type="submit"></span></span></td><td class="fl sblc" align="left" nowrap="" width="25%"><a href="/advanced_search?hl=sk&authuser=0">Rozš�ren� vyhľad�vanie</a><a href="/language_tools?hl=sk&authuser=0">Jazykov� n�stroje</a></td></tr></table><input id="gbv" name="gbv" type="hidden" value="1"><script nonce="E/jaNmgKSrGW70jZ+bkzog==">(function(){var a,b="1";if(document&&document.getElementById)if("undefined"!=typeof XMLHttpRequest)b="2";else if("undefined"!=typeof ActiveXObject){var c,d,e=["MSXML2.XMLHTTP.6.0","MSXML2.XMLHTTP.3.0","MSXML2.XMLHTTP","Microsoft.XMLHTTP"];for(c=0;d=e[c++];)try{new ActiveXObject(d),b="2"}catch(h){}}a=b;if("2"==a&&-1==location.search.indexOf("&gbv=2")){var f=google.gbvu,g=document.getElementById("gbv");g&&(g.value=a);f&&window.setTimeout(function(){location.href=f},0)};}).call(this);</script></form><div id="gac_scont"></div><div style="font-size:83%;min-height:3.5em"><br></div><span id="footer"><div style="font-size:10pt"><div style="margin:19px auto;text-align:center" id="fll"><a href="/intl/sk/ads/">Inzerujte s Googlom</a><a href="http://www.google.sk/intl/sk/services/">Riešenia pre firmy</a><a href="/intl/sk/about.html">Všetko o Google</a><a href="https://www.google.com/setprefdomain?prefdom=SK&prev=https://www.google.sk/&sig=K_h1qpa9VUgaF3hxNyjmipdDojSB8%3D">Google.sk</a></div></div><p style="color:#767676;font-size:8pt">© 2019</p></span></center><script nonce="E/jaNmgKSrGW70jZ+bkzog==">(function(){window.google.cdo={height:0,width:0};(function(){var a=window.innerWidth,b=window.innerHeight;if(!a||!b){var c=window.document,d="CSS1Compat"==c.compatMode?c.documentElement:c.body;a=d.clientWidth;b=d.clientHeight}a&&b&&(a!=google.cdo.width||b!=google.cdo.height)&&google.log("","","/client_204?&atyp=i&biw="+a+"&bih="+b+"&ei="+google.kEI);}).call(this);})();(function(){var u=\'/xjs/_/js/k\x3dxjs.hp.en.ijMVHLlBivM.O/m\x3dsb_he,d/am\x3d4KAW/d\x3d1/rs\x3dACT90oFEIZJPU3kNfUFNzq4EObfJ2fzeAA\';setTimeout(function(){var a=document.createElement("script");a.src=u;google.timers&&google.timers.load&&google.tick&&google.tick("load","xjsls");document.body.appendChild(a)},0);})();(function(){window.google.xjsu=\'/xjs/_/js/k\x3dxjs.hp.en.ijMVHLlBivM.O/m\x3dsb_he,d/am\x3d4KAW/d\x3d1/rs\x3dACT90oFEIZJPU3kNfUFNzq4EObfJ2fzeAA\';})();function _DumpException(e){throw e;}function _F_installCss(c){}(function(){google.spjs=false;google.snet=true;google.em=[];google.emw=false;})();google.sm=1;(function(){var pmc=\'{\x22Qnk92g\x22:{},\x22RWGcrA\x22:{},\x22U5B21g\x22:{},\x22YFCs/g\x22:{},\x22ZI/YVQ\x22:{},\x22d\x22:{},\x22mVopag\x22:{},\x22sb_he\x22:{\x22agen\x22:true,\x22cgen\x22:true,\x22client\x22:\x22heirloom-hp\x22,\x22dh\x22:true,\x22dhqt\x22:true,\x22ds\x22:\x22\x22,\x22ffql\x22:\x22en\x22,\x22fl\x22:true,\x22host\x22:\x22google.com\x22,\x22isbh\x22:28,\x22jsonp\x22:true,\x22msgs\x22:{\x22cibl\x22:\x22Vymazať vyhľad�vanie\x22,\x22dym\x22:\x22Možno ste mysleli:\x22,\x22lcky\x22:\x22Sk�sim šťastie\x22,\x22lml\x22:\x22Ďalšie inform�cie\x22,\x22oskt\x22:\x22N�stroje na zad�vanie textu\x22,\x22psrc\x22:\x22Toto vyhľad�vanie bolo odstr�nen� z vašej \\u003Ca href\x3d\\\x22/history\\\x22\\u003Ewebovej hist�rie\\u003C/a\\u003E\x22,\x22psrl\x22:\x22Odstr�niť\x22,\x22sbit\x22:\x22Hľadať podľa obr�zka\x22,\x22srch\x22:\x22Hľadať Googlom\x22},\x22ovr\x22:{},\x22pq\x22:\x22\x22,\x22refpd\x22:true,\x22rfs\x22:[],\x22sbpl\x22:24,\x22sbpr\x22:24,\x22scd\x22:10,\x22sce\x22:5,\x22stok\x22:\x22MEMaurlGkXtKm5r_5QW38vrvuu0\x22,\x22uhde\x22:false}}\';google.pmc=JSON.parse(pmc);})();</script>        </body></html>';


        // TODO: check charset
        $request = new Request('GET', $url, [
            'timeout' => 6,
            'connection_timeout' => 6,
        ]);

        // var_dump(html_entity_decode($page, null, 'UTF-8'));

        try {
            $response = $this->client->send($request);
        } catch (ClientException $e) {
            $exception = json_decode((string)$e->getResponse()->getBody(), true);
            throw new SeoException($exception['message'], $exception['code'], $exception);
        } catch (ServerException $e) {
            $exception = json_decode((string)$e->getResponse()->getBody(), true);
            throw new SeoException($exception['message'], $exception['code'], $exception);
        }

        if ($response->getStatusCode() !== 200) {
            throw new SeoException('Url: ' . $url . ' has returned `' . $response->getStatusCode() . '` code.');
        }

        return $response->getBody()->getContents();
    }

    /**
     * Handle robots.txt lines
     *
     * @return bool
     */
    private function handleRobotsData(): bool
    {
        if (file_exists($this->robotsPath) === true) {
            $this->robotsLines = file($this->robotsPath, FILE_IGNORE_NEW_LINES);

            if ($this->robotsLines === false) {
                return false;
            }
        }

        $userAgentAll = false;

        foreach ($this->robotsLines as $value) {
            if ($value === 'User-agent: *') {
                $userAgentAll = true;
            } else if ($userAgentAll === true) {
                if (strpos($value, 'User-agent: ') === 0) {
                    break;
                }

                if (strpos($value, 'Disallow: ') === 0) {
                    $this->skipUrl[] = DOMAINURL . substr($value, 10);
                } else if (strpos($value, 'Allow: ') === 0) {
                    $this->allowUrl[] = DOMAINURL . substr($value, 7);
                }
            }
        }

        return true;
    }

    public function start()
    {

        $this->prep();
        $this->fullScan();
        $this->closeCurlConn();
        $this->writeLog('## Scan end' . PHP_EOL);
        $this->end();

    }
################################################################################
################################################################################

    private function prep()
    {

        $time = time();

// set robots.txt path
        $this->robotsPath = SITEMAPPATH . 'robots.txt';

// set start time
        $this->startTime = $time;

// set version in userAgent
        $this->userAgent = str_replace('ver.', $this->version, $this->userAgent);

        // Set `skip urls`
        $this->handleRobotsData();


// set execution of function to y
        $this->exec = 'y';
        $this->updateExec();

// check tables into dbase
        $this->checkTables();

// update all states to old to be ready for the new full scan
        $this->query = "UPDATE getSeoSitemap SET state = 'old'";
        $this->execQuery();

        $this->writeLog('## Scan start');

// prepare mysqli statements
        $this->prepMysqliStmt();

// insert or update DOMAINURL
        $this->insUpdNewUrlQuery(DOMAINURL);

        $this->openCurlConn();

    }
################################################################################
################################################################################
################################################################################
################################################################################
// open mysqli connection

    private function writeLog($logMsg)
    {

        $msgLine = date('Y-m-d H:i:s') . ' - ' . $logMsg . PHP_EOL;

        if (file_put_contents($this->logPath, $msgLine, FILE_APPEND | LOCK_EX) === false) {
            error_log('Execution has been stopped because of file_put_contents cannot write ' . $this->logPath, 0);

            $this->stopExec();
        }

    }
################################################################################
################################################################################

    private function stopExec()
    {

        $this->exec = 'n';
        $this->updateExec();

        exit();

    }
################################################################################
################################################################################

    private function updateExec()
    {

        $this->query = "UPDATE getSeoSitemapExec SET exec = '$this->exec' WHERE func = 'getSeoSitemap' LIMIT 1";
        $this->execQuery();

    }
################################################################################
################################################################################
// close mysqli statements

    private function execQuery()
    {

// reset row
        $this->row = [];

        if (($result = $this->mysqli->query($this->query)) === false) {
            $this->writeLog('Execution has been stopped because of MySQL error. Error (' . $this->mysqli->errno . '): '
                . $this->mysqli->error . ' - query: "' . $this->query . '"' . $this->txtToAddOnMysqliErr);
            exit();
        }

        if ($this->mysqli->warning_count > 0) {
            if ($warnRes = $this->mysqli->query("SHOW WARNINGS")) {
                $warnRow = $warnRes->fetch_row();

                $warnMsg = sprintf("%s (%d): %s", $warnRow[0], $warnRow[1], lcfirst($warnRow[2]));
                $this->writeLog($warnMsg . ' - query: "' . $this->query . '"');

                $warnRes->close();
            }
        }

// if query is select....
        if (strpos($this->query, 'SELECT') === 0) {

// if query is SELECT COUNT(*) AS count
            if (strpos($this->query, 'SELECT COUNT(*) AS count') === 0) {
                $row = $result->fetch_assoc();
                $this->count = $row['count'];
            } else {
// the while below is faster than the equivalent for
                $i = 0;
                while ($row = $result->fetch_assoc()) {
                    $this->row[$i] = $row;
                    $i++;
                }

                $this->rowNum = $result->num_rows;
            }

            $result->free_result();
        } // else if query is show....
        elseif (strpos($this->query, 'SHOW') === 0) {
            $this->rowNum = $result->num_rows;

            $result->free_result();
        }

    }

################################################################################
################################################################################

    private function openMysqliConn()
    {

        $this->mysqli = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
        if ($this->mysqli->connect_errno) {
            $this->writeLog('Execution has been stopped because of MySQL database connection error: '
                . $this->mysqli->connect_error . $this->txtToAddOnMysqliErr);
            exit();
        }

        if (!$this->mysqli->set_charset('utf8')) {
            $this->writeLog('Execution has been stopped because of MySQL error loading character set utf8: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

    }
################################################################################
################################################################################

    private function checkTables()
    {

        $this->query = "SHOW TABLES LIKE 'getSeoSitemapExec'";
        $this->execQuery();

        if ($this->rowNum === 0) {

            $this->query = "CREATE TABLE `getSeoSitemapExec` (
 `id` int(1) NOT NULL AUTO_INCREMENT,
 `func` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
 `version` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
 `mDate` int(10) DEFAULT NULL COMMENT 'timestamp of last mod',
 `exec` varchar(1) COLLATE utf8_unicode_ci DEFAULT NULL,
 `step` int(2) NOT NULL DEFAULT '0' COMMENT 'passed step',
 `newData` varchar(1) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'n' COMMENT 'set to y when new data are avaialble',
 UNIQUE KEY `id` (`id`),
 UNIQUE KEY `func` (`func`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='execution of getSeoSitemap functions'";
            $this->execQuery();

            $this->query = "INSERT INTO getSeoSitemapExec (func, mDate, exec, newData) 
SELECT 'getSeoSitemap', 0, 'n', 'n' FROM DUAL WHERE NOT EXISTS 
(SELECT func FROM getSeoSitemapExec WHERE func='getSeoSitemap')";
            $this->execQuery();
        } elseif ($this->rowNum === 1) {
            $this->getDbaseVerNum();

            if ($this->dBaseVerNum < 310) {
                $this->query = "SHOW COLUMNS FROM getSeoSitemapExec WHERE FIELD = 'step'";
                $this->execQuery();

                if ($this->rowNum === 0) {
                    $this->query = "ALTER TABLE getSeoSitemapExec ADD COLUMN step int(2) NOT NULL DEFAULT '0' COMMENT 'passed step' AFTER exec";
                    $this->execQuery();
                }
            }
        }

        $this->query = "SHOW TABLES LIKE 'getSeoSitemap'";
        $this->execQuery();

        if ($this->rowNum === 0) {
            $this->query = "CREATE TABLE `getSeoSitemap` (
 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 `url` varbinary(767) NOT NULL,
 `callerUrl` varbinary(767),
 `size` int(10) unsigned NOT NULL COMMENT 'byte',
 `title` text COLLATE utf8_unicode_ci,
 `description` text COLLATE utf8_unicode_ci,
 `md5` char(32) COLLATE utf8_unicode_ci NOT NULL,
 `lastmod` int(10) unsigned NOT NULL,
 `changefreq` enum('daily','weekly','monthly','yearly') COLLATE utf8_unicode_ci NOT NULL,
 `priority` enum('0.1','0.2','0.3','0.4','0.5','0.6','0.7','0.8','0.9','1.0') COLLATE utf8_unicode_ci DEFAULT NULL,
 `state` enum('new','scan','skip','rSkip','old') COLLATE utf8_unicode_ci NOT NULL,
 `httpCode` char(3) COLLATE utf8_unicode_ci NOT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `url` (`url`),
 KEY `state` (`state`),
 KEY `httpCode` (`httpCode`),
 KEY `size` (`size`),
 KEY `changefreq` (`changefreq`),
 KEY `priority` (`priority`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
            $this->execQuery();
        } elseif ($this->rowNum === 1) {
            $this->getDbaseVerNum();

            if ($this->dBaseVerNum < 330) {
                $this->query = "SHOW COLUMNS FROM getSeoSitemap WHERE FIELD = 'callerUrl'";
                $this->execQuery();

                if ($this->rowNum === 0) {
                    $this->query = "ALTER TABLE getSeoSitemap ADD COLUMN callerUrl varbinary(767) AFTER url";
                    $this->execQuery();
                }
            }

            if ($this->dBaseVerNum < 380) {
                $this->query = "ALTER TABLE getSeoSitemap CHANGE state state enum('new','scan','skip','rSkip','old') COLLATE utf8_unicode_ci NOT NULL";
                $this->execQuery();
            }
        }

    }
################################################################################
################################################################################

    private function getDbaseVerNum()
    {

        $this->query = "SELECT version FROM getSeoSitemapExec WHERE func = 'getSeoSitemap' LIMIT 1";
        $this->execQuery();

        $this->dBaseVerNum = $this->getVerNum($this->row[0]['version']);

    }
################################################################################
################################################################################

    private function getVerNum($ver)
    {

// return digits only
        $verNum = filter_var($ver, FILTER_SANITIZE_NUMBER_INT);

        if ($verNum === false) {
            $this->writeLog("Execution has been stopped because of filter_var cannot filter value '" . $ver . "'");

            $this->stopExec();
        }

        $mainNo = substr($ver, 1, 2);

        if (ctype_digit($mainNo) === true) {
            $digits = 4;
        } else {
            $digits = 3;
        }

        $verNum = str_pad($verNum, $digits, '0');

        return $verNum;

    }
################################################################################
################################################################################

    private function prepMysqliStmt()
    {

        $this->stmt = $this->mysqli->prepare("UPDATE getSeoSitemap SET state = 'scan' WHERE url = ? LIMIT 1");
        if ($this->stmt === false) {
            $this->writeLog('Execution has been stopped because of MySQL prepare error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

        $this->stmt2 = $this->mysqli->prepare("INSERT INTO getSeoSitemap (url, callerUrl, state) VALUES (?, ?, 'new') "
            . "ON DUPLICATE KEY UPDATE state = IF(state = 'old', 'new', state), callerUrl = ?");

        if ($this->stmt2 === false) {
            $this->writeLog('Execution has been stopped because of MySQL prepare error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

        $this->stmt3 = $this->mysqli->prepare("UPDATE getSeoSitemap SET "
            . "size = ?, "
            . "md5 = ?, "
            . "lastmod = ?, "
            . "changefreq = ?, "
            . "httpCode = ? "
            . "WHERE url = ? LIMIT 1");
        if ($this->stmt3 === false) {
            $this->writeLog('Execution has been stopped because of MySQL prepare error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

        $this->stmt4 = $this->mysqli->prepare("INSERT INTO getSeoSitemap ("
            . "url, "
            . "callerUrl, "
            . "size, "
            . "md5, "
            . "lastmod, "
            . "changefreq, "
            . "priority, "
            . "state, "
            . "httpCode) "
            . "VALUES ("
            . "?, "
            . "?, "
            . "?, "
            . "'', "
            . "'', "
            . "'', "
            . "NULL, "
            . "'skip', "
            . "?) "
            . "ON DUPLICATE KEY UPDATE "
            . "callerUrl = ?, "
            . "size = ?, "
            . "md5 = '', "
            . "lastmod = 0, "
            . "changefreq = '', "
            . "priority = NULL, "
            . "state = 'skip', "
            . "httpCode = ?");

        if ($this->stmt4 === false) {
            $this->writeLog('Execution has been stopped because of MySQL prepare error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

        $this->stmt5 = $this->mysqli->prepare("UPDATE getSeoSitemap SET "
            . "title = ?, "
            . "description = ? "
            . "WHERE url = ? LIMIT 1");
        if ($this->stmt5 === false) {
            $this->writeLog('Execution has been stopped because of MySQL prepare error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

    }
################################################################################
################################################################################

    private function insUpdNewUrlQuery($url)
    {

        $this->checkUrlLength($url);

        if ($this->stmt2->bind_param('sss', $url, $this->callerUrl, $this->callerUrl) !== true) {
            $this->writeLog('Execution has been stopped because of MySQL error binding parameters: ' . $this->stmt2->error);

            $this->stopExec();
        }

        if ($this->stmt2->execute() !== true) {
            $this->writeLog('Execution has been stopped because of MySQL execute error: ' . $this->stmt2->error);

            $this->stopExec();
        }

    }
################################################################################
################################################################################

    private function checkUrlLength($url)
    {

        $urlLength = strlen($url);
        if ($urlLength > $this->maxUrlLength) {
            $this->writeLog('Execution has been stopped because of length is > ' . $this->maxUrlLength . ' characters for URL: ' . $url);

            $this->stopExec();
        }

    }
################################################################################
################################################################################

    private function openCurlConn()
    {

        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);

    }

    /**
     * Full scan
     */
    private function fullScan()
    {

        do {
            $this->query = "SELECT url, size, md5, lastmod FROM getSeoSitemap WHERE state = 'new' LIMIT 1";
            $this->execQuery();
            $rowNum = $this->rowNum;

// if there is almost 1 record into getSeoSitemap table with state new....
            if ($rowNum === 1) {
                $this->url = $this->row[0]['url'];
                $url = $this->url;

                $this->scan($url);
                $this->getHref($url);

                $this->callerUrl = $url;

                $this->linksScan();

                if ($this->stmt->bind_param('s', $url) !== true) {
                    $this->writeLog('Execution has been stopped because of MySQL error binding parameters: ' . $this->stmt->error);

                    $this->stopExec();
                }

                if ($this->stmt->execute() !== true) {
                    $this->writeLog('Execution has been stopped because of MySQL execute error: ' . $this->stmt->error);

                    $this->stopExec();
                }
            }

        } while ($rowNum === 1);

    }
################################################################################
################################################################################

    private function scan($url)
    {

        $this->resetVars2();
        $this->getPage($url);

// set skipCallerUrl to prepare pageTest in case of calling insSkipUrl from pageTest
        $this->skipCallerUrl = $this->callerUrl;

        if ($this->isPageUrl($url)) {
            $this->changefreq = 'daily';

            $this->update();

            if (
                $this->stmt3->bind_param('ssssss', $this->size, $this->md5, $this->lastmod, $this->changefreq, $this->httpCode, $url) !== true) {
                $this->writeLog('Execution has been stopped because of MySQL error binding parameters: ' . lcfirst($this->stmt3->error));

                $this->stopExec();
            }

            if ($this->stmt3->execute() !== true) {
                $this->writeLog('Execution has been stopped because of MySQL execute error: ' . lcfirst($this->stmt3->error));

                $this->stopExec();
            }
        }

    }
################################################################################
################################################################################

    private function resetVars2()
    {

        $this->size = null;
        $this->md5 = null;
        $this->lastmod = null;
        $this->changefreq = null;
        $this->state = null;
        $this->httpCode = null;
        $this->insUrl = null;
        $this->pageBody = null;

    }
################################################################################
################################################################################

    // private function getPage($url)
    // {
    //
    //     curl_setopt($this->ch, CURLOPT_URL, $url);
    //
    //     $this->pageBody = curl_exec($this->ch);
    //
    //     if ($this->pageBody === false) {
    //         $this->writeLog('curl_exec failed (cURL error: ' . curl_error($this->ch) . ') calling URL ' . $url);
    //
    //         $this->getErrCounter();
    //
    //         $this->pageBody = '';
    //         $this->httpCode = 'C01';
    //         $this->size = 0;
    //         $this->md5 = md5($this->pageBody);
    //         $this->lastmod = time();
    //
    //         return;
    //     }
    //
    //     $this->httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
    //     if ($this->httpCode === false) {
    //         $this->writeLog('Execution has been stopped because of curl_getinfo failed calling URL ' . $url);
    //
    //         $this->stopExec();
    //     }
    //
    //     $this->size = mb_strlen($this->pageBody, '8bit');
    //
    //     if ($this->size === false) {
    //         $this->writeLog('Execution has been stopped because of mb_strlen failed calling URL ' . $url);
    //
    //         $this->stopExec();
    //     }
    //
    //     $this->md5 = md5($this->pageBody);
    //     $this->lastmod = time();
    //
    // }
################################################################################
################################################################################

    private function getErrCounter()
    {

        $this->errCounter++;

        if ($this->errCounter >= $this->maxErr) {
            $this->writeLog('Execution has been stopped because of errors are more than ' . $this->maxErr);

            $this->stopExec();
        }

    }


    /**
     * Check page url
     *
     * @param string $url
     * @return bool
     */
    private function isPageUrl(string $url): bool
    {

        // if url is not into domain
        if (strpos($url, $this->url) !== 0) {
            $this->insSkipUrl($url);
            return false;
        }

        // if url is mailto
        if (strpos($url, 'mailto') === 0) {
            $this->insSkipUrl($url);
            return false;
        }

        return true;
    }

    private function insSkipUrl(string $url): void
    {

        // $this->checkUrlLength($url);
        //
        // if ($this->stmt4->bind_param('sssssss', $url, $this->skipCallerUrl, $this->size, $this->httpCode, $this->skipCallerUrl, $this->size, $this->httpCode) !== true) {
        //
        //     $this->writeLog('Execution has been stopped because of MySQL error binding parameters: ' . lcfirst($this->stmt4->error));
        //
        //     $this->stopExec();
        // }
        //
        // if ($this->stmt4->execute() !== true) {
        //     $this->writeLog('Execution has been stopped because of MySQL execute error: ' . lcfirst($this->stmt4->error));
        //
        //     $this->stopExec();
        // }

    }
################################################################################
################################################################################

    private function update()
    {

// to prevent error on empty page
        if ($this->row[0]['size'] > 0) {
            $sizeDiff = abs($this->size - $this->row[0]['size']);

            if ($this->row[0]['md5'] !== $this->md5) {
                $newLastmod = $this->lastmod;
            } else {
                $newLastmod = $this->row[0]['lastmod'];
            }

            $lastmodDiff = $this->lastmod - $this->row[0]['lastmod'];

// set changefreq weekly if lastmod date difference is more than 1 week
            if ($lastmodDiff > 604799 && $lastmodDiff < 2678400) {
                $this->changefreq = 'weekly';
            } // set changefreq monthly if lastmod date difference is more than 31 days
            elseif ($lastmodDiff > 2678399 && $lastmodDiff < 31536000) {
                $this->changefreq = 'monthly';
            } // set changefreq yearly if lastmod date difference is more than 365 days
            elseif ($lastmodDiff > 31535999) {
                $this->changefreq = 'yearly';
            }

            $this->lastmod = $newLastmod;
        }

    }
################################################################################
################################################################################

    private function getHref(string $url)
    {

        $html = $this->pageBody;

// reset pageLinks
        $this->pageLinks = [];

// return if httpCode is not 200 to prevent checking of failed pages
        if ($this->httpCode !== 200) {
            return;
        }

// return if $html is empty to prevent error on $dom->loadHTML($html)
        if (empty($html) === true) {
            return;
        }

// do not search links inside $doNotFollowLinksIn
        foreach ($this->doNotFollowLinksIn as $value) {
            if ($value === $this->getUrlExt($url)) {
                return;
            }
        }

        $dom = new DOMDocument();

        if (@$dom->loadHTML($html) === false) {
            $this->writeLog('DOMDocument parse error on URL ' . $url);
        }

// get all as
        $as = $dom->getElementsByTagName('a');

// get all imgs
        $imgs = $dom->getElementsByTagName('img');

        // get all scripts
        $scripts = $dom->getElementsByTagName('script');

// get all links
        $links = $dom->getElementsByTagName('link');

// get all iframes
        $iframes = $dom->getElementsByTagName('iframe');

// get all videos
        $videos = $dom->getElementsByTagName('video');

        // get all audios
        $audios = $dom->getElementsByTagName('audio');

        // get all h1s
        $h1Arr = $dom->getElementsByTagName('h1');
        $h1Count = $h1Arr->length;

        // get all forms
        $forms = $dom->getElementsByTagName('form');

        if ($h1Count > 1) {
            $this->writeLog('There are ' . $h1Count . ' h1 (SEO: h1 should be single) - URL ' . $url);
            $this->countUrlWithMultiH1++;
        } elseif ($h1Count === 0) {
            $this->writeLog('H1 does not exist (SEO: h1 should be present) - URL ' . $url);
            $this->countUrlWithoutH1++;
        }

        if ($this->checkH2 === true) {
            // get all h2s
            $h2Arr = $dom->getElementsByTagName('h2');
            $h2Count = $h2Arr->length;

            if ($h2Count === 0) {
                $this->writeLog('H2 does not exist (SEO: h2 should be present) - URL ' . $url);
                $this->countUrlWithoutH2++;
            }
        }

        if ($this->checkH3 === true) {
            // get all h3s
            $h3Arr = $dom->getElementsByTagName('h3');
            $h3Count = $h3Arr->length;

            if ($h3Count === 0) {
                $this->writeLog('H3 does not exist (SEO: h3 should be present) - URL ' . $url);
                $this->countUrlWithoutH3++;
            }
        }

        $titleArr = $dom->getElementsByTagName('title');
        $titleCount = $titleArr->length;

        if ($titleCount === 1) {
            $title = $titleArr->item(0)->textContent;
            $titleLength = strlen($title);

            if ($titleLength > 300) {
                $this->writeLog('Title length: ' . $titleLength
                    . ' characters (title has not been registered into dBase because of its length is more than 300 characters) - URL ' . $url);
                $title = null;
            }
        } elseif ($titleCount > 1) {
            $this->writeLog('There are ' . $titleCount . ' titles (title has not been registered into dBase because is not single) - URL ' . $url);
            $title = null;
            $this->countUrlWithMultiTitle++;
        } elseif ($titleCount === 0) {
            $this->writeLog('Title does not exist (SEO: title should be present) - URL ' . $url);
            $title = null;
            $this->countUrlWithoutTitle++;
        }

        $metaArr = $dom->getElementsByTagName('meta');

        $descriptionCount = 0;

        foreach ($metaArr as $val) {
            if (strtolower($val->getAttribute('name')) == 'description') {
                $description = $val->getAttribute('content');
                $descriptionCount++;
            }
        }

        if ($descriptionCount === 1) {
            $descriptionLength = strlen($description);

            if ($descriptionLength > 300) {
                $this->writeLog('Description length: ' . $descriptionLength
                    . ' characters (description has not been registered into dBase because of its length is more than 300 characters) - URL ' . $url);
                $description = null;
            }
        } elseif ($descriptionCount > 1) {
            $this->writeLog('There are ' . $descriptionCount . ' descriptions '
                . '(description has not been registered into dBase because is not single) - URL ' . $url);
            $description = null;
            $this->countUrlWithMultiDesc++;
        } elseif ($descriptionCount === 0) {
            $this->writeLog('Description does not exist (SEO: description should be present) - URL ' . $url);
            $description = null;
            $this->countUrlWithoutDesc++;
        }

        if ($this->stmt5->bind_param('sss', $title, $description, $url) !== true) {
            $this->writeLog('Execution has been stopped because of MySQL error binding parameters: ' . lcfirst($this->stmt5->error));

            $this->stopExec();
        }

        if ($this->stmt5->execute() !== true) {
            $this->writeLog('Execution has been stopped because of MySQL execute error: ' . lcfirst($this->stmt5->error));

            $this->stopExec();
        }

// set skipCallerUrl to prepare pageTest in case of calling insSkipUrl from pageTest
        $this->skipCallerUrl = $url;

// iterate over extracted links and display their URLs
        foreach ($as as $a) {

// get absolute URL of href
            $absHref = $this->getAbsoluteUrl($a->getAttribute('href'), $url);

// add only links to include
            if ($this->isPageUrl($absHref)) {
                $this->pageLinks[] = $absHref;
            }
        }

// iterate over extracted imgs and display their URLs
        foreach ($imgs as $img) {
// get absolute URL of image
            $absImg = $this->getAbsoluteUrl($img->getAttribute('src'), $url);

// check if img title and img alt are present and length >= 1
            if (strlen($img->getAttribute('title')) < 1) {
                $this->writeLog('Image without title: ' . $absImg . ' - URL: ' . $url);
            }

            if (strlen($img->getAttribute('alt')) < 1) {
                $this->writeLog('Image without alt: ' . $absImg . ' - URL: ' . $url);
            }

// insert img URL as skipped...in that way the class will check http response code
            $this->insSkipUrl($absImg);
        }

// iterate over extracted scripts and display their URLs
        foreach ($scripts as $script) {
            $scriptSrc = $script->getAttribute('src');

// get absolute URL script src if src exits only (this is to prevent error when script does not have src)
            if ($scriptSrc !== '') {

// insert acript URL as skipped...in that way the class will check http response code
                $this->insSkipUrl($this->getAbsoluteUrl($scriptSrc, $url));
            }
        }

// iterate over extracted links and display their URLs
        foreach ($links as $link) {

// insert link URL as skipped...in that way the class will check http response code
            $this->insSkipUrl($this->getAbsoluteUrl($link->getAttribute('href'), $url));
        }

// iterate over extracted iframes and display their URLs
        foreach ($iframes as $iframe) {

// insert iframe URL as skipped...in that way the class will check http response code
            $this->insSkipUrl($this->getAbsoluteUrl($iframe->getAttribute('src'), $url));
        }

// iterate over extracted video and display their URLs
        foreach ($videos as $video) {

// insert video URL as skipped...in that way the class will check http response code
            $this->insSkipUrl($this->getAbsoluteUrl($video->getAttribute('src'), $url));
        }

// iterate over extracted audios and display their URLs
        foreach ($audios as $audio) {

// insert audio URL as skipped...in that way the class will check http response code
            $this->insSkipUrl($this->getAbsoluteUrl($audio->getAttribute('src'), $url));
        }

// iterate over extracted forms and get their action URLs
        foreach ($forms as $form) {

// check and scan form with get method only
            if ($form->getAttribute('method') === 'get') {

// get absolute URL of form
                $absForm = $this->getAbsoluteUrl($form->getAttribute('action'), $url);

// add only URL to include
                if ($this->isPageUrl($absForm)) {
                    $this->pageLinks[] = $absForm;
                }
            }
        }

        $this->pageLinks = array_unique($this->pageLinks);

    }
################################################################################
################################################################################

    private function getUrlExt($url)
    {

        $fileExt = '';

        $parse = parse_url($url);

        if ($parse !== false) {
            if (isset($parse['path']) === true) {
                $path = $parse['path'];
                $fileExt = pathinfo($path, PATHINFO_EXTENSION);
            }

            return $fileExt;
        }

        return '';
    }


    /**
     * Get Absolute URL
     *
     * @param string $relativeUrl
     * @param string $baseUrl
     * @return string
     */
    private function getAbsoluteUrl(string $relativeUrl, string $baseUrl): string
    {
        $path = $scheme = $user = $pass = $host = $port = $query = null;

        if (parse_url($relativeUrl, PHP_URL_SCHEME) !== null) {
            return $relativeUrl;
        }

        // queries and anchors
        if (strpos($relativeUrl, '#') === 0 || strpos($relativeUrl, '?') === 0) {
            return $baseUrl . $relativeUrl;
        }

        extract(parse_url($baseUrl), EXTR_OVERWRITE);

        // if base URL contains a path remove non-directory elements from $path
        if (isset($path) === true) {
            $path = preg_replace('#/[^/]*$#', '', $path);
        } else {
            $path = '';
        }

        // if relative URL starts with //
        if (strpos($relativeUrl, '//') === 0) {
            return $scheme . ':' . $relativeUrl;
        }

        // if relative URL starts with /
        if (strpos($relativeUrl, '/') === 0) {
            $path = null;
        }

        $abs = null;

        // if relative URL contains a user
        if (isset($user) === true) {
            $abs .= $user;

            // if relative URL contains a password
            if (isset($pass) === true) {
                $abs .= ':' . $pass;
            }

            $abs .= '@';
        }

        $abs .= $host;

        // if relative URL contains a port
        if (isset($port) === true) {
            $abs .= ':' . $port;
        }

        $abs .= $path . '/' . $relativeUrl . (isset($query) === true ? '?' . $query : null);

        // replace // or /./ or /foo/../ with /
        $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];

        //TODO: Rewrite to better solution!
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {
        }

        return $scheme . '://' . $abs;
    }

################################################################################
################################################################################

    private function linksScan()
    {

        foreach ($this->pageLinks as $url) {
            $this->insNewUrl($url);
        }

    }
################################################################################
################################################################################
// get Kb from byte rounded 2 decimals and formatted 2 decimals

    private function insNewUrl($url)
    {

        $this->resetVars();

// set skipCallerUrl to prepare pageTest in case of calling insSkipUrl from pageTest
        $this->skipCallerUrl = $this->callerUrl;

        if ($this->isPageUrl($url)) {
            $this->insUpdNewUrlQuery($url);
        }

    }
################################################################################
################################################################################

    private function resetVars()
    {

        $this->resetVars2();

// reset row
        $this->row = [];

    }
################################################################################
################################################################################

    private function closeCurlConn()
    {

        curl_close($this->ch);

    }
################################################################################
################################################################################

    private function end()
    {

// delete old records of previous full scan
        $this->query = "DELETE FROM getSeoSitemap WHERE state = 'old'";
        $this->execQuery();

        $this->writeLog('Deleted old URLs');

        $this->query = "SELECT COUNT(*) AS count FROM getSeoSitemap";
        $this->execQuery();

        $this->writeLog($this->count . ' scanned URLs');

        $this->setUrlsToRobotsSkip();

        $this->writeLog($this->countUrlWithoutTitle . ' URLs without title into domain (SEO: title should be present)');
        $this->writeLog($this->countUrlWithMultiTitle . ' URLs with multiple title into domain (SEO: title should be single)');
        $this->writeLog($this->countUrlWithoutDesc . ' URLs without description into domain (SEO: description should be present)');
        $this->writeLog($this->countUrlWithMultiDesc . ' URLs with multiple description into domain (SEO: description should be single)');
        $this->writeLog($this->countUrlWithoutH1 . ' URLs without h1 into domain (SEO: h1 should be present)');
        $this->writeLog($this->countUrlWithMultiH1 . ' URLs with multiple h1 into domain (SEO: h1 should be single)');

        if ($this->checkH2 === true) {
            $this->writeLog($this->countUrlWithoutH2 . ' URLs without h2 into domain (SEO: h2 should be present)');
        }

        if ($this->checkH3 === true) {
            $this->writeLog($this->countUrlWithoutH3 . ' URLs without h3 into domain (SEO: h3 should be present)');
        }

        if ($this->extUrlsTest === true) {
            $this->openCurlConn();
            $this->checkSkipUrls();
            $this->closeCurlConn();
        }

// close msqli statements
        $this->closeMysqliStmt();

        $this->query = "SELECT * FROM getSeoSitemap WHERE httpCode != '200' OR size = 0 ORDER BY url";
        $this->execQuery();

        if ($this->rowNum > 0) {
            $this->writeLog('##### Failed URLs out of sitemap');

            foreach ($this->row as $value) {
                if ($value['httpCode'] !== '200') {

                    if (array_key_exists($value['httpCode'], $this->errMsg) === true) {
                        $logMsg = $this->errMsg[$value['httpCode']] . ' ' . $value['httpCode'] . ' - URL: ' . $value['url'] . ' - caller URL: ' . $value['callerUrl'];
                    } else {
                        $logMsg = 'Http code ' . $value['httpCode'] . ' - URL: ' . $value['url'] . ' - caller URL: ' . $value['callerUrl'];
                    }

                } else {
                    $logMsg = 'Empty file: ' . $value['url'];
                }
                $this->writeLog($logMsg);
            }

            $this->writeLog('##########');
        }

        $this->writeLog($this->rowNum . ' failed URLs out of sitemap' . PHP_EOL);

        $this->writeLog('##### SEO');
        $this->getSizeList();
        $this->getMinTitleLengthList();
        $this->getMaxTitleLengthList();
        $this->getDuplicateTitle();
        $this->getMinDescriptionLengthList();
        $this->getMaxDescriptionLengthList();
        $this->getDuplicateDescription();
        $this->getIntUrls();
        $this->setPriority();

// write changefreq into log
        foreach ($this->changefreqArr as $value) {
            $this->query = "SELECT COUNT(*) AS count FROM getSeoSitemap "
                . "WHERE changefreq = '$value' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND size != 0";
            $this->execQuery();

            $this->writeLog('Setted ' . $value . ' change frequency to ' . $this->count . ' URLs into sitemap');
        }

// write lastmod min and max values into log
        $this->query = "SELECT MIN(lastmod) AS minLastmod, MAX(lastmod) AS maxLastmod FROM getSeoSitemap "
            . "WHERE state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND size != 0";
        $this->execQuery();

        $minLastmodDate = date('Y.m.d H:i:s', $this->row[0]['minLastmod']);
        $maxLastmodDate = date('Y.m.d H:i:s', $this->row[0]['maxLastmod']);
        $this->writeLog('Min last modified time into sitemap is ' . $minLastmodDate);
        $this->writeLog('Max last modified time into sitemap is ' . $maxLastmodDate . PHP_EOL);

// save all sitemaps
        if ($this->save() !== true) {
            $this->writeLog('Execution has been stopped because of save error');

            $this->stopExec();
        }

// gzip all sitemaps
        foreach ($this->sitemapNameArr as $key => $value) {
            $this->gzip($value);

            $newValue = $value . '.gz';
            $fileName = $this->getFileName($newValue);
            $this->writeLog('Saved ' . $fileName);

// updte filePath into array
            $this->sitemapNameArr[$key] = $newValue;
        }

// get full sitemap
        $fullSitemapNameArr = $this->getSitemapNames();

// create an array of all sitemaps to delete
        $sitemapToDeleteArr = array_diff($fullSitemapNameArr, $this->sitemapNameArr);

// delete old missing sitemaps
        foreach ($sitemapToDeleteArr as $value) {
            $this->delete($value);

            $fileName = $this->getFileName($value);
            $this->writeLog('Deleted ' . $fileName);
        }

        if ($this->checkSitemapSize() !== true) {
            $this->writeLog('Execution has been stopped because of checkSitemapSize error');

            $this->stopExec();
        }

// set new sitemap is available
        $this->newSitemapAvailable();

// rewrite robots.txt
        $this->getRewriteRobots();

        $this->getTotalUrls();
        $this->getExtUrls();

// print type list if setted to true
        if ($this->printTypeList === true) {
            $this->getTypeList();
        }

// print changefreq list if setted to true
        if ($this->printChangefreqList === true) {
            $this->getChangefreqList();
        }

// print priority list if setted to true
        if ($this->printPriorityList === true) {
            $this->getPriorityList();
        }

// print malformed list if setted to true
        if ($this->printMalfUrls === true) {
            $this->getMalfList();
        }

// optimize tables
        $this->optimTables();

        $endTime = time();
        $execTime = gmdate('H:i:s', $endTime - $this->startTime);

        $this->writeLog('Total execution time ' . $execTime);
        $this->writeLog('##### Execution end' . PHP_EOL . PHP_EOL);

// update last execution time and set exec to n (a full scan has been successfully done) plus write version of getSeoSitemap
        $this->query = "UPDATE getSeoSitemapExec "
            . "SET version = '$this->version',  mDate = '$endTime', exec = 'n' WHERE func = 'getSeoSitemap' LIMIT 1";
        $this->execQuery();

// close msqli connection
        $this->closeMysqliConn();

    }
################################################################################
################################################################################

    private function setUrlsToRobotsSkip()
    {

        $this->query = "SELECT url FROM getSeoSitemap";

        $this->execQuery();

// set rSkip following robots.txt rules
        foreach ($this->row as $key => $v1) {
            foreach ($this->skipUrl as $v2) {

                if (strpos($v1['url'], $v2) === 0 || fnmatch($v2, $v1['url']) === true) {

                    if (empty($this->allowUrl) === false) {

                        foreach ($this->allowUrl as $v3) {
                            if (strpos($v1['url'], $v3) !== 0) {
                                if (strpos($v1['url'], '*') !== false) {
                                    if (fnmatch($v3, $v1['url']) === false) {
                                        $this->setRobotsSkip($v1['url']);
                                        break 2;
                                    }
                                } else {
                                    $this->setRobotsSkip($v1['url']);
                                    break 2;
                                }
                            } else {
                                break 1;
                            }
                        }
                    } else {
                        $this->setRobotsSkip($v1['url']);
                        break 1;
                    }
                }
            }
        }

        $this->writeLog('Setted URLs to skip following robots.txt rules');

    }
################################################################################
################################################################################

    private function setRobotsSkip($url)
    {

        $this->query = "UPDATE getSeoSitemap SET state = 'rSkip' WHERE url = '" . $url . "' LIMIT 1";
        $this->execQuery();

    }
################################################################################
################################################################################

    private function checkSkipUrls()
    {

        $this->query = "SELECT url FROM getSeoSitemap WHERE state IN ('skip', 'rSkip') AND url NOT LIKE 'mailto:%'";
        $this->execQuery();

        if ($this->rowNum > 0) {
            $this->stmt = $this->mysqli->prepare("UPDATE getSeoSitemap SET "
                . "size = ?, "
                . "httpCode = ? "
                . "WHERE url = ? LIMIT 1");
            if ($this->stmt === false) {
                $this->writeLog('Execution has been stopped because of MySQL prepare error: ' . lcfirst($this->mysqli->error));

                $this->stopExec();
            }

            foreach ($this->row as $value) {
                $url = $value['url'];
                $this->getPage($url);

                if ($this->stmt->bind_param('sss', $this->size, $this->httpCode, $url) !== true) {
                    $this->writeLog('Execution has been stopped because of MySQL error binding parameters: ' . lcfirst($this->stmt->error));

                    $this->stopExec();
                }

                if ($this->stmt->execute() !== true) {
                    $this->writeLog('Execution has been stopped because of MySQL execute error: ' . lcfirst($this->stmt->error));

                    $this->stopExec();
                }
            }
        }

    }
################################################################################
################################################################################
// print all URLs into sitemap in an alphaberic order

    private function closeMysqliStmt()
    {

        if ($this->stmt->close() !== true) {
            $this->writeLog('Execution has been stopped because of MySQL stmt close error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

        if ($this->stmt2->close() !== true) {
            $this->writeLog('Execution has been stopped because of MySQL stmt2 close error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

        if ($this->stmt3->close() !== true) {
            $this->writeLog('Execution has been stopped because of MySQL stmt3 close error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

        if ($this->stmt4->close() !== true) {
            $this->writeLog('Execution has been stopped because of MySQL stmt4 close error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

        if ($this->stmt5->close() !== true) {
            $this->writeLog('Execution has been stopped because of MySQL stmt5 close error: ' . lcfirst($this->mysqli->error));

            $this->stopExec();
        }

    }
################################################################################
################################################################################
// open curl connection

    private function getSizeList()
    {

        $kbBingMaxSize = $this->getKb($this->pageMaxSize);

        $this->query = "SELECT url, size FROM getSeoSitemap WHERE size > '" . $this->pageMaxSize
            . "' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200'";
        $this->execQuery();

        $this->writeLog('##### URLs with size > ' . $kbBingMaxSize . ' Kb into sitemap (SEO: page size should be lower than '
            . $kbBingMaxSize . ' Kb)');

        $i = 0;
        if ($this->rowNum > 0) {
            asort($this->row);
            foreach ($this->row as $v) {
                foreach ($this->seoExclusion as $value) {
                    $fileExt = $this->getUrlExt($v['url']);

                    if ($value !== $fileExt) {
                        $this->writeLog('Size: ' . $this->getKb($v['size']) . ' Kb - URL: ' . $v['url']);
                        $i++;
                    }
                }
            }
        }

        $this->writeLog('##########');
        $this->writeLog($i . ' URLs with size > ' . $kbBingMaxSize . ' Kb into sitemap' . PHP_EOL);

    }

    /**
     * Convert bytes into KB
     *
     * @param int $byte
     * @return string
     */
    private function getKb(int $byte): string
    {
        return sprintf('%0.2f', round($byte / 1024, 2));
    }




################################################################################
################################################################################
// update execution value

//     private function getMinTitleLengthList()
//     {
//
//         $this->query = "SELECT url, CHAR_LENGTH(title) AS titleLength FROM getSeoSitemap WHERE CHAR_LENGTH(title) < "
//             . $this->titleLength[0] . " AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND title IS NOT NULL";
//         $this->execQuery();
//
//         $i = 0;
//         if ($this->rowNum > 0) {
//             $this->writeLog('##### URLs with title length < ' . $this->titleLength[0]
//                 . ' characters into sitemap (SEO: page title length should be higher than ' . $this->titleLength[0] . ' characters)');
//
//             asort($this->row);
//             foreach ($this->row as $v) {
//                 foreach ($this->seoExclusion as $value) {
//                     $fileExt = $this->getUrlExt($v['url']);
//
//                     if ($value !== $fileExt) {
//                         $this->writeLog('Title length: ' . $v['titleLength'] . ' characters - URL: ' . $v['url']);
//                         $i++;
//                     }
//                 }
//             }
//
//             $this->writeLog('##########');
//         }
//
//         $this->writeLog($i . ' URLs with title length < ' . $this->titleLength[0] . ' characters into sitemap');
//
//     }
// ################################################################################
// ################################################################################
// // update error counter
//
//     private function getMaxTitleLengthList()
//     {
//
//         $this->query = "SELECT url, CHAR_LENGTH(title) AS titleLength FROM getSeoSitemap WHERE CHAR_LENGTH(title) > "
//             . $this->titleLength[1] . " AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND title IS NOT NULL";
//         $this->execQuery();
//
//         $i = 0;
//         if ($this->rowNum > 0) {
//             $this->writeLog('##### URLs with title length > ' . $this->titleLength[1]
//                 . ' characters into sitemap (SEO: page title length should be lower than ' . $this->titleLength[1] . ' characters)');
//
//             asort($this->row);
//             foreach ($this->row as $v) {
//                 foreach ($this->seoExclusion as $value) {
//                     $fileExt = $this->getUrlExt($v['url']);
//
//                     if ($value !== $fileExt) {
//                         $this->writeLog('Title length: ' . $v['titleLength'] . ' characters - URL: ' . $v['url']);
//                         $i++;
//                     }
//                 }
//             }
//
//             $this->writeLog('##########');
//         }
//
//         $this->writeLog($i . ' URLs with title length > ' . $this->titleLength[1] . ' characters into sitemap' . PHP_EOL);
//
//     }
// ################################################################################
// ################################################################################
// // delete a file
//
//     private function getDuplicateTitle()
//     {
//
//         $this->query = "SELECT title FROM getSeoSitemap WHERE state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND"
//             . " title IS NOT NULL GROUP BY title HAVING COUNT(*) > 1";
//         $this->execQuery();
//
//         $rowNum = $this->rowNum;
//         $row = $this->row;
//
//         $i = 0;
//
//         if ($rowNum > 0) {
//             $this->writeLog('##### URLs with duplicate title into sitemap (SEO: URLs should have unique title into the website)');
//
//             asort($row);
//
//             foreach ($row as $v) {
//                 $this->query = "SELECT url, title FROM getSeoSitemap WHERE title = '"
//                     . $v['title'] . "' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200'";
//                 $this->execQuery();
//
//                 foreach ($this->row as $v2) {
//                     $this->writeLog('Duplicate title: ' . $v2['title'] . ' - URL: ' . $v2['url']);
//                     $i++;
//                 }
//             }
//
//             $this->writeLog('##########');
//         }
//
//         $this->writeLog($i . ' URLs with duplicate title into sitemap');
//
//     }
// ################################################################################
// ################################################################################
// // get URL entity escaping
//
//     private function getMinDescriptionLengthList()
//     {
//
//         $this->query = "SELECT url, CHAR_LENGTH(description) AS descriptionLength FROM getSeoSitemap WHERE CHAR_LENGTH(description) < "
//             . $this->descriptionLength[0] . " AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND title IS NOT NULL";
//         $this->execQuery();
//
//         $i = 0;
//         if ($this->rowNum > 0) {
//             $this->writeLog('##### URLs with description length < ' . $this->descriptionLength[0]
//                 . ' characters into sitemap (SEO: page description length should be higher than ' . $this->descriptionLength[0] . ' characters)');
//
//             asort($this->row);
//             foreach ($this->row as $v) {
//                 foreach ($this->seoExclusion as $value) {
//                     $fileExt = $this->getUrlExt($v['url']);
//
//                     if ($value !== $fileExt) {
//                         $this->writeLog('Description length: ' . $v['descriptionLength'] . ' characters - URL: ' . $v['url']);
//                         $i++;
//                     }
//                 }
//             }
//
//             $this->writeLog('##########');
//         }
//
//         $this->writeLog($i . ' URLs with description length < ' . $this->descriptionLength[0] . ' characters into sitemap');
//
//     }
// ################################################################################
// ################################################################################
//
//     private function getMaxDescriptionLengthList()
//     {
//
//         $this->query = "SELECT url, CHAR_LENGTH(description) AS descriptionLength FROM getSeoSitemap WHERE CHAR_LENGTH(description) > "
//             . $this->descriptionLength[1] . " AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND description IS NOT NULL";
//         $this->execQuery();
//
//         $i = 0;
//         if ($this->rowNum > 0) {
//             $this->writeLog('##### URLs with description length > ' . $this->descriptionLength[1]
//                 . ' characters into sitemap (SEO: page description length should be lower than ' . $this->descriptionLength[1] . ' characters)');
//
//             asort($this->row);
//             foreach ($this->row as $v) {
//                 foreach ($this->seoExclusion as $value) {
//                     $fileExt = $this->getUrlExt($v['url']);
//
//                     if ($value !== $fileExt) {
//                         $this->writeLog('Description length: ' . $v['descriptionLength'] . ' characters - URL: ' . $v['url']);
//                         $i++;
//                     }
//                 }
//             }
//
//             $this->writeLog('##########');
//         }
//
//         $this->writeLog($i . ' URLs with description length > ' . $this->descriptionLength[1] . ' characters into sitemap');
//
//     }
// ################################################################################
// ################################################################################
//
//     private function getDuplicateDescription()
//     {
//
//         $this->query = "SELECT description FROM getSeoSitemap WHERE state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND"
//             . " title IS NOT NULL GROUP BY title HAVING COUNT(*) > 1";
//         $this->execQuery();
//
//         $rowNum = $this->rowNum;
//         $row = $this->row;
//
//         $i = 0;
//
//         if ($rowNum > 0) {
//             $this->writeLog('##### URLs with duplicate description into sitemap (SEO: URLs should have unique description into the website)');
//
//             asort($row);
//
//             foreach ($row as $v) {
//                 $this->query = "SELECT url, description FROM getSeoSitemap WHERE description = '"
//                     . $v['description'] . "' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200'";
//                 $this->execQuery();
//
//                 foreach ($this->row as $v2) {
//                     $this->writeLog('Duplicate description: ' . $v2['description'] . ' - URL: ' . $v2['url']);
//                     $i++;
//                 }
//             }
//
//             $this->writeLog('##########');
//         }
//
//         $this->writeLog($i . ' URLs with duplicate description into sitemap');
//
//     }
// ################################################################################
// ################################################################################
// // get file name without the rest of path
//
//     private function getIntUrls()
//     {
//
//         $this->query = "SELECT url, callerUrl FROM getSeoSitemap WHERE state IN ('skip', 'rSkip') AND url LIKE '" . DOMAINURL . "%'";
//         $this->execQuery();
//
// // print list of URLs into domain out of sitemap if PRINTSKIPURLS === true
//         if (PRINTSKIPURLS === true) {
//             $this->writeLog('##### URLs into domain out of sitemap');
//
//             if ($this->rowNum > 0) {
//                 asort($this->row);
//
//                 foreach ($this->row as $value) {
//                     $this->writeLog('URL: ' . $value['url'] . ' - caller URL: ' . $value['callerUrl']);
//                 }
//             }
//
//             $this->writeLog('##########');
//         }
//
//         $this->writeLog($this->rowNum . ' URLs into domain out of sitemap' . PHP_EOL);
//
//     }
// ################################################################################
// ################################################################################
// // get all sitemap names included in SITEMAPPATH
//
//     private function setPriority()
//     {
//
//         $this->query = "UPDATE getSeoSitemap SET priority = '" . DEFAULTPRIORITY . "' WHERE state != 'skip' AND state != 'rSkip'";
//         $this->execQuery();
//
//         foreach ($this->partialUrlPriority as $key => $value) {
//             foreach ($value as $v) {
//                 $this->query = "UPDATE getSeoSitemap SET priority = '" . $key . "' "
//                     . "WHERE url LIKE '" . $v . "%' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND size != 0";
//                 $this->execQuery();
//             }
//         }
//
//         foreach ($this->fullUrlPriority as $key => $value) {
//             foreach ($value as $v) {
//                 $this->query = "UPDATE getSeoSitemap SET priority = '" . $key . "' "
//                     . "WHERE url = '" . $v . "' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND size != 0 LIMIT 1";
//                 $this->execQuery();
//             }
//         }
//
// // $priority includes all priority values
//         $priority = [];
//         $priority = array_merge(array_keys($this->partialUrlPriority), array_keys($this->fullUrlPriority));
//         $priority[] = DEFAULTPRIORITY;
//         rsort($priority);
//
//         foreach ($priority as $value) {
//             $this->query = "SELECT COUNT(*) AS count FROM getSeoSitemap "
//                 . "WHERE priority = '" . $value . "' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND size != 0";
//             $this->execQuery();
//
//             $this->writeLog("Setted priority " . $value . " to " . $this->count . " URLs into sitemap");
//         }
//
//     }
// ################################################################################
// ################################################################################
// // detect if enconding is UTF-8
//
//     private function save()
//     {
//
//         $this->query = "SELECT url, lastmod, changefreq, priority FROM getSeoSitemap "
//             . "WHERE httpCode = '200' AND size != 0 AND state = 'scan'";
//         $this->execQuery();
//
// // set total URLs into sitemap
//         $this->totUrls = $this->rowNum;
//
// // stop exec if total URLs to insert is higher than $maxTotalUrls
//         if ($this->totUrls > $this->maxTotalUrls) {
//             $this->writeLog("Execution has been stopped because of total URLs to insert into sitemap is $this->totUrls "
//                 . "and higher than max limit of $this->maxTotalUrls");
//
//             $this->stopExec();
//         }
//
// // set sitemap counter start value
//         $sitemapCount = null;
//
//         if ($this->totUrls > $this->maxUrlsInSitemap) {
//             $sitemapCount = 1;
//             $this->multipleSitemaps = true;
//         }
//
//         // general row counter + sitemap internal row counter
//         $genCount = $sitemapIntCount = 1;
//
//         foreach ($this->row as $value) {
//
//             if ($sitemapCount > $this->maxUrlsInSitemap) {
//                 $this->writeLog('Execution has been stopped because total sitemaps are more than ' . $this->maxUrlsInSitemap);
//
//                 $this->stopExec();
//             }
//
//             if ($sitemapIntCount === 1) {
//
    /*  TODO: Add '?>' */
//                 $txt = <<<EOD
// <?xml version='1.0' encoding='UTF-8'
// <urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
// <!-- Created with $this->userAgent -->
//
// EOD;
//
//             }
//
//             $dT = new DateTime();
//             $dT->setTimestamp($value['lastmod']);
//             $lastmod = $dT->format(DATE_W3C);
//
//             $url = $this->entityEscaping($value['url']);
//
//             $txt .= '<url><loc>' . $url . '</loc><lastmod>' . $lastmod . '</lastmod>'
//                 . '<changefreq>' . $value['changefreq'] . '</changefreq><priority>' . $value['priority'] . '</priority></url>
// ';
//
//             if ($sitemapIntCount === $this->maxUrlsInSitemap || $genCount === $this->totUrls) {
//                 $sitemapIntCount = 0;
//
//                 $txt .= <<<EOD
// </urlset>
// EOD;
//
//                 $sitemapFile = 'sitemap' . $sitemapCount . '.xml';
//
//                 if (file_put_contents(SITEMAPPATH . $sitemapFile, $txt) === false) {
//                     $this->writeLog('Execution has been stopped because of file_put_contents cannot write ' . $sitemapFile);
//
//                     $this->stopExec();
//                 }
//
//                 $this->writeLog('Saved ' . $sitemapFile);
//                 $this->sitemapNameArr[] = SITEMAPPATH . $sitemapFile;
//
//                 $utf8Enc = $this->detectUtf8Enc($txt);
//
//                 if ($utf8Enc !== true) {
//                     $this->writeLog('Execution has been stopped because of ' . $sitemapFile . ' is not UTF-8 encoded');
//
//                     $this->stopExec();
//                 }
//
//                 if ($this->multipleSitemaps === true && $genCount !== $this->totUrls) {
//                     $sitemapCount++;
//                 }
//
//             }
//
//             $sitemapIntCount++;
//             $genCount++;
//         }
//
// // if there are multiple sitemaps, save sitemapindex
//         if ($this->multipleSitemaps === true) {
//             $time = time();
//
//             $dT = new DateTime();
//             $dT->setTimestamp($time);
//             $lastmod = $dT->format(DATE_W3C);
//
    /*  TODO: add '?>' */
//             $txt = <<<EOD
// <?xml version='1.0' encoding='UTF-8'
// <sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
// <!-- Created with $this->userAgent -->
//
// EOD;
//
//             foreach ($this->sitemapNameArr as $value) {
//
// // get sitemap URL
//                 $sitemapUrl = DOMAINURL . '/' . $this->getFileName($value) . '.gz';
//
//                 $txt .= '<sitemap><loc>' . $sitemapUrl . '</loc><lastmod>' . $lastmod . '</lastmod></sitemap>
// ';
//             }
//
//             $txt .= <<<EOD
// </sitemapindex>
// EOD;
//
//             $sitemapFile = 'sitemapindex.xml';
//
//             if (file_put_contents(SITEMAPPATH . $sitemapFile, $txt) === false) {
//                 $this->writeLog('Execution has been stopped because of file_put_contents cannot write ' . $sitemapFile);
//
//                 $this->stopExec();
//             }
//
//             $this->writeLog('Saved ' . $sitemapFile);
//             $this->sitemapNameArr[] = SITEMAPPATH . $sitemapFile;
//
//             $utf8Enc = $this->detectUtf8Enc($txt);
//
//             if ($utf8Enc !== true) {
//                 $this->writeLog('Execution has been stopped because of ' . $sitemapFile . ' is not UTF-8 encoded');
//
//                 $this->stopExec();
//             }
//         }
//
//         return true;
//
//     }
// ################################################################################
// ################################################################################
//
//     private function entityEscaping($url)
//     {
//
//         foreach ($this->escapeCodeArr as $key => $value) {
//             $url = str_replace($key, $value, $url);
//         }
//
//         return $url;
//
//     }
//
//     /**
//      * Detect UTF-8
//      *
//      * @param $str
//      * @return bool
//      */
//     private function detectUtf8Enc($str): bool
//     {
//         if (mb_detect_encoding($str, 'UTF-8', true) === 'UTF-8') {
//             return true;
//         }
//
//         return false;
//     }
// ################################################################################
// ################################################################################
// // get URL extension
//
//     private function getFileName($filePath)
//     {
//
//         $fileName = str_replace(SITEMAPPATH, '', $filePath);
//
//         return $fileName;
//
//     }
// ################################################################################
// ################################################################################
// // check all sitemap sizes. they must be non larger than $sitemapMaxSize
//
//     private function gzip($fileName)
//     {
//
//         $gzFile = $fileName . '.gz';
//
//         $fp = gzopen($gzFile, 'w9');
//
//         if ($fp === false) {
//             $this->writeLog('Execution has been stopped because of gzopen cannot open ' . $gzFile);
//
//             $this->stopExec();
//         }
//
//         $fileCont = file_get_contents($fileName);
//         if ($fileCont === false) {
//             $this->writeLog('Execution has been stopped because of file_get_contents cannot get content of ' . $fileName);
//
//             $this->stopExec();
//         }
//
//         gzwrite($fp, $fileCont);
//
//         if (gzclose($fp) !== true) {
//             $this->writeLog('Execution has been stopped because of gzclose cannot close ' . $gzFile);
//
//             $this->stopExec();
//         }
//
//     }
// ################################################################################
// ################################################################################
// // rewrite robots.txt with new sitemap infos
//
//     private function getSitemapNames()
//     {
//
//         $sitemapNameArr = glob(SITEMAPPATH . 'sitemap*.xml*');
//
//         if ($sitemapNameArr !== false) {
//             return $sitemapNameArr;
//         } else {
//             $this->writeLog('Execution has been stopped because of glob error');
//
//             $this->stopExec();
//         }
//
//     }
// ################################################################################
// ################################################################################
// // check tables
//
//     private function delete($fileName)
//     {
//
//         if (unlink($fileName) === false) {
//             $this->writeLog('Execution has been stopped because of unlink cannot delete sitemap.xml');
//
//             $this->stopExec();
//         }
//
//     }
// ################################################################################
// ################################################################################
// // optimize tables
//
//     private function checkSitemapSize()
//     {
//
//         if ($this->printSitemapSizeList === true) {
//             $this->writeLog('##### Sitemap sizes');
//         }
//
//         foreach ($this->sitemapNameArr as $value) {
//             $fileName = $this->getFileName($value);
//
//             $size = filesize($value);
//
//             if ($size === false) {
//                 $this->writeLog('Execution has been stopped because of filesize error checking ' . $fileName);
//
//                 $this->stopExec();
//             } elseif ($size > $this->sitemapMaxSize) {
//                 $this->writeLog('Warnuing: size of ' . $fileName . ' is larger than ' . $this->sitemapMaxSize . ' - double-check that file to fix it!');
//             }
//
//             if ($this->printSitemapSizeList === true) {
//                 $this->writeLog('Size: ' . round($size * 0.0009765625, 2) . ' Kb - sitemap: ' . $fileName);
//             }
//         }
//
//         if ($this->printSitemapSizeList === true) {
//             $this->writeLog('##########' . PHP_EOL);
//         }
//
//         return true;
//
//     }
// ################################################################################
// ################################################################################
//
//     private function newSitemapAvailable()
//     {
//
//         $this->query = "UPDATE getSeoSitemapExec SET newData = 'y' WHERE func = 'getSeoSitemap' LIMIT 1";
//         $this->execQuery();
//
//     }
// ################################################################################
// ################################################################################
// // get number from version (examples: v12.2 => 1220, v11.2.2 => 1122, v3.1.1 => 311, v3.1 => 310)
//
//     private function getRewriteRobots()
//     {
//
// // remove all old sitemap lines from robots.txt
//         foreach ($this->robotsLines as $key => $value) {
//             if ($value === '# Sitemap' || $value === '# Sitemapindex' || strpos($value, 'Sitemap: ') === 0) {
//                 unset($this->robotsLines[$key]);
//             }
//         }
//
//         if ($this->multipleSitemaps !== true) {
//             $this->robotsLines[] = '# Sitemap';
//             $this->robotsLines[] = 'Sitemap: ' . DOMAINURL . '/sitemap.xml.gz';
//         } else {
//             $this->robotsLines[] = '# Sitemapindex';
//             $this->robotsLines[] = 'Sitemap: ' . DOMAINURL . '/sitemapindex.xml.gz';
//         }
//
//         $newCont = null;
//
// // get new file content
//         foreach ($this->robotsLines as $key => $value) {
//             $newCont .= $value . PHP_EOL;
//         }
//
// // rewrite file
//         if (file_put_contents($this->robotsPath, $newCont) === false) {
//             $this->writeLog('Execution has been stopped because of file_put_contents cannot write robots.txt');
//
//             $this->stopExec();
//         }
//
//         $this->writeLog('Wrote robots.txt' . PHP_EOL);
//
//     }
// ################################################################################
// ################################################################################
// // get version number of database
//
//     private function getTotalUrls()
//     {
//
//         $this->writeLog('################################');
//         $this->writeLog('Included ' . $this->totUrls . ' URLs into sitemap');
//         $this->writeLog('################################' . PHP_EOL);
//
//     }
// ################################################################################
// ################################################################################
//
//     private function getExtUrls()
//     {
//
//         $this->query = "SELECT url, callerUrl FROM getSeoSitemap WHERE state = 'skip' AND url NOT LIKE '" . DOMAINURL . "%'";
//         $this->execQuery();
//
// // print list of URLs out of domain out of sitemap if PRINTSKIPURLS === true
//         if (PRINTSKIPURLS === true) {
//             $this->writeLog('##### URLs out of domain out of sitemap');
//
//             if ($this->rowNum > 0) {
// // sort ascending
//                 asort($this->row);
//
//                 foreach ($this->row as $value) {
//                     $this->writeLog('URL: ' . $value['url'] . ' - caller URL: ' . $value['callerUrl']);
//                 }
//             }
//
//             $this->writeLog('##########');
//         }
//
//         $this->writeLog($this->rowNum . ' URLs out of domain out of sitemap');
//
//     }
// ################################################################################
// ################################################################################
//
//     private function getTypeList()
//     {
//
//         $this->query = "SELECT url FROM getSeoSitemap WHERE httpCode = '200' AND size != 0 AND state = 'scan'";
//         $this->execQuery();
//
//         $this->writeLog('##### All URLs into sitemap');
//
//         if ($this->rowNum > 0) {
//             asort($this->row);
//             foreach ($this->row as $v) {
//                 $this->writeLog($v['url']);
//             }
//         }
//
//         $this->writeLog('##########' . PHP_EOL);
//
//     }
// ################################################################################
// ################################################################################
// // prepare mysqli statements
//
//     private function getChangefreqList()
//     {
//
//         foreach ($this->changefreqArr as $value) {
//             $this->query = "SELECT url FROM getSeoSitemap "
//                 . "WHERE changefreq = '$value' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND size != 0";
//             $this->execQuery();
//
//             $this->writeLog('##### URLs with ' . $value . ' change frequency into sitemap');
//
//             if ($this->rowNum > 0) {
//                 asort($this->row);
//                 foreach ($this->row as $v) {
//                     $this->writeLog($v['url']);
//                 }
//             }
//
//             $this->writeLog('##########' . PHP_EOL);
//         }
//
//     }
// ################################################################################
// ################################################################################
// // get absolute url from relative url
//
//     private function getPriorityList()
//     {
//
//         foreach ($this->priorityArr as $value) {
//             $this->query = "SELECT url FROM getSeoSitemap WHERE priority = '" . $value
//                 . "' AND state != 'skip' AND state != 'rSkip' AND httpCode = '200' AND size != 0";
//             $this->execQuery();
//
//             $this->writeLog('##### URLs with ' . $value . ' priority into sitemap');
//
//             if ($this->rowNum > 0) {
//                 asort($this->row);
//                 foreach ($this->row as $v) {
//                     $this->writeLog($v['url']);
//                 }
//             }
//
//             $this->writeLog('##########' . PHP_EOL);
//         }
//
//     }
// ################################################################################
// ################################################################################
//
//     private function getMalfList()
//     {
//
//         $i = 0;
//
//         foreach ($this->malfChars as $value) {
//             $this->query = "SELECT url FROM getSeoSitemap WHERE url LIKE '%" . $value
//                 . "%' AND url LIKE '" . DOMAINURL . "%'";
//             $this->execQuery();
//
//             if ($this->rowNum > 0) {
//                 $this->writeLog("##### URLs with '$value' malformed character into domain (good pratice: do not use that character in URL address)");
//
//                 asort($this->row);
//                 foreach ($this->row as $v) {
//                     $this->writeLog($v['url']);
//
//                     $i++;
//                 }
//
//                 $this->writeLog('##########');
//             }
//
//             $this->writeLog($i . ' URLs with malformed characters into domain' . PHP_EOL);
//         }
//
//     }
// ################################################################################
// ################################################################################
// // get data from robots.txt to set $skipUrl and allowUrl
//
//     private function optimTables()
//     {
//
// // remove gaps in id primary key of getSeoSitemap
//         $this->query = "SET @count = 0; "
//             . "UPDATE getSeoSitemap SET id = @count := @count + 1";
//         $this->execMultiQuery();
//
// // optimize getSeoSitemap
//         $this->query = "OPTIMIZE TABLE getSeoSitemap";
//         $this->execQuery();
//
//         $this->writeLog('Optimized getSeoSitemap table');
//
//     }
// ################################################################################
// ################################################################################
// // set rSkip
//
//     private function execMultiQuery()
//     {
//
//         if ($this->mysqli->multi_query($this->query)) {
//             do {
//                 if ($result = $this->mysqli->store_result()) {
//                     $result->free_result();
//                 }
//             } while ($this->mysqli->next_result());
//         } else {
//             $this->writeLog('Execution has been stopped because of MySQL multi_query error. Error ('
//                 . $this->mysqli->errno . '): ' . lcfirst($this->mysqli->error) . ' - query: ' . $this->query . $this->txtToAddOnMysqliErr);
//             exit();
//         }
//
//         if ($this->mysqli->errno) {
//             $this->writeLog('Execution has been stopped because of MySQL multi_query error. Error ('
//                 . $this->mysqli->errno . '): ' . lcfirst($this->mysqli->error) . ' - query: ' . $this->query . $this->txtToAddOnMysqliErr);
//             exit();
//         }
//
//         if ($this->mysqli->warning_count > 0) {
//             if ($warnRes = $this->mysqli->query("SHOW WARNINGS")) {
//                 $warnRow = $warnRes->fetch_row();
//
//                 $warnMsg = sprintf("%s (%d): %s", $warnRow[0], $warnRow[1], lcfirst($warnRow[2]));
//                 $this->writeLog($warnMsg . ' - query: "' . $this->query . '"');
//
//                 $warnRes->close();
//             }
//         }
//
//     }
//
// // set URLs to robots skip
//
//     private function closeMysqliConn()
//     {
//
//         if ($this->mysqli->close() !== true) {
//             $this->writeLog('Execution has been stopped because of MySQL mysqli close error: '
//                 . lcfirst($this->mysqli->error) . $this->txtToAddOnMysqliErr);
//             exit();
//         }
//
//     }
}