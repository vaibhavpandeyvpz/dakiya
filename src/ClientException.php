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

use Psr\Http\Client\ClientExceptionInterface;

/**
 * Class ClientException
 * @package Dakiya
 */
class ClientException extends \Exception implements ClientExceptionInterface
{
}
