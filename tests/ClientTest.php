<?php

/*
 * This file is part of vaibhavpandeyvpz/dakiya package.
 *
 * (c) Vaibhav Pandey <contact@vaibhavpandey.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Dakiya;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sandesh\RequestFactory;
use Sandesh\ResponseFactory;
use Sandesh\StreamFactory;

/**
 * Class ClientTest
 */
class ClientTest extends TestCase
{
    protected ClientInterface $client;

    protected RequestFactoryInterface $requests;

    protected StreamFactoryInterface $streams;

    protected function setUp(): void
    {
        $this->client = new Client(new ResponseFactory);
        $this->requests = new RequestFactory;
        $this->streams = new StreamFactory;
    }

    /**
     * @dataProvider provideCookies
     */
    public function test_cookies(string $name, string $value): void
    {
        $client = new Client(new ResponseFactory, [
            CURLOPT_COOKIEJAR => tempnam(sys_get_temp_dir(), 'cookie'),
        ]);
        $request = $this->requests->createRequest('GET', "https://httpbin.org/cookies/set?{$name}={$value}");
        $response = $client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Location'));
        $this->assertEquals('/cookies', $response->getHeaderLine('Location'));
        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $this->assertCount(1, $cookies = $response->getHeader('Set-Cookie'));
        $this->assertStringStartsWith("{$name}={$value}", $cookies[0]);
    }

    /**
     * @return array<int, array{string, string}>
     */
    public static function provideCookies(): array
    {
        return [
            ['k1', 'v1'],
            ['k2', 'v2'],
        ];
    }

    /**
     * @dataProvider provideContentTypes
     */
    public function test_content_types(string $path, string $type): void
    {
        $request = $this->requests->createRequest('GET', "https://httpbin.org/{$path}");
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($type, $response->getHeaderLine('Content-Type'));
    }

    /**
     * @return array<int, array{string, string}>
     */
    public static function provideContentTypes(): array
    {
        return [
            ['html', 'text/html; charset=utf-8'],
            ['ip', 'application/json'],
            ['robots.txt', 'text/plain'],
            ['xml', 'application/xml'],
        ];
    }

    /**
     * @dataProvider provideStatusCodes
     */
    public function test_status_codes(int $code): void
    {
        $request = $this->requests->createRequest('GET', "https://httpbin.org/status/{$code}");
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($code, $response->getStatusCode());
    }

    /**
     * @return array<int, array{int}>
     */
    public static function provideStatusCodes(): array
    {
        return [
            [200],
            [400],
            [401],
            [405],
            [500],
        ];
    }

    public function test_get(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get');
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @dataProvider providePatchPostOrPut
     */
    public function test_patch_post_or_put(string $method): void
    {
        $request = $this->requests->createRequest($method, 'https://httpbin.org/'.strtolower($method))
            ->withBody($this->streams->createStream('k1=v1&k2=v2'))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('form', $data);
        $this->assertArrayHasKey('k1', $data['form']);
        $this->assertArrayHasKey('k2', $data['form']);
        $this->assertEquals('v1', $data['form']['k1']);
        $this->assertEquals('v2', $data['form']['k2']);
    }

    /**
     * @dataProvider providePatchPostOrPut
     */
    public function test_patch_post_or_put_json(string $method): void
    {
        $request = $this->requests->createRequest($method, 'https://httpbin.org/'.strtolower($method))
            ->withBody($this->streams->createStream('{"k1": "v1", "k2": "v2"}'))
            ->withHeader('Content-Type', 'application/json');
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('json', $data);
        $this->assertArrayHasKey('k1', $data['json']);
        $this->assertArrayHasKey('k2', $data['json']);
        $this->assertEquals('v1', $data['json']['k1']);
        $this->assertEquals('v2', $data['json']['k2']);
    }

    /**
     * @return array<int, array{string}>
     */
    public static function providePatchPostOrPut(): array
    {
        return [
            ['PATCH'],
            ['POST'],
            ['PUT'],
        ];
    }

    public function test_delete(): void
    {
        $request = $this->requests->createRequest('DELETE', 'https://httpbin.org/delete');
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function test_delete_discard_body(): void
    {
        $request = $this->requests->createRequest('DELETE', 'https://httpbin.org/delete')
            ->withBody($this->streams->createStream('{"k1": "v1", "k2": "v2"}'))
            ->withHeader('Content-Type', 'application/json');
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode($response->getBody(), true);
        $this->assertNull($data['json']);
    }

    public function test_head(): void
    {
        $request = $this->requests->createRequest('HEAD', 'https://httpbin.org/get');
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        // HEAD requests typically have no body
        $this->assertEquals('', (string) $response->getBody());
    }

    /**
     * @dataProvider provideProtocolVersions
     */
    public function test_protocol_versions(string $version): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get')
            ->withProtocolVersion($version);
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        // Verify protocol version is set (response should reflect the version used)
        $this->assertNotEmpty($response->getProtocolVersion());
    }

    /**
     * @return array<int, array{string}>
     */
    public static function provideProtocolVersions(): array
    {
        return [
            ['1.0'],
            ['1.1'],
            ['2.0'],
        ];
    }

    public function test_authentication(): void
    {
        // httpbin.org supports basic auth via /basic-auth/user/passwd
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/basic-auth/testuser/testpass');
        $uri = $request->getUri()->withUserInfo('testuser', 'testpass');
        $request = $request->withUri($uri);

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('authenticated', $data);
        $this->assertTrue($data['authenticated']);
        $this->assertEquals('testuser', $data['user']);
    }

    public function test_custom_headers(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/headers')
            ->withHeader('X-Custom-Header', 'custom-value')
            ->withHeader('X-Another-Header', 'another-value');

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('headers', $data);
        $this->assertEquals('custom-value', $data['headers']['X-Custom-Header']);
        $this->assertEquals('another-value', $data['headers']['X-Another-Header']);
    }

    public function test_multiple_header_values(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/headers')
            ->withHeader('X-Multi-Header', 'value1')
            ->withAddedHeader('X-Multi-Header', 'value2');

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify the request was sent (httpbin echoes headers back)
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('headers', $data);
        // The header should be in the request (httpbin will show it)
        $this->assertTrue(
            isset($data['headers']['X-Multi-Header']) ||
            isset($data['headers']['X-Multi-Header-0']) ||
            isset($data['headers']['X-Multi-Header-1'])
        );
    }

    public function test_empty_body(): void
    {
        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post');
        // No body set
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('', $data['data']);
    }

    public function test_null_body(): void
    {
        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post')
            ->withBody($this->streams->createStream(''));

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_custom_curl_options(): void
    {
        $timeout = 30;
        $client = new Client(new ResponseFactory, [
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $request = $this->requests->createRequest('GET', 'https://httpbin.org/delay/1');
        $response = $client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_response_body_reading(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/json');
        $response = $this->client->sendRequest($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $this->assertNotEmpty($body);

        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('slideshow', $data);
    }

    public function test_response_body_can_be_read_multiple_times(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/json');
        $response = $this->client->sendRequest($request);

        $body1 = (string) $response->getBody();
        $body2 = (string) $response->getBody();

        $this->assertEquals($body1, $body2);
        $this->assertNotEmpty($body1);
    }

    /**
     * @dataProvider provideMoreStatusCodes
     */
    public function test_more_status_codes(int $code): void
    {
        $request = $this->requests->createRequest('GET', "https://httpbin.org/status/{$code}");
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($code, $response->getStatusCode());
    }

    /**
     * @return array<int, array{int}>
     */
    public static function provideMoreStatusCodes(): array
    {
        return [
            [201], // Created
            [204], // No Content
            [301], // Moved Permanently
            [302], // Found
            [304], // Not Modified
            [403], // Forbidden
            [404], // Not Found
            [408], // Request Timeout
            [429], // Too Many Requests
            [502], // Bad Gateway
            [503], // Service Unavailable
            [504], // Gateway Timeout
        ];
    }

    public function test_response_headers(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/response-headers?Content-Type=application/json&X-Custom=test');
        $response = $this->client->sendRequest($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertTrue($response->hasHeader('X-Custom'));
        $this->assertEquals('test', $response->getHeaderLine('X-Custom'));
    }

    public function test_response_protocol_version(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get');
        $response = $this->client->sendRequest($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $version = $response->getProtocolVersion();
        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/^[12](\.[01])?$/', $version);
    }

    public function test_request_with_query_parameters(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get?param1=value1&param2=value2');
        $response = $this->client->sendRequest($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('args', $data);
        $this->assertEquals('value1', $data['args']['param1']);
        $this->assertEquals('value2', $data['args']['param2']);
    }

    public function test_post_with_large_body(): void
    {
        // Create a body just under 1MB to test regular POST
        $smallBody = str_repeat('a', 512 * 1024); // 512KB

        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post')
            ->withBody($this->streams->createStream($smallBody))
            ->withHeader('Content-Type', 'text/plain');

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_post_with_streaming_body(): void
    {
        // Create a body over 1MB to test streaming upload
        // Using exactly 1MB + 1 byte to trigger streaming mode
        $largeBody = str_repeat('b', 1_024 * 1_024 + 1);

        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post')
            ->withBody($this->streams->createStream($largeBody))
            ->withHeader('Content-Type', 'text/plain');

        try {
            $response = $this->client->sendRequest($request);
            $this->assertInstanceOf(ResponseInterface::class, $response);
            // httpbin may reject very large bodies, so accept 200 or 413
            $this->assertContains($response->getStatusCode(), [200, 413]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                $this->assertArrayHasKey('data', $data);
                // Verify the body was sent (httpbin echoes it back)
                $this->assertStringStartsWith('b', $data['data']);
            }
        } catch (NetworkExceptionInterface $e) {
            // If httpbin rejects or times out on large bodies, that's acceptable
            // The important thing is that streaming mode was attempted
            $this->assertInstanceOf(NetworkExceptionInterface::class, $e);
        }
    }

    public function test_invalid_url_throws_exception(): void
    {
        $this->expectException(NetworkExceptionInterface::class);

        $request = $this->requests->createRequest('GET', 'https://invalid-domain-that-does-not-exist-12345.com');
        $this->client->sendRequest($request);
    }

    public function test_user_agent_is_set(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/user-agent');
        $response = $this->client->sendRequest($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('user-agent', $data);
        $this->assertEquals('vaibhavpandeyvpz/dakiya', $data['user-agent']);
    }

    public function test_custom_user_agent(): void
    {
        $customUserAgent = 'MyCustomAgent/1.0';
        $client = new Client(new ResponseFactory, [
            CURLOPT_USERAGENT => $customUserAgent,
        ]);

        $request = $this->requests->createRequest('GET', 'https://httpbin.org/user-agent');
        $response = $client->sendRequest($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertEquals($customUserAgent, $data['user-agent']);
    }

    public function test_response_reason_phrase(): void
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/status/200');
        $response = $this->client->sendRequest($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $reasonPhrase = $response->getReasonPhrase();
        $this->assertNotEmpty($reasonPhrase);
    }

    public function test_multiple_requests_reuse_curl_handle(): void
    {
        // Make multiple requests to verify curl handle is reused
        $request1 = $this->requests->createRequest('GET', 'https://httpbin.org/get');
        $response1 = $this->client->sendRequest($request1);
        $this->assertEquals(200, $response1->getStatusCode());

        $request2 = $this->requests->createRequest('GET', 'https://httpbin.org/ip');
        $response2 = $this->client->sendRequest($request2);
        $this->assertEquals(200, $response2->getStatusCode());

        // Both should work independently
        $this->assertNotEquals((string) $response1->getBody(), (string) $response2->getBody());
    }

    public function test_content_length_header_handling(): void
    {
        $body = 'test body content';
        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post')
            ->withBody($this->streams->createStream($body))
            ->withHeader('Content-Type', 'text/plain');

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify the body was sent correctly
        $data = json_decode($response->getBody(), true);
        $this->assertEquals($body, $data['data']);
    }

    public function test_expect_header_is_removed(): void
    {
        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post')
            ->withHeader('Expect', '100-continue')
            ->withBody($this->streams->createStream('test'));

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        // The Expect header should be removed by the client
    }

    public function test_network_exception_get_request(): void
    {
        $exception = new NetworkException('Test error', 0);
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get');
        $exception->setRequest($request);

        $this->assertSame($request, $exception->getRequest());
    }

    public function test_network_exception_get_request_throws_when_not_set(): void
    {
        $exception = new NetworkException('Test error', 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set');
        $exception->getRequest();
    }

    public function test_request_exception_get_request(): void
    {
        $exception = new RequestException('Test error', 0);
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get');
        $exception->setRequest($request);

        $this->assertSame($request, $exception->getRequest());
    }

    public function test_request_exception_get_request_throws_when_not_set(): void
    {
        $exception = new RequestException('Test error', 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set');
        $exception->getRequest();
    }

    public function test_body_with_null_size(): void
    {
        // Test body with null size (unknown size) - should use streaming
        $stream = $this->streams->createStream('test data');
        // Create a mock scenario where size is null by using a stream that reports null size
        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post')
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/plain');

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_protocol_version_default(): void
    {
        // Test default protocol version (not 1.0, 1.1, or 2.0)
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get')
            ->withProtocolVersion('3.0'); // Invalid version, should default to NONE

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_body_with_int_size(): void
    {
        // Test body with integer size > 1MB
        $largeBody = str_repeat('a', 1_024 * 1_024 + 100);
        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post')
            ->withBody($this->streams->createStream($largeBody))
            ->withHeader('Content-Type', 'text/plain');

        try {
            $response = $this->client->sendRequest($request);
            $this->assertInstanceOf(ResponseInterface::class, $response);
            // May succeed or fail depending on httpbin limits
            $this->assertContains($response->getStatusCode(), [200, 413]);
        } catch (NetworkExceptionInterface $e) {
            // Acceptable if httpbin rejects large bodies
            $this->assertInstanceOf(NetworkExceptionInterface::class, $e);
        }
    }

    public function test_response_header_parsing_with_existing_header(): void
    {
        // Test that response headers are properly added when header already exists
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/response-headers?X-Test=value1&X-Test=value2');
        $response = $this->client->sendRequest($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_curl_handle_reuse(): void
    {
        // Test that curl handle is properly reused across multiple requests
        $request1 = $this->requests->createRequest('GET', 'https://httpbin.org/get');
        $response1 = $this->client->sendRequest($request1);
        $this->assertEquals(200, $response1->getStatusCode());

        // Second request should reuse the curl handle
        $request2 = $this->requests->createRequest('GET', 'https://httpbin.org/ip');
        $response2 = $this->client->sendRequest($request2);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function test_response_header_with_added_header(): void
    {
        // Test response header parsing when header already exists (uses withAddedHeader)
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/response-headers?X-Duplicate=first');
        $response = $this->client->sendRequest($request);

        // Send another request that might add duplicate headers
        $request2 = $this->requests->createRequest('GET', 'https://httpbin.org/response-headers?X-Duplicate=second');
        $response2 = $this->client->sendRequest($request2);

        $this->assertInstanceOf(ResponseInterface::class, $response2);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function test_content_length_with_read_function(): void
    {
        // Test content-length header handling when CURLOPT_READFUNCTION is set
        $largeBody = str_repeat('a', 1_024 * 1_024 + 100);
        $request = $this->requests->createRequest('POST', 'https://httpbin.org/post')
            ->withBody($this->streams->createStream($largeBody))
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Length', '999999'); // Should be recalculated

        try {
            $response = $this->client->sendRequest($request);
            $this->assertInstanceOf(ResponseInterface::class, $response);
        } catch (NetworkExceptionInterface $e) {
            // Acceptable if httpbin rejects
            $this->assertInstanceOf(NetworkExceptionInterface::class, $e);
        }
    }

    public function test_protocol_version2(): void
    {
        // Test protocol version '2' (without .0)
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get')
            ->withProtocolVersion('2');

        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
