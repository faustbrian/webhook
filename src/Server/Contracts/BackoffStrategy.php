<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Contracts;

/**
 * Defines retry backoff calculation strategy.
 * @author Brian Faust <brian@cline.sh>
 */
interface BackoffStrategy
{
    /**
     * Calculate delay in seconds for a given attempt.
     *
     * @param int $attempt The attempt number (1-indexed)
     */
    public function calculate(int $attempt): int;
}
