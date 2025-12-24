<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Database\Factories;

use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookCall>
 */
final class WebhookCallFactory extends Factory
{
    protected $model = WebhookCall::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'config_name' => 'default',
            'webhook_id' => Str::ulid()->toString(),
            'timestamp' => time(),
            'payload' => [
                'event' => 'test.event',
                'data' => [
                    'id' => $this->faker->uuid(),
                    'name' => $this->faker->name(),
                ],
            ],
            'headers' => [
                'content-type' => 'application/json',
                'webhook-signature' => 'test-signature',
            ],
            'status' => WebhookStatus::PENDING,
            'exception' => null,
            'attempts' => 0,
            'processed_at' => null,
        ];
    }

    /**
     * Indicate that the webhook is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookStatus::PROCESSING,
            'attempts' => 1,
        ]);
    }

    /**
     * Indicate that the webhook has been processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookStatus::PROCESSED,
            'processed_at' => now(),
            'exception' => null,
        ]);
    }

    /**
     * Indicate that the webhook has failed.
     */
    public function failed(?string $exceptionMessage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookStatus::FAILED,
            'exception' => $exceptionMessage ?? 'Test exception',
        ]);
    }

    /**
     * Set the number of attempts.
     */
    public function withAttempts(int $attempts): static
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => $attempts,
        ]);
    }

    /**
     * Set the config name.
     */
    public function forConfig(string $configName): static
    {
        return $this->state(fn (array $attributes) => [
            'config_name' => $configName,
        ]);
    }
}
