<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;
use RuntimeException;

/**
 * Thrown when an invalid or malformed URL is provided for webhook dispatch.
 *
 * This exception prevents webhook delivery attempts to URLs that fail
 * validation, such as malformed URLs, missing schemes, or disallowed
 * protocols. Catching this early prevents wasted HTTP requests and
 * provides clear feedback about configuration errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidUrlException extends RuntimeException implements WebhookException
{
    /**
     * Create exception for an invalid webhook URL.
     *
     * @param  string $url The malformed or invalid URL that was rejected
     * @return self   Exception instance with descriptive error message
     */
    public static function make(string $url): self
    {
        return new self('Invalid webhook URL: '.$url);
    }
}
