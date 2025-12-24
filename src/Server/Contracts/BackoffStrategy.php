<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Contracts;

/**
 * Defines retry backoff calculation strategy.
 */
interface BackoffStrategy
{
    /**
     * Calculate delay in seconds for a given attempt.
     *
     * @param  int  $attempt  The attempt number (1-indexed)
     */
    public function calculate(int $attempt): int;
}
