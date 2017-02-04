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

use Interop\Http\Factory\ResponseFactoryInterface;
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
    protected $options = array(
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_USERAGENT => 'Dakiya/PHP',
    );

    /**
     * Client constructor.
     * @param ResponseFactoryInterface $factory
     * @param array $options
     */
    public function __construct(ResponseFactoryInterface $factory, array $options = array())
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
     * @param ResponseInterface[] $bag
     * @return array
     */
    protected function prepare(RequestInterface $request, array &$bag)
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
        if (in_array($request->getMethod(), array('POST', 'PUT'), true)) {
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
        $headers = array('Expect:');
        foreach ($request->getHeaders() as $name => $values) {
            $header = strtolower($name);
            if ('expect' === $header) {
                continue;
            } elseif ('content-length' === $header) {
                if (isset($options[CURLOPT_POSTFIELDS])) {
                    $values = array(strlen($options[CURLOPT_POSTFIELDS]));
                } elseif (empty($options[CURLOPT_READFUNCTION])) {
                    $values = array(0);
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
        // Response
        $options[CURLOPT_WRITEFUNCTION] = function ($curl, $data) use (&$bag) {
            return $bag[0]->getBody()->write($data);
        };
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function send(RequestInterface $request)
    {
        if (is_resource($this->curl)) {
            curl_reset($this->curl);
        } else {
            $this->curl = curl_init();
        }
        $bag = array($this->factory->createResponse());
        $options = $this->prepare($request, $bag);
        // Headers
        $options[CURLOPT_HEADERFUNCTION] = function ($curl, $data) use (&$bag) {
            static $regex = '~^HTTP/(?<version>[012.]{1,3}+)\\s(?<status>[\\d]+)(?:\\s(?<reason>[a-zA-Z\\s]+))?$~';
            $header = trim($data);
            if (strlen($header) > 0) {
                if (preg_match($regex, $header, $matches)) {
                    $bag[0] = $bag[0]->withProtocolVersion($matches['version'])
                        ->withStatus($matches['status'], isset($matches['reason']) ? $matches['reason'] : '');
                } else {
                    list($name, $value) = explode(': ', $header, 2);
                    if ($bag[0]->hasHeader($name)) {
                        $bag[0] = $bag[0]->withAddedHeader($name, $value);
                    } else {
                        $bag[0] = $bag[0]->withHeader($name, $value);
                    }
                }
            }
            return strlen($data);
        };
        curl_setopt_array($this->curl, $options);
        curl_exec($this->curl);
        switch (curl_errno($this->curl)) {
            case CURLE_OK:
                break;
            case CURLE_COULDNT_RESOLVE_PROXY:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_COULDNT_CONNECT:
            case CURLE_OPERATION_TIMEOUTED:
            case CURLE_SSL_CONNECT_ERROR:
            default:
                throw new \RuntimeException(curl_error($this->curl));
        }
        return $bag[0];
    }
}
