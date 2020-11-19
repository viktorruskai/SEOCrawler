<?php

namespace App\Tests\Services;

use App\Exceptions\SeoException;
use App\Services\SeoService;
use App\Tests\AppTestTrait;
use PHPUnit\Framework\TestCase;

class SeoServiceTest extends TestCase
{
    use AppTestTrait;

    /** @var SeoService $seoService */
    protected SeoService $seoService;

    /**
     * @throws SeoException
     */
    public function setUp(): void
    {
        // Init of SeoService
//        $this->seoService = new SeoService('', []);
    }

    public function tearDown(): void
    {
        unset($this->seoService);
    }

    /**
     * Test that the index route returns a rendered response containing the text 'SlimFramework' but not a greeting
     */
    public function testSomething(): void
    {
        self::assertTrue(true);
//        // Create request with method and url
//        $request = $this->createRequest('GET', '/test');
//
//        // Make request and fetch response
//        $response = $this->app->handle($request);
//
//        // Asserts
//        self::assertSame(200, $response->getStatusCode());
    }
}
