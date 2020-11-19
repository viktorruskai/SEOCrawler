<?php

namespace App\Tests\Controllers;

use App\Tests\AppTestTrait;
use PHPUnit\Framework\TestCase;

class AnalyzeControllerTest extends TestCase
{
    use AppTestTrait;

    /**
     * Websites with positive response (everything is fine)
     *
     * @return string[]
     */
    public function goodWebsitesDataProvider(): array
    {
        return [
            ['http://localhost:8080/test/testOk.html'],
        ];
    }

    /**
     * Analyze positive test
     *
     * @dataProvider goodWebsitesDataProvider
     * @param string $url
     */
    public function testAnalyzePositive(string $url): void
    {
        $request = $this->createRequest('POST', '/analyze', [
            'url' => $url,
        ]);

        // Make request and fetch response
        $response = $this->app->handle($request);

        // Asserts
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * Analyze negative test
     */
}