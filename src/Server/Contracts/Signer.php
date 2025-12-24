<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Contracts;

use Cline\Webhook\Enums\SignatureVersion;

/**
 * Defines webhook signature generation.
 * @author Brian Faust <brian@cline.sh>
 */
interface Signer
{
    /**
     * Generate signature for webhook payload.
     *
     * @param  string $webhookId The unique webhook ID
     * @param  int    $timestamp Unix timestamp
     * @param  string $payload   JSON payload
     * @return string Signature in format: "v1,<base64>" or "v1a,<base64>"
     */
    public function sign(string $webhookId, int $timestamp, string $payload): string;

    /**
     * Get the signature version.
     */
    public function version(): SignatureVersion;
}
