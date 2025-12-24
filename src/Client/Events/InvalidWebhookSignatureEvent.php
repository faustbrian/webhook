<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a webhook signature verification fails.
 *
 * This event is dispatched when an incoming webhook request fails signature
 * validation, indicating either a malicious request, configuration mismatch,
 * or network tampering. Listeners can use this event for security monitoring,
 * logging, alerting, or rate limiting suspicious sources.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class InvalidWebhookSignatureEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new invalid webhook signature event.
     *
     * @param Request $request    The HTTP request that failed signature verification,
     *                            containing the invalid payload and headers for
     *                            security analysis and audit logging
     * @param string  $configName The webhook configuration name that was used for
     *                            validation, identifying which webhook endpoint
     *                            and secret key were involved in the failure
     */
    public function __construct(
        public Request $request,
        public string $configName,
    ) {}
}
