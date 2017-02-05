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

use Interop\Http\Factory\RequestFactoryInterface;
use Interop\Http\Factory\StreamFactoryInterface;
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
    protected $request;

    /**
     * @var StreamFactoryInterface
     */
    protected $stream;

    protected function setUp()
    {
        $this->client = new Client(new ResponseFactory());
        $this->request = new RequestFactory();
        $this->stream = new StreamFactory();
    }

    /**
     * @param string $name
     * @param string $value
     * @dataProvider provideCookies
     */
    public function testCookies($name, $value)
    {
        $client = new Client(new ResponseFactory(), array(
            CURLOPT_COOKIEJAR => tempnam(sys_get_temp_dir(), 'cookie'),
        ));
        $request = $this->request->createRequest('GET', "https://httpbin.org/cookies/set?{$name}={$value}");
        $response = $client->send($request);
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
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
        return array(
            array('k1', 'v1'),
            array('k2', 'v2'),
        );
    }

    /**
     * @param string $path
     * @param string $type
     * @dataProvider provideContentTypes
     */
    public function testContentTypes($path, $type)
    {
        $request = $this->request->createRequest('GET', "https://httpbin.org/{$path}");
        $response = $this->client->send($request);
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertEquals($type, $response->getHeaderLine('Content-Type'));
    }

    /**
     * @return array
     */
    public function provideContentTypes()
    {
        return array(
            array('html', 'text/html; charset=utf-8'),
            array('ip', 'application/json'),
            array('robots.txt', 'text/plain'),
            array('xml', 'application/xml'),
        );
    }

    /**
     * @param int $code
     * @dataProvider provideStatusCodes
     */
    public function testStatusCodes($code)
    {
        $request = $this->request->createRequest('GET', "https://httpbin.org/status/{$code}");
        $response = $this->client->send($request);
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertEquals($code, $response->getStatusCode());
    }

    /**
     * @return array
     */
    public function provideStatusCodes()
    {
        return array(
            array(200),
            array(400),
            array(401),
            array(405),
            array(500),
        );
    }

    public function testGet()
    {
        $request = $this->request->createRequest('GET', 'https://httpbin.org/get');
        $response = $this->client->send($request);
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @param string $method
     * @dataProvider providePatchPostOrPut
     */
    public function testPatchPostOrPut($method)
    {
        $request = $this->request->createRequest($method, 'https://httpbin.org/' . strtolower($method))
            ->withBody($this->stream->createStream('k1=v1&k2=v2'))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $response = $this->client->send($request);
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
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
        $request = $this->request->createRequest($method, 'https://httpbin.org/' . strtolower($method))
            ->withBody($this->stream->createStream('{"k1": "v1", "k2": "v2"}'))
            ->withHeader('Content-Type', 'application/json');
        $response = $this->client->send($request);
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
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
        return array(
            array('PATCH'),
            array('POST'),
            array('PUT'),
        );
    }

    public function testDelete()
    {
        $request = $this->request->createRequest('DELETE', 'https://httpbin.org/delete');
        $response = $this->client->send($request);
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testDeleteDiscardBody()
    {
        $request = $this->request->createRequest('DELETE', 'https://httpbin.org/delete')
            ->withBody($this->stream->createStream('{"k1": "v1", "k2": "v2"}'))
            ->withHeader('Content-Type', 'application/json');
        $response = $this->client->send($request);
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode($response->getBody(), true);
        $this->assertNull($data['json']);
    }
}
