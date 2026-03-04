<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Contracts;

/**
 * Defines retry backoff calculation strategy for webhook delivery.
 *
 * Implementations determine the delay between retry attempts when webhook
 * calls fail. Common strategies include exponential backoff, linear backoff,
 * or fixed delays to prevent overwhelming the receiving endpoint.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface BackoffStrategy
{
    /**
     * Calculate delay in seconds before the next retry attempt.
     *
     * @param  int $attempt The current attempt number (1-indexed, where 1 is the first retry after initial failure)
     * @return int Delay in seconds to wait before the next retry attempt
     */
    public function calculate(int $attempt): int;
}
