<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionObject;
use RuntimeException;
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
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerApplicationRunner;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\PlainTextRendererMock;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Psr7WorkerMock;

use function gc_status;
use function json_encode;

final class RoadRunnerApplicationRunnerTest extends TestCase
{
    private RoadRunnerApplicationRunner $runner;
    private Psr7WorkerMock $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->worker = new Psr7WorkerMock($this->createServerRequest());
        $this->runner = (new RoadRunnerApplicationRunner(__DIR__ . '/Support', true, null))
            ->withoutBootstrap()
            ->withoutCheckingEvents()
            ->withPsr7Worker($this->worker)
        ;
    }

    public function testCheckGarbageCollector(): void
    {
        $this->assertSame(0, gc_status()['runs']);

        $this->expectOutputString($this->getResponseData(Status::OK, [], 'OK'));

        $this->runner->run();

        $this->assertSame(2, $this->worker->getRequestCount());

        $this->assertSame(1, gc_status()['runs']);
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithDefaults(): void
    {
        $this->expectOutputString($this->getResponseData(Status::OK, [], 'OK'));

        $this->runner->run();

        $this->assertSame(2, $this->worker->getRequestCount());
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithBootstrap(): void
    {
        $runner = $this->runner->withBootstrap('bootstrap-web');

        $this->expectOutputString("Bootstrapping{$this->getResponseData(Status::OK, [], 'OK')}");

        $runner->run();
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithCheckingEvents(): void
    {
        $runner = $this->runner->withCheckingEvents('events-fail');

        $this->expectException(InvalidListenerConfigurationException::class);

        $runner->run();
    }

    /**
     * @depends testCheckGarbageCollector
     */
    public function testRunWithCustomizedConfiguration(): void
    {
        $container = $this->createContainer();

        $runner = $this->runner
            ->withContainer($container)
            ->withConfig($this->createConfig())
            ->withTemporaryErrorHandler($this->createErrorHandler())
        ;

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
        $worker = new Psr7WorkerMock();
        $container = $this->createContainer();
        $runner = $this->runner
            ->withoutBootstrap()
            ->withoutCheckingEvents()
            ->withPsr7Worker($worker)
            ->withContainer($container)
        ;

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
        $worker = new Psr7WorkerMock(new RuntimeException('Some error'));
        $container = $this->createContainer();
        $runner = $this->runner
            ->withoutBootstrap()
            ->withoutCheckingEvents()
            ->withPsr7Worker($worker)
            ->withContainer($container)
        ;

        $this->expectOutputString($this->getResponseData(
            Status::BAD_REQUEST,
            [],
            json_encode([
                'error-message' => 'Some error',
                'request-method' => '',
                'request-uri' => '',
                'request-attribute-exists' => false,
            ]),
        ));

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
        $container = $this->createContainer(true);
        $runner = $this->runner->withContainer($container);

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
        $this->assertNotSame($this->runner, $this->runner->withBootstrap('bootstrap-web'));
        $this->assertNotSame($this->runner, $this->runner->withoutBootstrap());
        $this->assertNotSame($this->runner, $this->runner->withCheckingEvents('events-web'));
        $this->assertNotSame($this->runner, $this->runner->withoutCheckingEvents());
        $this->assertNotSame($this->runner, $this->runner->withConfig($this->createConfig()));
        $this->assertNotSame($this->runner, $this->runner->withContainer($this->createContainer()));
        $this->assertNotSame($this->runner, $this->runner->withTemporaryErrorHandler($this->createErrorHandler()));
        $this->assertNotSame($this->runner, $this->runner->withPsr7Worker(new Psr7WorkerMock()));
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
}
