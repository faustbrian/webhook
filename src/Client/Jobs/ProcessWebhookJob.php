<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Jobs;

use Cline\Webhook\Client\Contracts\ProcessesWebhook;
use Cline\Webhook\Client\Events\WebhookProcessedEvent;
use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Throwable;

use function event;
use function resolve;
use function sprintf;

/**
 * Job to process webhook calls asynchronously.
 * @author Brian Faust <brian@cline.sh>
 */
final class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly WebhookCall $webhookCall,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->webhookCall->markAsProcessing();

        try {
            $processor = $this->getProcessor();
            $processor->process($this->webhookCall);

            $this->webhookCall->markAsProcessed();

            event(
                new WebhookProcessedEvent($this->webhookCall),
            );
        } catch (Throwable $throwable) {
            $this->webhookCall->markAsFailed($throwable);

            throw $throwable;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->webhookCall->markAsFailed($exception);
    }

    /**
     * Get webhook processor instance.
     */
    private function getProcessor(): ProcessesWebhook
    {
        // Check if a custom processor is configured for this config
        /** @var null|class-string<ProcessesWebhook> $processorClass */
        $processorClass = Config::get(
            sprintf('webhook.client.configs.%s.webhook_processor', $this->webhookCall->config_name),
        );

        if ($processorClass) {
            return resolve($processorClass);
        }

        // No processor configured - this is valid if you just want to store webhooks
        return new class() implements ProcessesWebhook
        {
            public function process(WebhookCall $webhookCall): void
            {
                // No-op processor - webhook is just stored
            }
        };
    }
}
