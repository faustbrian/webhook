<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Strategies;

use Cline\Webhook\Server\Contracts\BackoffStrategy;

/**
 * Exponential backoff strategy with jitter for retry delays.
 *
 * Uses the formula: base * (2 ^ attempt) with random jitter
 */
final class ExponentialBackoffStrategy implements BackoffStrategy
{
    /**
     * @param  int  $baseDelaySeconds  Base delay in seconds (default: 1)
     * @param  int  $maxDelaySeconds  Maximum delay in seconds (default: 3600 - 1 hour)
     * @param  bool  $useJitter  Add random jitter to prevent thundering herd
     */
    public function __construct(
        private readonly int $baseDelaySeconds = 1,
        private readonly int $maxDelaySeconds = 3600,
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
        $delay = \min($delay, $this->maxDelaySeconds);

        // Add jitter (random variation up to 25% of delay)
        if ($this->useJitter) {
            $jitter = \random_int(0, (int) ($delay * 0.25));
            $delay += $jitter;
        }

        return $delay;
    }
}
