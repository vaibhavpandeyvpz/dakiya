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

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Exception thrown when a network error occurs.
 *
 * This exception is thrown when a network-level error occurs, such as
 * connection failures, timeouts, DNS resolution errors, or SSL errors.
 * It implements PSR-18's NetworkExceptionInterface.
 *
 * @see \Psr\Http\Client\NetworkExceptionInterface
 */
final class NetworkException extends ClientException implements NetworkExceptionInterface
{
    /**
     * The request that caused the network error.
     */
    private ?RequestInterface $request = null;

    /**
     * Constructor.
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code (typically a cURL error code)
     * @param  \Throwable|null  $previous  The previous exception if any
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Gets the request that caused the network error.
     *
     * {@inheritdoc}
     *
     * @return RequestInterface The request that caused the error
     *
     * @throws \RuntimeException If the request was not set before calling this method
     */
    public function getRequest(): RequestInterface
    {
        return $this->request ?? throw new \RuntimeException('Request not set');
    }

    /**
     * Sets the request that caused the network error.
     *
     * @param  RequestInterface  $request  The request that caused the error
     */
    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }
}
