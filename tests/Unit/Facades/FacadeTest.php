<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Facades;

use Cline\VariableKeys\Enums\PrimaryKeyType;
use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Profiles\ProcessEverything;
use Cline\Webhook\Client\Responses\DefaultResponse;
use Cline\Webhook\Client\Validators\HmacValidator;
use Cline\Webhook\Client\WebhookProcessor;
use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Facades\WebhookClient;
use Cline\Webhook\Facades\WebhookServer;
use Cline\Webhook\Server\Jobs\CallWebhookJob;
use Cline\Webhook\Server\Strategies\ExponentialBackoffStrategy;
use Cline\Webhook\Server\WebhookCall;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionClass;
use Symfony\Component\HttpFoundation\ParameterBag;
use Tests\TestCase;

use function get_class_methods;
use function method_exists;
use function time;

/**
 * Comprehensive tests for WebhookClient and WebhookServer facades.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
#[CoversClass(WebhookClient::class)]
#[CoversClass(WebhookServer::class)]
#[Small()]
final class FacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set primary key type for webhook_calls table
        Config::set('webhook.primary_key_type', PrimaryKeyType::ULID->value);

        // Set required configuration for client tests
        Config::set('webhook.client.configs.default.signature_validator', HmacValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'test-secret');
        Config::set('webhook.client.configs.default.webhook_profile', ProcessEverything::class);
        Config::set('webhook.client.configs.default.webhook_response', DefaultResponse::class);
        Config::set('webhook.client.configs.default.webhook_model', \Cline\Webhook\Client\Models\WebhookCall::class);
        Config::set('webhook.client.configs.default.store_headers', ['*']);
        Config::set('webhook.client.configs.default.timestamp_tolerance_seconds', 300);

        // Set required configuration for server tests
        Config::set('webhook.server.http_verb', 'POST');
        Config::set('webhook.server.timeout_in_seconds', 3);
        Config::set('webhook.server.tries', 3);
        Config::set('webhook.server.verify_ssl', true);
        Config::set('webhook.server.throw_exception_on_failure', false);
        Config::set('webhook.server.queue', null);
        Config::set('webhook.server.signature_version', SignatureVersion::V1_HMAC->value);
        Config::set('webhook.server.signing_secret', 'test-secret');
        Config::set('webhook.server.ed25519_private_key', 'test-private-key');
    }

    // ========================================
    // WebhookClient Facade Tests
    // ========================================

    #[Test()]
    #[TestDox('WebhookClient getFacadeAccessor() returns correct binding name')]
    #[Group('happy-path')]
    public function webhook_client_get_facade_accessor_returns_correct_binding_name(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookClient::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        // Act
        $accessor = $method->invoke(null);

        // Assert
        $this->assertSame(WebhookProcessor::class, $accessor);
    }

    #[Test()]
    #[TestDox('WebhookClient resolves to WebhookProcessor instance from container')]
    #[Group('happy-path')]
    public function webhook_client_resolves_to_webhook_processor_from_container(): void
    {
        // Arrange & Act
        $resolved = WebhookClient::getFacadeRoot();

        // Assert
        $this->assertInstanceOf(WebhookProcessor::class, $resolved);
    }

    #[Test()]
    #[TestDox('WebhookClient facade process() method is callable and returns Response')]
    #[Group('happy-path')]
    public function webhook_client_facade_process_method_is_callable_and_returns_response(): void
    {
        // Arrange
        $processor = WebhookClient::getFacadeRoot();

        // Act & Assert
        $this->assertInstanceOf(WebhookProcessor::class, $processor);
        $this->assertTrue(method_exists($processor, 'process'), 'process() method should exist on WebhookProcessor');
    }

    #[Test()]
    #[TestDox('WebhookClient facade correctly passes method calls to WebhookProcessor instance')]
    #[Group('happy-path')]
    public function webhook_client_facade_correctly_passes_method_calls_to_webhook_processor_instance(): void
    {
        // Arrange
        $processorViaFacade = WebhookClient::getFacadeRoot();
        $processorDirect = new WebhookProcessor();

        // Act & Assert
        $this->assertInstanceOf($processorDirect::class, $processorViaFacade);
        $this->assertEquals(
            get_class_methods($processorDirect),
            get_class_methods($processorViaFacade),
            'Facade should expose same methods as underlying class',
        );
    }

    #[Test()]
    #[TestDox('WebhookClient facade handles invalid signature gracefully')]
    #[Group('sad-path')]
    public function webhook_client_facade_handles_invalid_signature_gracefully(): void
    {
        // Arrange
        $request = Request::create('https://example.com/webhook', 'POST');
        $request->headers->set('webhook-signature', 'sha256=invalid-signature');
        $request->headers->set('webhook-id', 'webhook-123');
        $request->headers->set('webhook-timestamp', (string) time());
        $request->setJson(
            new ParameterBag(['event' => 'test.event']),
        );

        // Mock the signature validation to fail
        $mockValidator = $this->createMock(SignatureValidator::class);
        $mockValidator->method('isValid')->willReturn(false);

        $this->app->bind(
            HmacValidator::class,
            fn () => $mockValidator,
        );

        // Act
        $response = WebhookClient::process($request);

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid signature', $response->getContent());
    }

    #[Test()]
    #[TestDox('WebhookClient facade maintains singleton behavior across calls')]
    #[Group('edge-case')]
    public function webhook_client_facade_maintains_singleton_behavior(): void
    {
        // Arrange & Act
        $instance1 = WebhookClient::getFacadeRoot();
        $instance2 = WebhookClient::getFacadeRoot();

        // Assert
        $this->assertSame($instance1, $instance2, 'Facade should return same instance');
    }

    // ========================================
    // WebhookServer Facade Tests
    // ========================================

    #[Test()]
    #[TestDox('WebhookServer getFacadeAccessor() returns correct binding name')]
    #[Group('happy-path')]
    public function webhook_server_get_facade_accessor_returns_correct_binding_name(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookServer::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        // Act
        $accessor = $method->invoke(null);

        // Assert
        $this->assertSame(WebhookCall::class, $accessor);
    }

    #[Test()]
    #[TestDox('WebhookServer resolves to WebhookCall instance from container')]
    #[Group('happy-path')]
    public function webhook_server_resolves_to_webhook_call_from_container(): void
    {
        // Arrange & Act
        $resolved = WebhookServer::getFacadeRoot();

        // Assert
        $this->assertInstanceOf(WebhookCall::class, $resolved);
    }

    #[Test()]
    #[TestDox('WebhookServer create() method returns new WebhookCall instance')]
    #[Group('happy-path')]
    public function webhook_server_create_method_returns_new_webhook_call_instance(): void
    {
        // Arrange & Act
        $webhookCall = WebhookServer::create();

        // Assert
        $this->assertInstanceOf(WebhookCall::class, $webhookCall);
    }

    #[Test()]
    #[TestDox('WebhookServer create() returns different instances on each call')]
    #[Group('happy-path')]
    public function webhook_server_create_returns_different_instances_on_each_call(): void
    {
        // Arrange & Act
        $instance1 = WebhookServer::create();
        $instance2 = WebhookServer::create();

        // Assert
        $this->assertNotSame($instance1, $instance2, 'Each create() should return new instance');
    }

    #[Test()]
    #[TestDox('WebhookServer facade proxies url() method to underlying WebhookCall')]
    #[Group('happy-path')]
    public function webhook_server_facade_proxies_url_method_to_webhook_call(): void
    {
        // Arrange
        $url = 'https://example.com/webhook';

        // Act
        $result = WebhookServer::create()->url($url);

        // Assert
        $this->assertInstanceOf(WebhookCall::class, $result);
    }

    #[Test()]
    #[TestDox('WebhookServer facade proxies payload() method to underlying WebhookCall')]
    #[Group('happy-path')]
    public function webhook_server_facade_proxies_payload_method_to_webhook_call(): void
    {
        // Arrange
        $payload = ['event' => 'user.created', 'data' => ['id' => 123]];

        // Act
        $result = WebhookServer::create()->payload($payload);

        // Assert
        $this->assertInstanceOf(WebhookCall::class, $result);
    }

    #[Test()]
    #[TestDox('WebhookServer facade proxies dispatch() method to underlying WebhookCall')]
    #[Group('happy-path')]
    public function webhook_server_facade_proxies_dispatch_method_to_webhook_call(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookServer::create()
            ->url('https://example.com/webhook')
            ->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('WebhookServer facade supports full fluent method chaining')]
    #[Group('happy-path')]
    public function webhook_server_facade_supports_full_fluent_method_chaining(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookServer::create()
            ->url('https://example.com/webhook')
            ->payload(['event' => 'test.event'])
            ->withHeaders(['X-Custom' => 'value'])
            ->meta(['key' => 'value'])
            ->tags(['tag1', 'tag2'])
            ->useHttpVerb('POST')
            ->timeoutInSeconds(5)
            ->maximumTries(3)
            ->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('WebhookServer facade proxies dispatchIf() with true condition')]
    #[Group('happy-path')]
    public function webhook_server_facade_proxies_dispatch_if_with_true_condition(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookServer::create()
            ->url('https://example.com/webhook')
            ->dispatchIf(true);

        // Assert
        Queue::assertPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('WebhookServer facade proxies dispatchIf() with false condition')]
    #[Group('happy-path')]
    public function webhook_server_facade_proxies_dispatch_if_with_false_condition(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookServer::create()
            ->url('https://example.com/webhook')
            ->dispatchIf(false);

        // Assert
        Queue::assertNotPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('WebhookServer facade proxies dispatchUnless() with false condition')]
    #[Group('happy-path')]
    public function webhook_server_facade_proxies_dispatch_unless_with_false_condition(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookServer::create()
            ->url('https://example.com/webhook')
            ->dispatchUnless(false);

        // Assert
        Queue::assertPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('WebhookServer facade proxies dispatchUnless() with true condition')]
    #[Group('happy-path')]
    public function webhook_server_facade_proxies_dispatch_unless_with_true_condition(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookServer::create()
            ->url('https://example.com/webhook')
            ->dispatchUnless(true);

        // Assert
        Queue::assertNotPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('WebhookServer facade proxies all configuration methods')]
    #[Group('happy-path')]
    public function webhook_server_facade_proxies_all_configuration_methods(): void
    {
        // Arrange
        Queue::fake();

        // Act - Test all fluent configuration methods
        $webhookCall = WebhookServer::create()
            ->url('https://example.com/webhook')
            ->payload(['test' => 'data'])
            ->withHeaders(['X-Test' => 'value'])
            ->meta(['meta' => 'data'])
            ->tags('test-tag')
            ->useHttpVerb('POST')
            ->timeoutInSeconds(10)
            ->maximumTries(5)
            ->useBackoffStrategy(
                new ExponentialBackoffStrategy(),
            )
            ->doNotVerifySsl()
            ->webhookId('test-id')
            ->useSecret('test-secret')
            ->signatureVersion(SignatureVersion::V1_HMAC)
            ->onQueue('test-queue')
            ->throwExceptionOnFailure();

        $webhookCall->dispatch();

        // Assert
        Queue::assertPushedOn('test-queue', CallWebhookJob::class);
    }

    // ========================================
    // Edge Case Tests
    // ========================================

    #[Test()]
    #[TestDox('WebhookClient facade works after clearing resolved instances')]
    #[Group('edge-case')]
    public function webhook_client_facade_works_after_clearing_resolved_instances(): void
    {
        // Arrange
        $firstInstance = WebhookClient::getFacadeRoot();
        WebhookClient::clearResolvedInstance(WebhookProcessor::class);

        // Act
        $secondInstance = WebhookClient::getFacadeRoot();

        // Assert
        $this->assertNotSame($firstInstance, $secondInstance, 'Should create new instance after clearing');
        $this->assertInstanceOf(WebhookProcessor::class, $secondInstance);
    }

    #[Test()]
    #[TestDox('WebhookServer facade works after clearing resolved instances')]
    #[Group('edge-case')]
    public function webhook_server_facade_works_after_clearing_resolved_instances(): void
    {
        // Arrange
        $firstInstance = WebhookServer::getFacadeRoot();
        WebhookServer::clearResolvedInstance(WebhookCall::class);

        // Act
        $secondInstance = WebhookServer::getFacadeRoot();

        // Assert
        $this->assertNotSame($firstInstance, $secondInstance, 'Should create new instance after clearing');
        $this->assertInstanceOf(WebhookCall::class, $secondInstance);
    }

    #[Test()]
    #[TestDox('WebhookClient facade works with custom container binding')]
    #[Group('edge-case')]
    public function webhook_client_facade_works_with_custom_container_binding(): void
    {
        // Arrange
        $customProcessor = new WebhookProcessor('custom-config');
        $this->app->instance(WebhookProcessor::class, $customProcessor);

        WebhookClient::clearResolvedInstance(WebhookProcessor::class);

        // Act
        $resolved = WebhookClient::getFacadeRoot();

        // Assert
        $this->assertSame($customProcessor, $resolved);
    }

    #[Test()]
    #[TestDox('WebhookServer facade works with custom container binding')]
    #[Group('edge-case')]
    public function webhook_server_facade_works_with_custom_container_binding(): void
    {
        // Arrange
        $customWebhookCall = WebhookCall::create();
        $this->app->instance(WebhookCall::class, $customWebhookCall);

        WebhookServer::clearResolvedInstance(WebhookCall::class);

        // Act
        $resolved = WebhookServer::getFacadeRoot();

        // Assert
        $this->assertSame($customWebhookCall, $resolved);
    }

    #[Test()]
    #[TestDox('Both facades work correctly when service provider is registered')]
    #[Group('edge-case')]
    public function both_facades_work_correctly_when_service_provider_is_registered(): void
    {
        // Arrange & Act
        $clientRoot = WebhookClient::getFacadeRoot();
        $serverRoot = WebhookServer::getFacadeRoot();

        // Assert
        $this->assertInstanceOf(WebhookProcessor::class, $clientRoot);
        $this->assertInstanceOf(WebhookCall::class, $serverRoot);
    }

    #[Test()]
    #[TestDox('WebhookClient facade is marked as final')]
    #[Group('edge-case')]
    public function webhook_client_facade_is_marked_as_final(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookClient::class);

        // Act & Assert
        $this->assertTrue($reflection->isFinal(), 'WebhookClient facade should be final');
    }

    #[Test()]
    #[TestDox('WebhookServer facade is marked as final')]
    #[Group('edge-case')]
    public function webhook_server_facade_is_marked_as_final(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookServer::class);

        // Act & Assert
        $this->assertTrue($reflection->isFinal(), 'WebhookServer facade should be final');
    }

    #[Test()]
    #[TestDox('WebhookClient facade extends Laravel Facade class')]
    #[Group('edge-case')]
    public function webhook_client_facade_extends_laravel_facade_class(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookClient::class);

        // Act & Assert
        $this->assertTrue(
            $reflection->isSubclassOf(Facade::class),
            'WebhookClient should extend Illuminate\Support\Facades\Facade',
        );
    }

    #[Test()]
    #[TestDox('WebhookServer facade extends Laravel Facade class')]
    #[Group('edge-case')]
    public function webhook_server_facade_extends_laravel_facade_class(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookServer::class);

        // Act & Assert
        $this->assertTrue(
            $reflection->isSubclassOf(Facade::class),
            'WebhookServer should extend Illuminate\Support\Facades\Facade',
        );
    }

    #[Test()]
    #[TestDox('WebhookServer facade create() method is static')]
    #[Group('edge-case')]
    public function webhook_server_facade_create_method_is_static(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookServer::class);

        // Act
        $method = $reflection->getMethod('create');

        // Assert
        $this->assertTrue($method->isStatic(), 'create() method should be static');
        $this->assertTrue($method->isPublic(), 'create() method should be public');
    }

    #[Test()]
    #[TestDox('WebhookClient facade has correct @method docblock annotations')]
    #[Group('edge-case')]
    public function webhook_client_facade_has_correct_method_docblock_annotations(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookClient::class);

        // Act
        $docComment = $reflection->getDocComment();

        // Assert
        $this->assertNotFalse($docComment, 'Facade should have docblock');
        $this->assertStringContainsString('@method static Response process(Request $request', $docComment);
        $this->assertStringContainsString('@see WebhookProcessor', $docComment);
    }

    #[Test()]
    #[TestDox('WebhookServer facade has correct @method docblock annotations')]
    #[Group('edge-case')]
    public function webhook_server_facade_has_correct_method_docblock_annotations(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WebhookServer::class);

        // Act
        $docComment = $reflection->getDocComment();

        // Assert
        $this->assertNotFalse($docComment, 'Facade should have docblock');
        $this->assertStringContainsString('@method static void        dispatch()', $docComment);
        $this->assertStringContainsString('@method static WebhookCall url(string $url)', $docComment);
        $this->assertStringContainsString('@method static WebhookCall payload(array<string, mixed> $payload)', $docComment);
        $this->assertStringContainsString('@see WebhookCall', $docComment);
    }
}
