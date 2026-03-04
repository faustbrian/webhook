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
 * Job to process webhook calls asynchronously via Laravel queue system.
 *
 * Handles the processing of received webhook calls by delegating to the
 * configured webhook processor. Manages webhook status transitions through
 * pending -> processing -> processed/failed lifecycle. Dispatches events
 * on successful processing and handles failures gracefully with automatic
 * retry support through Laravel's queue system.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new webhook processing job.
     *
     * @param WebhookCall $webhookCall The webhook call record to process. Must be
     *                                 in PENDING status when job is dispatched.
     *                                 Status will be updated to PROCESSING during
     *                                 execution and PROCESSED or FAILED upon completion.
     */
    public function __construct(
        public readonly WebhookCall $webhookCall,
    ) {}

    /**
     * Execute the webhook processing job.
     *
     * Marks the webhook as processing, delegates to the configured processor,
     * updates status to processed on success, and dispatches WebhookProcessedEvent.
     * If processing fails, marks webhook as failed and re-throws the exception
     * to trigger Laravel's queue retry mechanism.
     *
     * @throws Throwable When webhook processing fails, allowing Laravel queue
     *                   to handle retries according to job configuration
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
     * Handle job failure after all retry attempts exhausted.
     *
     * Called by Laravel's queue system when the job has failed and all retry
     * attempts have been exhausted. Ensures the webhook call is marked as
     * failed with the exception details persisted for debugging.
     *
     * @param Throwable $exception The exception that caused the final job failure
     */
    public function failed(Throwable $exception): void
    {
        $this->webhookCall->markAsFailed($exception);
    }

    /**
     * Get webhook processor instance for the webhook's configuration.
     *
     * Resolves the configured webhook processor from the service container, or
     * returns a no-op processor if none is configured. The no-op processor allows
     * webhooks to be received, validated, and stored without custom processing
     * logic, which is useful for logging or audit requirements.
     *
     * @return ProcessesWebhook Resolved processor instance or anonymous no-op processor
     */
    private function getProcessor(): ProcessesWebhook
    {
        /** @var null|class-string<ProcessesWebhook> $processorClass */
        $processorClass = Config::get(
            sprintf('webhook.client.configs.%s.webhook_processor', $this->webhookCall->config_name),
        );

        if ($processorClass) {
            return resolve($processorClass);
        }

        // Return no-op processor when none configured - valid for storage-only use cases
        return new class() implements ProcessesWebhook
        {
            public function process(WebhookCall $webhookCall): void
            {
                // No-op processor - webhook is just stored
            }
        };
    }
}
