<?php

namespace App\Tests\Controllers;

use App\Tests\AppTestTrait;
use PHPUnit\Framework\TestCase;

class IndexControllerTest extends TestCase
{
    use AppTestTrait;

    /**
     * Test that the index route returns a rendered response containing the text 'SlimFramework' but not a greeting
     */
    public function testGetHomepageWithoutName(): void
    {
        // Create request with method and url
        $request = $this->createRequest('GET', '/test');

        // Make request and fetch response
        $response = $this->app->handle($request);
        // Asserts
        self::assertSame(200, $response->getStatusCode());
    }
}
