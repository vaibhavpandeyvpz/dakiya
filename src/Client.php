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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Client
 * @package Dakiya
 */
class Client implements ClientInterface
{
    /**
     * @var resource
     */
    protected $curl;

    /**
     * @var ResponseFactoryInterface
     */
    protected $factory;

    /**
     * @var array
     */
    protected $options = [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_USERAGENT => 'vaibhavpandeyvpz/dakiya',
    ];

    /**
     * Client constructor.
     * @param ResponseFactoryInterface $factory
     * @param array $options
     */
    public function __construct(ResponseFactoryInterface $factory, array $options = [])
    {
        $this->factory = $factory;
        $this->options = array_merge($this->options, $options);
    }

    public function __destruct()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }

    /**
     * @param RequestInterface $request
     * @return array
     */
    protected function prepare(RequestInterface $request)
    {
        $options = $this->options;
        // Protocol Version
        switch ($request->getProtocolVersion()) {
            case '1.0':
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_0;
                break;
            case '1.1':
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
                break;
            case '2':
            case '2.0':
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2;
                break;
            default:
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_NONE;
                break;
        }
        // URI
        $options[CURLOPT_URL] = (string)$request->getUri();
        // Body
        if (in_array($request->getMethod(), ['PATCH', 'POST', 'PUT'])) {
            if (null !== ($body = $request->getBody())) {
                $size = $body->getSize();
                if (is_null($size) || ($size > 1024 * 1024)) {
                    $options[CURLOPT_UPLOAD] = true;
                    if (is_int($size)) {
                        $options[CURLOPT_INFILESIZE] = $size;
                    }
                    $options[CURLOPT_READFUNCTION] = function ($curl, $file, $length) use ($body) {
                        return $body->read($length);
                    };
                } else {
                    $options[CURLOPT_POSTFIELDS] = (string)$body;
                }
            }
        }
        // Headers
        $headers = ['Expect:'];
        foreach ($request->getHeaders() as $name => $values) {
            $header = strtolower($name);
            if ('expect' === $header) {
                continue;
            } elseif ('content-length' === $header) {
                if (isset($options[CURLOPT_POSTFIELDS])) {
                    $values = [strlen($options[CURLOPT_POSTFIELDS])];
                } elseif (empty($options[CURLOPT_READFUNCTION])) {
                    $values = [0];
                }
            }
            foreach ($values as $value) {
                $headers[] = "{$name}: {$value}";
            }
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        // Method
        if ($request->getMethod() === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        } elseif ($request->getMethod() !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        }
        // Username or Password
        if ($request->getUri()->getUserInfo()) {
            $options[CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
        }
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (is_resource($this->curl)) {
            curl_reset($this->curl);
        } else {
            $this->curl = curl_init();
        }
        $bag = [$this->factory->createResponse()];
        $options = $this->prepare($request);
        // Headers
        $options[CURLOPT_HEADERFUNCTION] = function ($curl, $data) use (&$bag) {
            static $regex = '~^HTTP/(?<version>[012.]{1,3}+)\\s(?<status>[\\d]+)(?:\\s(?<reason>[a-zA-Z\\s]+))?$~';
            $header = trim($data);
            if (strlen($header) > 0) {
                if (preg_match($regex, $header, $matches)) {
                    $bag[0] = $bag[0]->withProtocolVersion($matches['version'])
                        ->withStatus($matches['status'], isset($matches['reason']) ? $matches['reason'] : '');
                } else {
                    list ($name, $value) = explode(': ', $header, 2);
                    if ($bag[0]->hasHeader($name)) {
                        $bag[0] = $bag[0]->withAddedHeader($name, $value);
                    } else {
                        $bag[0] = $bag[0]->withHeader($name, $value);
                    }
                }
            }
            return strlen($data);
        };
        // Body
        $options[CURLOPT_WRITEFUNCTION] = function ($curl, $data) use (&$bag) {
            return $bag[0]->getBody()->write($data);
        };
        curl_setopt_array($this->curl, $options);
        curl_exec($this->curl);
        $errno = curl_errno($this->curl);
        switch ($errno) {
            case CURLE_OK:
                break;
            case CURLE_COULDNT_RESOLVE_PROXY:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_COULDNT_CONNECT:
            case CURLE_OPERATION_TIMEOUTED:
            case CURLE_SSL_CONNECT_ERROR:
            default:
                $e = new NetworkException(curl_error($this->curl), $errno);
                $e->setRequest($request);
                throw $e;
        }
        return $bag[0];
    }
}
