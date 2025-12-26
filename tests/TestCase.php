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
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        Model::clearBootedModels();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Tear down the test environment.
     */
    #[Override()]
    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    /**
     * Get package providers.
     *
     * @param  Application              $app
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
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app->make(Repository::class)->set('database.default', 'testing');
        $app->make(Repository::class)->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load webhook configuration
        $app->make(Repository::class)->set('webhook', require __DIR__.'/../config/webhook.php');

        // Override webhook configuration for testing (env values are null in tests)
        $app->make(Repository::class)->set('webhook.primary_key_type', 'ulid');
        $app->make(Repository::class)->set('webhook.client.configs.default.signing_secret', 'test-secret');
        $app->make(Repository::class)->set('webhook.client.configs.custom.signing_secret', 'custom-secret');

        // Use interfaces for testing to make mocking easier
        $app->make(Repository::class)->set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        $app->make(Repository::class)->set('webhook.client.configs.default.webhook_profile', WebhookProfile::class);
        $app->make(Repository::class)->set('webhook.client.configs.default.webhook_response', WebhookResponse::class);
        $app->make(Repository::class)->set('webhook.client.configs.custom.signature_validator', SignatureValidator::class);
        $app->make(Repository::class)->set('webhook.client.configs.custom.webhook_profile', WebhookProfile::class);
        $app->make(Repository::class)->set('webhook.client.configs.custom.webhook_response', WebhookResponse::class);
    }
}
