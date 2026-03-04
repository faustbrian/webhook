<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Exceptions\Client;

use Cline\Webhook\Exceptions\WebhookException;
use InvalidArgumentException;

/**
 * Base exception for webhook timestamp validation failures.
 *
 * Parent class for all timestamp-related validation errors that occur during
 * webhook signature verification. Timestamp validation is critical for preventing
 * replay attacks by ensuring webhooks are processed within acceptable time bounds.
 * Specific validation failures extend this class with detailed error context.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see ExpiredTimestampException For timestamps that are too old
 * @see FutureTimestampException For timestamps ahead of server time
 */
abstract class InvalidTimestampException extends InvalidArgumentException implements WebhookException {}
