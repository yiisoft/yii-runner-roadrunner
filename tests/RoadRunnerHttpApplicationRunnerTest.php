<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionObject;
use RuntimeException;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Yiisoft\Config\Config;
use Yiisoft\Config\ConfigPaths;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Middleware\Dispatcher\Event\AfterMiddleware;
use Yiisoft\Middleware\Dispatcher\Event\BeforeMiddleware;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Test\Support\Log\SimpleLogger;
use Yiisoft\Yii\Event\InvalidListenerConfigurationException;
use Yiisoft\Yii\Http\Event\AfterEmit;
use Yiisoft\Yii\Http\Event\AfterRequest;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;
use Yiisoft\Yii\Http\Event\ApplicationStartup;
use Yiisoft\Yii\Http\Event\BeforeRequest;
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpApplicationRunner;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\PlainTextRendererMock;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Psr7WorkerMock;

use function gc_status;
use function json_encode;

final class RoadRunnerHttpApplicationRunnerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_ENV['RR_MODE'] = '';
        parent::tearDown();
    }

    public function testFirstRunGarbageCollector(): int
    {
        $gcRuns = gc_status()['runs'];
        $this->assertSame($gcRuns === 0 ? 0 : 1, $gcRuns);

        return $gcRuns;
    }

    public function testUnsupportedMode(): void
    {
        $_ENV['RR_MODE'] = 'invalid';
        $runner = $this->createRunner();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mode "invalid", modes are supported: "http", "temporal".');
        $runner->run();
    }

    public function testTemporalInactiveException(): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_TEMPORAL;
        $runner = $this->createRunner();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Temporal support is disabled. You should call `withEnabledTemporal(true)` to enable temporal support.');
        $runner->run();
    }

    /**
     * @depends testFirstRunGarbageCollector
     */
    public function testCheckGarbageCollector(int $gcRuns): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_HTTP;
        $worker = $this->createWorker();
        $runner = $this->createRunner(worker: $worker);

        $this->expectOutputString($this->getResponseData(Status::OK, [], 'OK'));
        $runner->run();
        $this->assertSame(2, $worker->getRequestCount());

        $this->assertSame($gcRuns === 0 ? 1 : 3, gc_status()['runs']);
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithDefaults(): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_HTTP;
        $worker = $this->createWorker();
        $runner = $this->createRunner(worker: $worker);

        $this->expectOutputString($this->getResponseData(Status::OK, [], 'OK'));
        $runner->run();
        $this->assertSame(2, $worker->getRequestCount());
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithBootstrap(): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_HTTP;
        $runner = $this->createRunner(bootstrapGroup: 'bootstrap-web');

        $this->expectOutputString("Bootstrapping{$this->getResponseData(Status::OK, [], 'OK')}");
        $runner->run();
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithCheckingEvents(): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_HTTP;
        $runner = $this->createRunner(checkEvents: true, eventsGroup: 'events-fail');

        $this->expectException(InvalidListenerConfigurationException::class);

        $runner->run();
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithCustomizedConfiguration(): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_HTTP;
        $config = $this->createConfig();
        $container = $this->createContainer();
        $runner = $this->createRunner()
            ->withContainer($container)
            ->withConfig($config)
            ->withTemporaryErrorHandler($this->createErrorHandler());

        $this->expectOutputString($this->getResponseData());

        $runner->run();

        $this->assertTrue($this->isStateReset($container));

        /** @var SimpleEventDispatcher $dispatcher */
        $dispatcher = $container->get(EventDispatcherInterface::class);

        $this->assertSame(
            [
                ApplicationStartup::class,
                BeforeRequest::class,
                BeforeMiddleware::class,
                AfterMiddleware::class,
                AfterRequest::class,
                AfterEmit::class,
                ApplicationShutdown::class,
            ],
            $dispatcher->getEventClasses(),
        );
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithWaitRequestNullReturn(): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_HTTP;
        $worker = new Psr7WorkerMock();
        $container = $this->createContainer();
        $runner = $this->createRunner(worker: $worker)
            ->withPsr7Worker($worker)
            ->withContainer($container);

        $this->expectOutputString('');

        $runner->run();

        $this->assertSame(1, $worker->getRequestCount());
        $this->assertFalse($this->isStateReset($container));

        /** @var SimpleEventDispatcher $dispatcher */
        $dispatcher = $container->get(EventDispatcherInterface::class);

        $this->assertSame(
            [
                ApplicationStartup::class,
                ApplicationShutdown::class,
            ],
            $dispatcher->getEventClasses(),
        );
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithWaitRequestThrowableInstanceReturn(): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_HTTP;
        $worker = new Psr7WorkerMock(new RuntimeException('Some error'));
        $container = $this->createContainer();
        $runner = $this->createRunner(worker: $worker)
            ->withContainer($container);

        $this->expectOutputString(
            $this->getResponseData(
                Status::BAD_REQUEST,
                [],
                json_encode([
                    'error-message' => 'Some error',
                    'request-method' => '',
                    'request-uri' => '',
                    'request-attribute-exists' => false,
                ]),
            )
        );

        $runner->run();

        $this->assertSame(2, $worker->getRequestCount());
        $this->assertTrue($this->isStateReset($container));

        /** @var SimpleEventDispatcher $dispatcher */
        $dispatcher = $container->get(EventDispatcherInterface::class);

        $this->assertSame(
            [
                ApplicationStartup::class,
                AfterEmit::class,
                ApplicationShutdown::class,
            ],
            $dispatcher->getEventClasses(),
        );
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithFailureDuringRunningProcess(): void
    {
        $_ENV['RR_MODE'] = Mode::MODE_HTTP;
        $container = $this->createContainer(true);
        $runner = $this->createRunner()->withContainer($container);

        $this->expectOutputString($this->getResponseData(
            Status::INTERNAL_SERVER_ERROR,
            ['Content-Type' => ['text/plain']],
            json_encode([
                'error-message' => 'Failure',
                'request-method' => Method::GET,
                'request-uri' => '/',
                'request-attribute-exists' => true,
            ]),
        ));

        $runner->run();

        $this->assertTrue($this->isStateReset($container));
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testImmutability(): void
    {
        $runner = $this->createRunner();
        $this->assertNotSame($runner, $runner->withConfig($this->createConfig()));
        $this->assertNotSame($runner, $runner->withContainer($this->createContainer()));
        $this->assertNotSame($runner, $runner->withTemporaryErrorHandler($this->createErrorHandler()));
        $this->assertNotSame($runner, $runner->withEnabledTemporal(true));
        $this->assertNotSame($runner, $runner->withPsr7Worker(new Psr7WorkerMock()));
    }

    private function createConfig(string $configDirectory = 'config'): Config
    {
        return new Config(new ConfigPaths(__DIR__ . '/Support', $configDirectory));
    }

    private function createErrorHandler(): ErrorHandler
    {
        return new ErrorHandler(new SimpleLogger(), new PlainTextRendererMock());
    }

    private function isStateReset(ContainerInterface $container): bool
    {
        /** @var ErrorHandler $errorHandler */
        $errorHandler = $container->get(ErrorHandler::class);
        $object = new ReflectionObject($errorHandler);

        $property = $object->getProperty('debug');
        $property->setAccessible(true);
        $debugValue = $property->getValue($errorHandler);
        $property->setAccessible(false);

        return !$debugValue;
    }

    private function createRunner(
        ?PSR7WorkerInterface $worker = null,
        bool $checkEvents = false,
        string $bootstrapGroup = 'non-exists',
        string $eventsGroup = 'events-web',
    ): RoadRunnerHttpApplicationRunner {
        return (new RoadRunnerHttpApplicationRunner(
            __DIR__ . '/Support',
            debug: true,
            checkEvents: $checkEvents,
            bootstrapGroup: $bootstrapGroup,
            eventsGroup: $eventsGroup,
        ))
            ->withPsr7Worker($worker ?? $this->createWorker());
    }

    private function createWorker(): PSR7WorkerInterface
    {
        return new Psr7WorkerMock($this->createServerRequest());
    }
}
