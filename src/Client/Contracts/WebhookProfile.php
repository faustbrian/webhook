<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Contracts;

use Illuminate\Http\Request;

/**
 * Determines which webhooks should be processed.
 * @author Brian Faust <brian@cline.sh>
 */
interface WebhookProfile
{
    /**
     * Determine if webhook should be processed.
     */
    public function shouldProcess(Request $request): bool;
}
