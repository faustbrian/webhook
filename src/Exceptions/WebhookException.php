<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Exceptions;

use Throwable;

/**
 * Marker interface for all webhook package exceptions.
 *
 * Provides a unified exception hierarchy allowing consumers to catch any
 * exception thrown by the Webhook package with a single catch block. All
 * webhook-specific exceptions implement this interface, enabling targeted
 * error handling separate from other application exceptions.
 *
 * ```php
 * try {
 *     WebhookClient::process($request);
 * } catch (WebhookException $e) {
 *     // Handle any webhook-related error
 *     Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface WebhookException extends Throwable {}
