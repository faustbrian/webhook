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
 * @template TModelClass of \Illuminate\Database\Eloquent\Model
 * @extends Builder<TModelClass>
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookCallBuilder extends Builder
{
    /**
     * Filter to pending webhooks.
     *
     * @return static
     */
    public function pending(): static
    {
        return $this->where('status', WebhookStatus::PENDING);
    }

    /**
     * Filter to processing webhooks.
     *
     * @return static
     */
    public function processing(): static
    {
        return $this->where('status', WebhookStatus::PROCESSING);
    }

    /**
     * Filter to processed webhooks.
     *
     * @return static
     */
    public function processed(): static
    {
        return $this->where('status', WebhookStatus::PROCESSED);
    }

    /**
     * Filter to failed webhooks.
     *
     * @return static
     */
    public function failed(): static
    {
        return $this->where('status', WebhookStatus::FAILED);
    }

    /**
     * Filter to specific config.
     *
     * @return static
     */
    public function forConfig(string $configName): static
    {
        return $this->where('config_name', $configName);
    }

    /**
     * Filter to specific webhook ID.
     *
     * @return static
     */
    public function byWebhookId(string $webhookId): static
    {
        return $this->where('webhook_id', $webhookId);
    }
}
