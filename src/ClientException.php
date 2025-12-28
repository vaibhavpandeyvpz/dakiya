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

use Psr\Http\Client\ClientExceptionInterface;

/**
 * Base exception for all HTTP client exceptions.
 *
 * This is the base exception class for all exceptions thrown by the HTTP client.
 * It implements PSR-18's ClientExceptionInterface.
 *
 * @see \Psr\Http\Client\ClientExceptionInterface
 */
class ClientException extends \Exception implements ClientExceptionInterface
{
    /**
     * Constructor.
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  \Throwable|null  $previous  The previous exception if any
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
