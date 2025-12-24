<?php

declare(strict_types=1);

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

/**
 * Job to process webhook calls asynchronously.
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

            WebhookProcessedEvent::dispatch($this->webhookCall);
        } catch (\Throwable $exception) {
            $this->webhookCall->markAsFailed($exception);

            throw $exception;
        }
    }

    /**
     * Get webhook processor instance.
     */
    private function getProcessor(): ProcessesWebhook
    {
        // Check if a custom processor is configured for this config
        $processorClass = Config::get(
            "webhook.client.configs.{$this->webhookCall->config_name}.webhook_processor"
        );

        if ($processorClass) {
            return app($processorClass);
        }

        // No processor configured - this is valid if you just want to store webhooks
        return new class implements ProcessesWebhook {
            public function process(WebhookCall $webhookCall): void
            {
                // No-op processor - webhook is just stored
            }
        };
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->webhookCall->markAsFailed($exception);
    }
}
