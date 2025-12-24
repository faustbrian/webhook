<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Strategies;

use Cline\Webhook\Server\Contracts\BackoffStrategy;

use function min;
use function random_int;

/**
 * Exponential backoff strategy with jitter for retry delays.
 *
 * Implements exponential backoff using the formula: base * (2 ^ (attempt - 1))
 * with optional random jitter to prevent thundering herd problems when multiple
 * webhooks retry simultaneously. The delay is capped at a configurable maximum
 * to prevent excessively long waits. Jitter adds up to 25% random variation.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ExponentialBackoffStrategy implements BackoffStrategy
{
    /**
     * Create a new exponential backoff strategy.
     *
     * @param int  $baseDelaySeconds Initial delay in seconds before first retry (default: 1).
     *                               Each subsequent retry doubles this base value until
     *                               maxDelaySeconds is reached. Must be positive.
     * @param int  $maxDelaySeconds  Maximum delay cap in seconds (default: 3600 - 1 hour).
     *                               Prevents exponential growth from creating unreasonably
     *                               long delays. Should be set based on webhook sensitivity.
     * @param bool $useJitter        Whether to add random jitter to calculated delays
     *                               (default: true). Jitter adds 0-25% random variation
     *                               to prevent thundering herd when multiple webhooks retry.
     */
    public function __construct(
        private int $baseDelaySeconds = 1,
        private int $maxDelaySeconds = 3_600,
        private bool $useJitter = true,
    ) {}

    /**
     * Calculate the retry delay for a given attempt number.
     *
     * Applies exponential backoff formula with optional jitter. For attempt N,
     * the base delay is: baseDelaySeconds * (2 ^ (N - 1)). The result is capped
     * at maxDelaySeconds, then jitter is added if enabled (0-25% of delay).
     *
     * @param  int $attempt The retry attempt number (1-based, where 1 is first retry)
     * @return int Delay in seconds to wait before the next retry attempt
     */
    public function calculate(int $attempt): int
    {
        // Calculate base delay: base * (2 ^ (attempt - 1))
        $delay = $this->baseDelaySeconds * (2 ** ($attempt - 1));

        // Cap at maximum delay
        $delay = min($delay, $this->maxDelaySeconds);

        // Add jitter (random variation up to 25% of delay)
        if ($this->useJitter) {
            $jitter = random_int(0, (int) ($delay * 0.25));
            $delay += $jitter;
        }

        return (int) $delay;
    }
}
