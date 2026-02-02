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
use Throwable;

use function sprintf;

/**
 * Thrown when webhook HTTP request fails or returns non-2xx response.
 *
 * This exception wraps various webhook delivery failures including network
 * errors, timeouts, and HTTP error responses. It preserves the underlying
 * cause through exception chaining for detailed error diagnosis.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookCallException extends RuntimeException implements WebhookException
{
    /**
     * Create exception for network or request dispatch failure.
     *
     * Used when the HTTP request cannot be completed due to network errors,
     * DNS failures, connection timeouts, or other transport-level issues.
     *
     * @param  string         $url      The target URL that could not be reached
     * @param  null|Throwable $previous The underlying exception that caused the failure (e.g., Guzzle RequestException)
     * @return self           Exception instance with chained cause for debugging
     */
    public static function dispatchFailed(string $url, ?Throwable $previous = null): self
    {
        return new self('Failed to dispatch webhook to: '.$url, 0, $previous);
    }

    /**
     * Create exception for HTTP error response (non-2xx status).
     *
     * Used when the server responds but returns an error status code
     * indicating rejection or processing failure.
     *
     * @param  string $url        The target URL that returned an error
     * @param  int    $statusCode HTTP status code received (e.g., 400, 500)
     * @param  string $response   Response body content for diagnostic purposes
     * @return self   Exception instance with detailed HTTP error information
     */
    public static function httpError(string $url, int $statusCode, string $response): self
    {
        return new self(sprintf('Webhook call to %s failed with HTTP %d: %s', $url, $statusCode, $response));
    }
}
