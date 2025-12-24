<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Contracts\WebhookProfile;
use Cline\Webhook\Client\Contracts\WebhookResponse;
use Cline\Webhook\WebhookServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            WebhookServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load webhook configuration
        $app['config']->set('webhook', require __DIR__.'/../config/webhook.php');

        // Override webhook configuration for testing (env values are null in tests)
        $app['config']->set('webhook.primary_key_type', 'id');
        $app['config']->set('webhook.client.configs.default.signing_secret', 'test-secret');
        $app['config']->set('webhook.client.configs.custom.signing_secret', 'custom-secret');

        // Use interfaces for testing to make mocking easier
        $app['config']->set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        $app['config']->set('webhook.client.configs.default.webhook_profile', WebhookProfile::class);
        $app['config']->set('webhook.client.configs.default.webhook_response', WebhookResponse::class);
        $app['config']->set('webhook.client.configs.custom.signature_validator', SignatureValidator::class);
        $app['config']->set('webhook.client.configs.custom.webhook_profile', WebhookProfile::class);
        $app['config']->set('webhook.client.configs.custom.webhook_response', WebhookResponse::class);
    }
}
