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
 * Custom Eloquent query builder for WebhookCall model with status-based scopes.
 *
 * Provides convenient query scopes for filtering webhook calls by their processing
 * status and other common criteria. Simplifies querying for webhooks in specific
 * states or configurations.
 *
 * @author Brian Faust <brian@cline.sh>
 * @template TModelClass of \Illuminate\Database\Eloquent\Model
 * @extends Builder<TModelClass>
 */
final class WebhookCallBuilder extends Builder
{
    /**
     * Filter query to only include pending webhooks.
     */
    public function pending(): static
    {
        return $this->where('status', WebhookStatus::PENDING);
    }

    /**
     * Filter query to only include webhooks currently being processed.
     */
    public function processing(): static
    {
        return $this->where('status', WebhookStatus::PROCESSING);
    }

    /**
     * Filter query to only include successfully processed webhooks.
     */
    public function processed(): static
    {
        return $this->where('status', WebhookStatus::PROCESSED);
    }

    /**
     * Filter query to only include failed webhooks.
     */
    public function failed(): static
    {
        return $this->where('status', WebhookStatus::FAILED);
    }

    /**
     * Filter query to webhooks from a specific configuration.
     *
     * @param string $configName Configuration name from webhook.client.configs
     */
    public function forConfig(string $configName): static
    {
        return $this->where('config_name', $configName);
    }

    /**
     * Filter query to a specific webhook by its external ID.
     *
     * @param string $webhookId The webhook-id value from the webhook headers
     */
    public function byWebhookId(string $webhookId): static
    {
        return $this->where('webhook_id', $webhookId);
    }
}
