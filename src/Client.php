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

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP Client implementation using cURL.
 *
 * This class implements PSR-18 HTTP Client Interface and provides a lightweight
 * HTTP client for sending PSR-7 HTTP requests. It uses cURL as the underlying
 * transport mechanism and supports all standard HTTP methods, protocol versions,
 * and features like streaming uploads for large request bodies.
 *
 * @see \Psr\Http\Client\ClientInterface
 */
final class Client implements ClientInterface
{
    /**
     * The cURL handle resource.
     */
    private ?\CurlHandle $curl = null;

    /**
     * The response factory used to create response instances.
     */
    private readonly ResponseFactoryInterface $factory;

    /**
     * Default cURL options merged with user-provided options.
     *
     * @var array<int, mixed>
     */
    private readonly array $options;

    /**
     * Client constructor.
     *
     * @param  ResponseFactoryInterface  $factory  The factory used to create response instances
     * @param  array<int, mixed>  $options  Optional cURL options to override defaults
     */
    public function __construct(
        ResponseFactoryInterface $factory,
        array $options = []
    ) {
        $this->factory = $factory;
        $this->options = array_replace([
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'vaibhavpandeyvpz/dakiya',
        ], $options);
    }

    /**
     * Destructor.
     *
     * Closes the cURL handle if it was initialized.
     */
    public function __destruct()
    {
        if ($this->curl instanceof \CurlHandle) {
            curl_close($this->curl);
        }
    }

    /**
     * Prepares cURL options from a PSR-7 request.
     *
     * Converts a PSR-7 request into an array of cURL options, handling
     * protocol version, URI, request method, headers, body, and authentication.
     * For large request bodies (>1MB), streaming upload is used.
     *
     * @param  RequestInterface  $request  The PSR-7 request to prepare
     * @return array<int, mixed> Array of cURL option constants and their values
     */
    private function prepare(RequestInterface $request): array
    {
        $options = $this->options;
        // Protocol Version
        $options[CURLOPT_HTTP_VERSION] = match ($request->getProtocolVersion()) {
            '1.0' => CURL_HTTP_VERSION_1_0,
            '1.1' => CURL_HTTP_VERSION_1_1,
            '2', '2.0' => CURL_HTTP_VERSION_2,
            default => CURL_HTTP_VERSION_NONE,
        };
        // URI
        $options[CURLOPT_URL] = (string) $request->getUri();
        // Body
        $method = $request->getMethod();
        if (in_array($method, ['PATCH', 'POST', 'PUT'], true)) {
            $body = $request->getBody();
            if ($body !== null) {
                $size = $body->getSize();
                if ($size === null || $size > 1_024 * 1_024) {
                    $options[CURLOPT_UPLOAD] = true;
                    if (is_int($size)) {
                        $options[CURLOPT_INFILESIZE] = $size;
                    }
                    $options[CURLOPT_READFUNCTION] = function ($curl, $file, $length) use ($body): string {
                        return $body->read($length);
                    };
                } else {
                    $options[CURLOPT_POSTFIELDS] = (string) $body;
                }
            }
        }
        // Headers
        $headers = ['Expect:'];
        foreach ($request->getHeaders() as $name => $values) {
            $header = strtolower($name);
            if ($header === 'expect') {
                continue;
            }
            if ($header === 'content-length') {
                if (isset($options[CURLOPT_POSTFIELDS])) {
                    $values = [(string) strlen($options[CURLOPT_POSTFIELDS])];
                } elseif (! isset($options[CURLOPT_READFUNCTION])) {
                    $values = ['0'];
                }
            }
            foreach ($values as $value) {
                $headers[] = "{$name}: {$value}";
            }
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        // Method
        if ($method === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        } elseif ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }
        // Username or Password
        $userInfo = $request->getUri()->getUserInfo();
        if ($userInfo !== '') {
            $options[CURLOPT_USERPWD] = $userInfo;
        }

        return $options;
    }

    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * {@inheritdoc}
     *
     * @param  RequestInterface  $request  The PSR-7 request to send
     * @return ResponseInterface The PSR-7 response
     *
     * @throws NetworkException If a network error occurs (connection failure, timeout, etc.)
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->curl === null) {
            $curl = curl_init();
            if (! ($curl instanceof \CurlHandle)) {
                throw new NetworkException('Failed to initialize cURL', 0);
            }
            $this->curl = $curl;
        } else {
            curl_reset($this->curl);
        }
        $response = $this->factory->createResponse();
        $options = $this->prepare($request);

        // Headers
        $options[CURLOPT_HEADERFUNCTION] = function ($curl, $data) use (&$response): int {
            static $regex = '~^HTTP/(?<version>[012.]{1,3}+)\\s(?<status>[\\d]+)(?:\\s(?<reason>[a-zA-Z\\s]+))?$~';
            $header = trim($data);
            if ($header === '') {
                return strlen($data);
            }

            if (preg_match($regex, $header, $matches)) {
                $response = $response->withProtocolVersion($matches['version'])
                    ->withStatus((int) $matches['status'], $matches['reason'] ?? '');
            } else {
                [$name, $value] = explode(': ', $header, 2);
                $response = $response->hasHeader($name)
                    ? $response->withAddedHeader($name, $value)
                    : $response->withHeader($name, $value);
            }

            return strlen($data);
        };

        // Body
        $options[CURLOPT_WRITEFUNCTION] = function ($curl, $data) use (&$response): int {
            return $response->getBody()->write($data);
        };

        curl_setopt_array($this->curl, $options);
        curl_exec($this->curl);

        $errno = curl_errno($this->curl);
        if ($errno !== CURLE_OK) {
            $exception = new NetworkException(curl_error($this->curl), $errno);
            $exception->setRequest($request);
            throw $exception;
        }

        return $response;
    }
}
