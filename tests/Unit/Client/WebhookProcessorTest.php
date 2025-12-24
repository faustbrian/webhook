<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Client;

use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Contracts\WebhookProfile;
use Cline\Webhook\Client\Contracts\WebhookResponse;
use Cline\Webhook\Client\Events\InvalidWebhookSignatureEvent;
use Cline\Webhook\Client\Events\WebhookReceivedEvent;
use Cline\Webhook\Client\Jobs\ProcessWebhookJob;
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Client\WebhookProcessor;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;

use function beforeEach;
use function describe;
use function expect;
use function json_encode;
use function test;
use function uses;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Set primary key type to ID for simpler testing
    Config::set('webhook.primary_key_type', 'id');

    // Run migrations
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

    // Set up default config values
    Config::set('webhook.client.configs.default', [
        'signing_secret' => 'test-secret',
        'signature_validator' => SignatureValidator::class,
        'webhook_profile' => WebhookProfile::class,
        'webhook_response' => WebhookResponse::class,
        'webhook_model' => WebhookCall::class,
        'process_webhook_job' => ProcessWebhookJob::class,
        'store_headers' => ['*'],
    ]);

    // Fake events and queues
    Event::fake();
    Queue::fake();
});

describe('WebhookProcessor', function (): void {
    describe('Happy Path', function (): void {
        test('processes valid webhook successfully with all steps', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')
                ->once()
                ->andReturn(true);

            $mockProfile->shouldReceive('shouldProcess')
                ->once()
                ->andReturn(true);

            $mockResponse->shouldReceive('response')
                ->once()
                ->andReturn(
                    new Response('Success', 200),
                );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-123',
                'HTTP_WEBHOOK_TIMESTAMP' => '1234567890',
            ], '{"event": "user.created", "data": {"id": 1}}');

            $processor = new WebhookProcessor('default');

            // Act
            $response = $processor->process($request);

            // Assert
            expect($response)->toBeInstanceOf(Response::class);
            expect($response->getStatusCode())->toBe(200);

            // Verify webhook was stored in database
            $this->assertDatabaseHas('webhook_calls', [
                'config_name' => 'default',
                'webhook_id' => 'webhook-123',
                'timestamp' => 1_234_567_890,
                'status' => WebhookStatus::PENDING,
                'attempts' => 0,
            ]);

            // Verify event was dispatched
            Event::assertDispatched(WebhookReceivedEvent::class, function ($event): bool {
                return $event->webhookCall instanceof WebhookCall
                    && $event->configName === 'default';
            });

            // Verify job was dispatched
            Queue::assertPushed(ProcessWebhookJob::class);
        });

        test('stores payload correctly as JSON', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $payload = [
                'event' => 'user.updated',
                'data' => [
                    'id' => 42,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ];

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-456',
                'HTTP_WEBHOOK_TIMESTAMP' => '9876543210',
            ], json_encode($payload));

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->payload)->toBe($payload);
        });

        test('stores all headers when wildcard configured', function (): void {
            // Arrange
            Config::set('webhook.client.configs.default.store_headers', ['*']);

            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-789',
                'HTTP_WEBHOOK_TIMESTAMP' => '1111111111',
                'HTTP_X_CUSTOM_HEADER' => 'custom-value',
                'HTTP_USER_AGENT' => 'WebhookClient/1.0',
            ], '{"test": true}');

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->headers)->toHaveKey('webhook-id');
            expect($webhookCall->headers)->toHaveKey('webhook-timestamp');
            expect($webhookCall->headers)->toHaveKey('x-custom-header');
            expect($webhookCall->headers)->toHaveKey('user-agent');
        });

        test('uses custom config name correctly', function (): void {
            // Arrange
            Config::set('webhook.client.configs.stripe', [
                'signing_secret' => 'stripe-secret',
                'signature_validator' => SignatureValidator::class,
                'webhook_profile' => WebhookProfile::class,
                'webhook_response' => WebhookResponse::class,
                'webhook_model' => WebhookCall::class,
                'process_webhook_job' => ProcessWebhookJob::class,
                'store_headers' => ['*'],
            ]);

            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'stripe-123',
                'HTTP_WEBHOOK_TIMESTAMP' => '2222222222',
            ], '{"type": "charge.succeeded"}');

            $processor = new WebhookProcessor('stripe');

            // Act
            $processor->process($request);

            // Assert
            $this->assertDatabaseHas('webhook_calls', [
                'config_name' => 'stripe',
                'webhook_id' => 'stripe-123',
            ]);

            Event::assertDispatched(WebhookReceivedEvent::class, function ($event): bool {
                return $event->configName === 'stripe';
            });
        });
    });

    describe('Sad Path', function (): void {
        test('returns 401 when signature is invalid', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);

            $mockValidator->shouldReceive('isValid')
                ->once()
                ->andReturn(false);

            $this->app->instance(SignatureValidator::class, $mockValidator);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-invalid',
                'HTTP_WEBHOOK_TIMESTAMP' => '3333333333',
            ], '{"malicious": "payload"}');

            $processor = new WebhookProcessor('default');

            // Act
            $response = $processor->process($request);

            // Assert
            expect($response->getStatusCode())->toBe(401);
            expect($response->getContent())->toBe('Invalid signature');

            // Verify webhook was NOT stored
            $this->assertDatabaseMissing('webhook_calls', [
                'webhook_id' => 'webhook-invalid',
            ]);

            // Verify InvalidWebhookSignatureEvent was dispatched
            Event::assertDispatched(InvalidWebhookSignatureEvent::class, function ($event) use ($request): bool {
                return $event->request === $request
                    && $event->configName === 'default';
            });

            // Verify WebhookReceivedEvent was NOT dispatched
            Event::assertNotDispatched(WebhookReceivedEvent::class);

            // Verify job was NOT dispatched
            Queue::assertNothingPushed();
        });

        test('returns 200 but does not process when profile rejects webhook', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')
                ->once()
                ->andReturn(false);

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-rejected',
                'HTTP_WEBHOOK_TIMESTAMP' => '4444444444',
            ], '{"event": "ignored.event"}');

            $processor = new WebhookProcessor('default');

            // Act
            $response = $processor->process($request);

            // Assert
            expect($response->getStatusCode())->toBe(200);
            expect($response->getContent())->toBe('Webhook ignored');

            // Verify webhook was NOT stored
            $this->assertDatabaseMissing('webhook_calls', [
                'webhook_id' => 'webhook-rejected',
            ]);

            // Verify events were NOT dispatched
            Event::assertNotDispatched(WebhookReceivedEvent::class);
            Event::assertNotDispatched(InvalidWebhookSignatureEvent::class);

            // Verify job was NOT dispatched
            Queue::assertNothingPushed();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles missing webhook-id header gracefully', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_TIMESTAMP' => '5555555555',
            ], '{"event": "test"}');

            $processor = new WebhookProcessor('default');

            // Act & Assert - This should throw an exception because webhook_id is NOT NULL in the migration
            $this->expectException(QueryException::class);
            $processor->process($request);
        });

        test('handles missing timestamp header gracefully', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-no-timestamp',
            ], '{"event": "test"}');

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->webhook_id)->toBe('webhook-no-timestamp');
            expect($webhookCall->timestamp)->toBe(0);
        });

        test('stores empty payload when request body is empty', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-empty',
                'HTTP_WEBHOOK_TIMESTAMP' => '6666666666',
            ], '');

            $processor = new WebhookProcessor('default');

            // Act & Assert - This should throw an exception because payload is NOT NULL in the migration
            $this->expectException(QueryException::class);
            $processor->process($request);
        });

        test('filters headers based on store_headers configuration', function (): void {
            // Arrange
            Config::set('webhook.client.configs.default.store_headers', [
                'webhook-id',
                'x-custom-header',
            ]);

            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-filtered',
                'HTTP_WEBHOOK_TIMESTAMP' => '7777777777',
                'HTTP_X_CUSTOM_HEADER' => 'keep-this',
                'HTTP_USER_AGENT' => 'ignore-this',
                'HTTP_AUTHORIZATION' => 'ignore-this-too',
            ], '{"test": true}');

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->headers)->toHaveKey('webhook-id');
            expect($webhookCall->headers)->toHaveKey('x-custom-header');
            expect($webhookCall->headers)->not->toHaveKey('user-agent');
            expect($webhookCall->headers)->not->toHaveKey('authorization');
            expect($webhookCall->headers)->not->toHaveKey('webhook-timestamp');
        });

        test('handles case-insensitive header matching in filtering', function (): void {
            // Arrange
            Config::set('webhook.client.configs.default.store_headers', [
                'X-Custom-Header',
                'WEBHOOK-ID',
            ]);

            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-case',
                'HTTP_X_CUSTOM_HEADER' => 'case-insensitive',
            ], '{"test": true}');

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->headers)->toHaveKey('webhook-id');
            expect($webhookCall->headers)->toHaveKey('x-custom-header');
            expect($webhookCall->headers['webhook-id'])->toBe('webhook-case');
            expect($webhookCall->headers['x-custom-header'])->toBe('case-insensitive');
        });

        test('stores empty headers array when no headers match filter', function (): void {
            // Arrange
            Config::set('webhook.client.configs.default.store_headers', [
                'x-nonexistent-header',
                'x-another-missing',
            ]);

            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-no-match',
                'HTTP_WEBHOOK_TIMESTAMP' => '8888888888',
            ], '{"test": true}');

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->headers)->toBeArray();
            expect($webhookCall->headers)->toBeEmpty();
        });

        test('handles complex nested JSON payload correctly', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $complexPayload = [
                'event' => 'order.completed',
                'data' => [
                    'id' => 12_345,
                    'customer' => [
                        'name' => 'Jane Doe',
                        'email' => 'jane@example.com',
                        'metadata' => [
                            'referral_code' => 'ABC123',
                            'loyalty_points' => 500,
                        ],
                    ],
                    'items' => [
                        ['sku' => 'ITEM-001', 'quantity' => 2, 'price' => 29.99],
                        ['sku' => 'ITEM-002', 'quantity' => 1, 'price' => 49.99],
                    ],
                    'total' => 109.97,
                ],
                'metadata' => [
                    'source' => 'mobile_app',
                    'version' => '2.3.1',
                ],
            ];

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-complex',
                'HTTP_WEBHOOK_TIMESTAMP' => '9999999999',
            ], json_encode($complexPayload));

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->payload)->toBe($complexPayload);
            expect($webhookCall->payload['data']['customer']['metadata']['referral_code'])->toBe('ABC123');
            expect($webhookCall->payload['data']['items'])->toHaveCount(2);
        });

        test('handles unicode characters in payload', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $unicodePayload = [
                'message' => 'Hello 世界! 🎉',
                'emoji' => '🚀💡✨',
                'special' => 'Café, naïve, résumé',
            ];

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-unicode',
                'HTTP_WEBHOOK_TIMESTAMP' => '1010101010',
            ], json_encode($unicodePayload));

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            $webhookCall = WebhookCall::query()->first();
            expect($webhookCall->payload['message'])->toBe('Hello 世界! 🎉');
            expect($webhookCall->payload['emoji'])->toBe('🚀💡✨');
        });
    });

    describe('Integration Tests', function (): void {
        test('signature validator receives correct parameters', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            Config::set('webhook.client.configs.default.signing_secret', 'my-secret-key');

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-validator',
                'HTTP_WEBHOOK_TIMESTAMP' => '1212121212',
            ], '{"test": true}');

            $mockValidator->shouldReceive('isValid')
                ->once()
                ->with(
                    Mockery::on(fn ($r): bool => $r === $request),
                    'my-secret-key',
                )
                ->andReturn(true);

            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert - expectations verified by Mockery
        });

        test('webhook profile receives correct request', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-profile',
                'HTTP_WEBHOOK_TIMESTAMP' => '1313131313',
            ], '{"event": "test.event"}');

            $mockValidator->shouldReceive('isValid')->andReturn(true);

            $mockProfile->shouldReceive('shouldProcess')
                ->once()
                ->with(Mockery::on(fn ($r): bool => $r === $request))
                ->andReturn(true);

            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert - expectations verified by Mockery
        });

        test('webhook response handler receives created WebhookCall', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);

            $mockResponse->shouldReceive('response')
                ->once()
                ->with(Mockery::on(function ($webhookCall): bool {
                    return $webhookCall instanceof WebhookCall
                        && $webhookCall->webhook_id === 'webhook-response'
                        && $webhookCall->config_name === 'default';
                }))
                ->andReturn(
                    new Response('Custom Response', 202),
                );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-response',
                'HTTP_WEBHOOK_TIMESTAMP' => '1414141414',
            ], '{"test": true}');

            $processor = new WebhookProcessor('default');

            // Act
            $response = $processor->process($request);

            // Assert
            expect($response->getStatusCode())->toBe(202);
            expect($response->getContent())->toBe('Custom Response');
        });

        test('dispatched job receives created WebhookCall', function (): void {
            // Arrange
            $mockValidator = Mockery::mock(SignatureValidator::class);
            $mockProfile = Mockery::mock(WebhookProfile::class);
            $mockResponse = Mockery::mock(WebhookResponse::class);

            $mockValidator->shouldReceive('isValid')->andReturn(true);
            $mockProfile->shouldReceive('shouldProcess')->andReturn(true);
            $mockResponse->shouldReceive('response')->andReturn(
                new Response('Success', 200),
            );

            $this->app->instance(SignatureValidator::class, $mockValidator);
            $this->app->instance(WebhookProfile::class, $mockProfile);
            $this->app->instance(WebhookResponse::class, $mockResponse);

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WEBHOOK_ID' => 'webhook-job',
                'HTTP_WEBHOOK_TIMESTAMP' => '1515151515',
            ], '{"event": "job.test"}');

            $processor = new WebhookProcessor('default');

            // Act
            $processor->process($request);

            // Assert
            Queue::assertPushed(ProcessWebhookJob::class);

            // Verify the webhook was created with correct data
            $this->assertDatabaseHas('webhook_calls', [
                'webhook_id' => 'webhook-job',
                'status' => WebhookStatus::PENDING,
            ]);
        });
    });
});
