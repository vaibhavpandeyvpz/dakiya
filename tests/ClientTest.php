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
use Sandesh\RequestFactory;
use Sandesh\ResponseFactory;

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

    protected function setUp()
    {
        $this->client = new Client(new ResponseFactory());
        $this->request = new RequestFactory();
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
}
