<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Profiles;

use Cline\Webhook\Client\Contracts\WebhookProfile;
use Illuminate\Http\Request;

/**
 * Process all incoming webhooks without filtering.
 * @author Brian Faust <brian@cline.sh>
 */
final class ProcessEverything implements WebhookProfile
{
    /**
     * {@inheritDoc}
     */
    public function shouldProcess(Request $request): bool
    {
        return true;
    }
}
