<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Models\Builders;

use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * @author Brian Faust <brian@cline.sh>
 * @template TModelClass of \Illuminate\Database\Eloquent\Model
 * @extends Builder<TModelClass>
 */
final class WebhookCallBuilder extends Builder
{
    /**
     * Filter to pending webhooks.
     */
    public function pending(): static
    {
        return $this->where('status', WebhookStatus::PENDING);
    }

    /**
     * Filter to processing webhooks.
     */
    public function processing(): static
    {
        return $this->where('status', WebhookStatus::PROCESSING);
    }

    /**
     * Filter to processed webhooks.
     */
    public function processed(): static
    {
        return $this->where('status', WebhookStatus::PROCESSED);
    }

    /**
     * Filter to failed webhooks.
     */
    public function failed(): static
    {
        return $this->where('status', WebhookStatus::FAILED);
    }

    /**
     * Filter to specific config.
     */
    public function forConfig(string $configName): static
    {
        return $this->where('config_name', $configName);
    }

    /**
     * Filter to specific webhook ID.
     */
    public function byWebhookId(string $webhookId): static
    {
        return $this->where('webhook_id', $webhookId);
    }
}
