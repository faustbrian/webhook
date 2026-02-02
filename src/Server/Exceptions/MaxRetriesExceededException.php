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

use function sprintf;

/**
 * Thrown when maximum retry attempts are exceeded without success.
 *
 * This exception indicates that all configured retry attempts have failed
 * to deliver the webhook successfully. It is only thrown when the
 * throwExceptionOnFailure option is enabled; otherwise, failures are
 * silently logged through events.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MaxRetriesExceededException extends RuntimeException implements WebhookException
{
    /**
     * Create exception for exhausted retry attempts.
     *
     * @param  int    $maxRetries The maximum number of retry attempts that were configured
     * @param  string $url        The target URL that could not be reached after all attempts
     * @return self   Exception instance with detailed failure information
     */
    public static function make(int $maxRetries, string $url): self
    {
        return new self(sprintf('Maximum retry attempts (%d) exceeded for webhook: %s', $maxRetries, $url));
    }
}
