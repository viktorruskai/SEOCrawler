<?php
declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Tests\AppTestTrait;
use JsonException;
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
            ['http://127.0.0.1:8080/test/ok.php'],
        ];
    }

    /**
     * Websites with negative response (SEO problems)
     *
     * @return string[]
     */
    public function badWebsitesDataProvider(): array
    {
        return [
            ['https://www.google.com'],
        ];
    }

    /**
     * Analyze positive test
     *
     * @dataProvider goodWebsitesDataProvider
     * @param string $url
     * @throws JsonException
     */
    public function testAnalyzePositive(string $url): void
    {
        $request = $this->createJsonRequest('POST', '/analyze', [
            'url' => $url,
        ]);

        // Make request and fetch response
        $response = $this->app->handle($request);

        $responseJson = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        // Asserts
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('success', $responseJson['status']);
        self::assertTrue($responseJson['data']['isSeoGood']);
        self::assertSame($url, $responseJson['data']['websiteName']);
        self::assertEmpty($responseJson['data']['results']);
    }

    /**
     * Analyze negative test
     *
     * @dataProvider badWebsitesDataProvider
     * @param string $url
     * @throws JsonException
     */
    public function testAnalyzeNegative(string $url): void
    {
        $request = $this->createJsonRequest('POST', '/analyze', [
            'url' => $url,
        ]);

        // Make request and fetch response
        $response = $this->app->handle($request);

        $responseJson = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        // Asserts
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('success', $responseJson['status']);
        self::assertFalse($responseJson['data']['isSeoGood']);
        self::assertSame($url, $responseJson['data']['websiteName']);
        self::assertNotEmpty($responseJson['data']['results']);
    }
}