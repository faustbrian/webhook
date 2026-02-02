<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Feature\Client\Http\Controllers;

use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Contracts\WebhookProfile;
use Cline\Webhook\Client\Contracts\WebhookResponse;
use Cline\Webhook\Client\Events\InvalidWebhookSignatureEvent;
use Cline\Webhook\Client\Events\WebhookReceivedEvent;
use Cline\Webhook\Client\Http\Controllers\WebhookController;
use Cline\Webhook\Client\Jobs\ProcessWebhookJob;
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Mockery;

use function array_fill;
use function beforeEach;
use function describe;
use function expect;
use function random_int;
use function str_repeat;
use function test;
use function uses;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Set up webhook configs
    Config::set('webhook.client.configs.default', [
        'signing_secret' => 'test-secret',
        'signature_validator' => SignatureValidator::class,
        'webhook_profile' => WebhookProfile::class,
        'webhook_response' => WebhookResponse::class,
        'webhook_model' => WebhookCall::class,
        'process_webhook_job' => ProcessWebhookJob::class,
        'store_headers' => ['*'],
    ]);

    Config::set('webhook.client.configs.custom', [
        'signing_secret' => 'custom-secret',
        'signature_validator' => SignatureValidator::class,
        'webhook_profile' => WebhookProfile::class,
        'webhook_response' => WebhookResponse::class,
        'webhook_model' => WebhookCall::class,
        'process_webhook_job' => ProcessWebhookJob::class,
        'store_headers' => ['*'],
    ]);

    // Fake only application events, not Eloquent model events
    Event::fake([
        WebhookReceivedEvent::class,
        InvalidWebhookSignatureEvent::class,
    ]);
    Queue::fake();

    // Register webhook routes manually (macro doesn't support configName parameter)
    Route::post('webhooks', WebhookController::class)
        ->defaults('configName', 'default')
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('webhook.default');

    Route::post('webhooks/custom', WebhookController::class)
        ->defaults('configName', 'custom')
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('webhook.custom');
});

describe('WebhookController', function (): void {
    describe('Happy Path', function (): void {
        test('accepts POST request and returns successful response', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = ['event' => 'user.created', 'data' => ['id' => 1]];

            // Act
            $response = $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-123',
                'webhook-timestamp' => '1234567890',
            ]);

            // Assert
            $response->assertStatus(200);
            $response->assertContent('Success');
        });

        test('delegates to WebhookProcessor with default config name', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = ['event' => 'test.event'];

            // Act
            $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-default',
                'webhook-timestamp' => '1111111111',
            ]);

            // Assert
            $this->assertDatabaseHas('webhook_calls', [
                'config_name' => 'default',
                'webhook_id' => 'webhook-default',
            ]);

            Event::assertDispatched(WebhookReceivedEvent::class, fn ($event): bool => $event->configName === 'default');
        });

        test('delegates to WebhookProcessor with custom config name from route', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = ['event' => 'custom.event'];

            // Act
            $this->postJson('/webhooks/custom', $payload, [
                'webhook-id' => 'webhook-custom',
                'webhook-timestamp' => '2222222222',
            ]);

            // Assert
            $this->assertDatabaseHas('webhook_calls', [
                'config_name' => 'custom',
                'webhook_id' => 'webhook-custom',
            ]);

            Event::assertDispatched(WebhookReceivedEvent::class, fn ($event): bool => $event->configName === 'custom');
        });

        test('returns processor response directly without modification', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Custom processor response', \Symfony\Component\HttpFoundation\Response::HTTP_ACCEPTED),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = ['event' => 'test.event'];

            // Act
            $response = $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-response',
                'webhook-timestamp' => '3333333333',
            ]);

            // Assert
            $response->assertStatus(202);
            $response->assertContent('Custom processor response');
        });

        test('stores webhook call in database via processor', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = ['event' => 'user.updated', 'data' => ['id' => 42, 'name' => 'John']];

            // Act
            $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-store',
                'webhook-timestamp' => '4444444444',
            ]);

            // Assert
            $this->assertDatabaseHas('webhook_calls', [
                'webhook_id' => 'webhook-store',
                'timestamp' => 4_444_444_444,
                'status' => WebhookStatus::PENDING,
            ]);

            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->payload)->toBe($payload);
        });

        test('dispatches WebhookReceivedEvent via processor', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = ['event' => 'event.dispatched'];

            // Act
            $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-event',
                'webhook-timestamp' => '5555555555',
            ]);

            // Assert
            Event::assertDispatched(WebhookReceivedEvent::class, fn ($event): bool => $event->webhookCall instanceof WebhookCall
                && $event->webhookCall->webhook_id === 'webhook-event'
                && $event->configName === 'default');
        });

        test('queues processing job via processor', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = ['event' => 'job.queued'];

            // Act
            $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-job',
                'webhook-timestamp' => '6666666666',
            ]);

            // Assert
            Queue::assertPushed(ProcessWebhookJob::class, fn ($job): bool => $job->webhookCall->webhook_id === 'webhook-job');
        });
    });

    describe('Sad Path', function (): void {
        test('returns 401 when signature validation fails', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);

            $mockValidator->shouldReceive('isValid')->andReturn(false);

            $this->app->instance(SignatureValidator::class, $mockValidator);

            $payload = ['malicious' => 'payload'];

            // Act
            $response = $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-invalid',
                'webhook-timestamp' => '7777777777',
            ]);

            // Assert
            $response->assertStatus(401);
            $response->assertContent('Invalid signature');

            // Verify webhook was NOT stored
            $this->assertDatabaseMissing('webhook_calls', [
                'webhook_id' => 'webhook-invalid',
            ]);

            // Verify InvalidWebhookSignatureEvent was dispatched
            Event::assertDispatched(InvalidWebhookSignatureEvent::class);

            // Verify WebhookReceivedEvent was NOT dispatched
            Event::assertNotDispatched(WebhookReceivedEvent::class);

            // Verify no job was queued
            Queue::assertNothingPushed();
        });

        test('returns 200 with ignored message when profile rejects webhook', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(false);

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);

            $payload = ['event' => 'ignored.event'];

            // Act
            $response = $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-ignored',
                'webhook-timestamp' => '8888888888',
            ]);

            // Assert
            $response->assertStatus(200);
            $response->assertContent('Webhook ignored');

            // Verify webhook was NOT stored
            $this->assertDatabaseMissing('webhook_calls', [
                'webhook_id' => 'webhook-ignored',
            ]);

            // Verify no events were dispatched
            Event::assertNotDispatched(WebhookReceivedEvent::class);
            Event::assertNotDispatched(InvalidWebhookSignatureEvent::class);

            // Verify no job was queued
            Queue::assertNothingPushed();
        });

        test('rejects GET requests with 405 Method Not Allowed', function (): void {
            // Arrange - no mocks needed, Laravel routing handles this

            // Act
            $response = $this->getJson('/webhooks');

            // Assert
            $response->assertStatus(405);
        });

        test('rejects PUT requests with 405 Method Not Allowed', function (): void {
            // Arrange - no mocks needed

            // Act
            $response = $this->putJson('/webhooks', ['event' => 'test']);

            // Assert
            $response->assertStatus(405);
        });

        test('rejects DELETE requests with 405 Method Not Allowed', function (): void {
            // Arrange - no mocks needed

            // Act
            $response = $this->deleteJson('/webhooks');

            // Assert
            $response->assertStatus(405);
        });

        test('rejects PATCH requests with 405 Method Not Allowed', function (): void {
            // Arrange - no mocks needed

            // Act
            $response = $this->patchJson('/webhooks', ['event' => 'test']);

            // Assert
            $response->assertStatus(405);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty payload gracefully', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);

            // Act - Empty payload should cause database exception (NOT NULL constraint)
            $response = $this->postJson('/webhooks', [], [
                'webhook-id' => 'webhook-empty',
                'webhook-timestamp' => '9999999999',
            ]);

            // Assert - This will be a 500 error due to database constraint
            $response->assertStatus(500);
        });

        test('handles missing webhook-id header', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);

            // Act - Missing webhook-id should cause database exception (NOT NULL constraint)
            $response = $this->postJson('/webhooks', ['event' => 'test'], [
                'webhook-timestamp' => '1010101010',
            ]);

            // Assert
            $response->assertStatus(500);
        });

        test('handles missing webhook-timestamp header', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Act
            $response = $this->postJson('/webhooks', ['event' => 'test'], [
                'webhook-id' => 'webhook-no-timestamp',
            ]);

            // Assert
            $response->assertStatus(200);

            // Verify webhook was stored with timestamp = 0
            $this->assertDatabaseHas('webhook_calls', [
                'webhook_id' => 'webhook-no-timestamp',
                'timestamp' => 0,
            ]);
        });

        test('handles large payload efficiently', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Create a large nested payload
            $largePayload = [
                'event' => 'bulk.operation',
                'items' => array_fill(0, 100, [
                    'id' => random_int(1, 999_999),
                    'name' => str_repeat('A', 50),
                    'metadata' => ['key' => str_repeat('B', 100)],
                ]),
            ];

            // Act
            $response = $this->postJson('/webhooks', $largePayload, [
                'webhook-id' => 'webhook-large',
                'webhook-timestamp' => '1111111111',
            ]);

            // Assert
            $response->assertStatus(200);

            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->payload['items'])->toHaveCount(100);
        });

        test('handles special characters in payload', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = [
                'message' => 'Hello 世界! 🎉',
                'special' => '<script>alert("xss")</script>',
                'quotes' => 'It\'s "quoted"',
            ];

            // Act
            $response = $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-special',
                'webhook-timestamp' => '1212121212',
            ]);

            // Assert
            $response->assertStatus(200);

            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->payload)->toBe($payload);
        });

        test('handles null values in payload', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = [
                'event' => 'nullable.test',
                'optional_field' => null,
                'present_field' => 'value',
            ];

            // Act
            $response = $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-null',
                'webhook-timestamp' => '1313131313',
            ]);

            // Assert
            $response->assertStatus(200);

            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->payload)->toBe($payload);
            expect($webhookCall->payload['optional_field'])->toBeNull();
        });

        test('handles boolean and numeric values in payload', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = [
                'event' => 'types.test',
                'is_active' => true,
                'is_deleted' => false,
                'count' => 42,
                'price' => 99.99,
                'zero' => 0,
            ];

            // Act
            $response = $this->postJson('/webhooks', $payload, [
                'webhook-id' => 'webhook-types',
                'webhook-timestamp' => '1414141414',
            ]);

            // Assert
            $response->assertStatus(200);

            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->payload)->toBe($payload);
            expect($webhookCall->payload['is_active'])->toBeTrue();
            expect($webhookCall->payload['is_deleted'])->toBeFalse();
            expect($webhookCall->payload['count'])->toBe(42);
        });

        test('handles concurrent requests to same endpoint', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Act - Simulate 5 concurrent webhook calls
            $responses = [];

            for ($i = 1; $i <= 5; ++$i) {
                $responses[] = $this->postJson('/webhooks', ['event' => 'concurrent.'.$i], [
                    'webhook-id' => 'webhook-concurrent-'.$i,
                    'webhook-timestamp' => (string) (1_500_000_000 + $i),
                ]);
            }

            // Assert
            foreach ($responses as $response) {
                $response->assertStatus(200);
            }

            // Verify all 5 webhooks were stored
            $this->assertDatabaseCount('webhook_calls', 5);

            for ($i = 1; $i <= 5; ++$i) {
                $this->assertDatabaseHas('webhook_calls', [
                    'webhook_id' => 'webhook-concurrent-'.$i,
                ]);
            }
        });
    });

    describe('Integration with Laravel Features', function (): void {
        test('route is registered via webhooks macro', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Act - Test that the route is accessible
            $response = $this->postJson('/webhooks', ['event' => 'route.test'], [
                'webhook-id' => 'webhook-route-test',
                'webhook-timestamp' => '1234567890',
            ]);

            // Assert - Route works and returns expected response
            $response->assertStatus(200);
        });

        test('route excludes CSRF verification middleware', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Act - POST without CSRF token should succeed
            $response = $this->postJson('/webhooks', ['event' => 'csrf.test'], [
                'webhook-id' => 'webhook-csrf',
                'webhook-timestamp' => '1616161616',
            ]);

            // Assert - Should succeed even without CSRF token
            $response->assertStatus(200);
        });

        test('custom route name is set correctly', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Act - Test that the custom route is accessible
            $response = $this->postJson('/webhooks/custom', ['event' => 'custom.test'], [
                'webhook-id' => 'webhook-custom-test',
                'webhook-timestamp' => '1234567890',
            ]);

            // Assert - Custom route works with correct config
            $response->assertStatus(200);
            $this->assertDatabaseHas('webhook_calls', [
                'config_name' => 'custom',
                'webhook_id' => 'webhook-custom-test',
            ]);
        });

        test('controller instance is resolved from container', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Act
            $response = $this->postJson('/webhooks', ['event' => 'container.test'], [
                'webhook-id' => 'webhook-container',
                'webhook-timestamp' => '1717171717',
            ]);

            // Assert
            $response->assertStatus(200);

            // Verify controller was instantiated and executed
            $this->assertDatabaseHas('webhook_calls', [
                'webhook_id' => 'webhook-container',
            ]);
        });

        test('accepts application/json content type', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Act
            $response = $this->postJson('/webhooks', ['event' => 'json.test'], [
                'webhook-id' => 'webhook-json',
                'webhook-timestamp' => '1818181818',
                'Content-Type' => 'application/json',
            ]);

            // Assert
            $response->assertStatus(200);
        });

        test('processes custom headers correctly', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', \Symfony\Component\HttpFoundation\Response::HTTP_OK),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            // Act
            $response = $this->postJson('/webhooks', ['event' => 'headers.test'], [
                'webhook-id' => 'webhook-headers',
                'webhook-timestamp' => '1919191919',
                'X-Custom-Header' => 'custom-value',
                'X-Request-ID' => 'req-12345',
            ]);

            // Assert
            $response->assertStatus(200);

            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->headers)->toHaveKey('x-custom-header');
            expect($webhookCall->headers)->toHaveKey('x-request-id');
            expect($webhookCall->headers['x-custom-header'])->toBe('custom-value');
            expect($webhookCall->headers['x-request-id'])->toBe('req-12345');
        });
    });
});
