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
 * Uses the formula: base * (2 ^ attempt) with random jitter
 * @author Brian Faust <brian@cline.sh>
 */
final class ExponentialBackoffStrategy implements BackoffStrategy
{
    /**
     * @param int  $baseDelaySeconds Base delay in seconds (default: 1)
     * @param int  $maxDelaySeconds  Maximum delay in seconds (default: 3600 - 1 hour)
     * @param bool $useJitter        Add random jitter to prevent thundering herd
     */
    public function __construct(
        private readonly int $baseDelaySeconds = 1,
        private readonly int $maxDelaySeconds = 3_600,
        private readonly bool $useJitter = true,
    ) {}

    /**
     * {@inheritDoc}
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

        return $delay;
    }
}
