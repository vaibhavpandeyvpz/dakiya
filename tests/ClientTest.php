<?php

/*
 * This file is part of vaibhavpandeyvpz/dakiya package.
 *
 * (c) Vaibhav Pandey <contact@vaibhavpandey.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.md.
 */

namespace Dakiya;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sandesh\RequestFactory;
use Sandesh\ResponseFactory;
use Sandesh\StreamFactory;

/**
 * Class ClientTest
 * @package Dakiya
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var RequestFactoryInterface
     */
    protected $requests;

    /**
     * @var StreamFactoryInterface
     */
    protected $streams;

    protected function setUp()
    {
        $this->client = new Client(new ResponseFactory());
        $this->requests = new RequestFactory();
        $this->streams = new StreamFactory();
    }

    /**
     * @param string $name
     * @param string $value
     * @dataProvider provideCookies
     */
    public function testCookies($name, $value)
    {
        $client = new Client(new ResponseFactory(), [
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
     * @return array
     */
    public function provideCookies()
    {
        return [
            ['k1', 'v1'],
            ['k2', 'v2'],
        ];
    }

    /**
     * @param string $path
     * @param string $type
     * @dataProvider provideContentTypes
     */
    public function testContentTypes($path, $type)
    {
        $request = $this->requests->createRequest('GET', "https://httpbin.org/{$path}");
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($type, $response->getHeaderLine('Content-Type'));
    }

    /**
     * @return array
     */
    public function provideContentTypes()
    {
        return [
            ['html', 'text/html; charset=utf-8'],
            ['ip', 'application/json'],
            ['robots.txt', 'text/plain'],
            ['xml', 'application/xml'],
        ];
    }

    /**
     * @param int $code
     * @dataProvider provideStatusCodes
     */
    public function testStatusCodes($code)
    {
        $request = $this->requests->createRequest('GET', "https://httpbin.org/status/{$code}");
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($code, $response->getStatusCode());
    }

    /**
     * @return array
     */
    public function provideStatusCodes()
    {
        return [
            [200],
            [400],
            [401],
            [405],
            [500],
        ];
    }

    public function testGet()
    {
        $request = $this->requests->createRequest('GET', 'https://httpbin.org/get');
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @param string $method
     * @dataProvider providePatchPostOrPut
     */
    public function testPatchPostOrPut($method)
    {
        $request = $this->requests->createRequest($method, 'https://httpbin.org/' . strtolower($method))
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
     * @param string $method
     * @dataProvider providePatchPostOrPut
     */
    public function testPatchPostOrPutJson($method)
    {
        $request = $this->requests->createRequest($method, 'https://httpbin.org/' . strtolower($method))
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
     * @return array
     */
    public function providePatchPostOrPut()
    {
        return [
            ['PATCH'],
            ['POST'],
            ['PUT'],
        ];
    }

    public function testDelete()
    {
        $request = $this->requests->createRequest('DELETE', 'https://httpbin.org/delete');
        $response = $this->client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testDeleteDiscardBody()
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
}
