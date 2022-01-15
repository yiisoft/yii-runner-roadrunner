<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner;

use ErrorException;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\RoadRunner;
use Throwable;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Definitions\Exception\NotInstantiableException;
use Yiisoft\Di\Container;
use Yiisoft\Di\NotFoundException;
use Yiisoft\Di\StateResetter;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Renderer\PlainTextRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Http\Handler\ThrowableHandler;
use Yiisoft\Yii\Runner\ApplicationRunner;

use function gc_collect_cycles;
use function microtime;

/**
 * `RoadRunnerApplicationRunner` runs the Yii HTTP application for RoadRunner.
 */
final class RoadRunnerApplicationRunner extends ApplicationRunner
{
    private ?ErrorHandler $temporaryErrorHandler = null;

    /**
     * @param string $rootPath The absolute path to the project root.
     * @param bool $debug Whether the debug mode is enabled.
     * @param string|null $environment The environment name.
     */
    public function __construct(string $rootPath, bool $debug, ?string $environment)
    {
        parent::__construct($rootPath, $debug, $environment);
        $this->bootstrapGroup = 'bootstrap-web';
        $this->eventsGroup = 'events-web';
    }

    /**
     * Returns a new instance with the specified temporary error handler instance {@see ErrorHandler}.
     *
     * A temporary error handler is needed to handle the creation of configuration and container instances,
     * then the error handler configured in your application configuration will be used.
     *
     * @param ErrorHandler $temporaryErrorHandler The temporary error handler instance.
     *
     * @return self
     */
    public function withTemporaryErrorHandler(ErrorHandler $temporaryErrorHandler): self
    {
        $new = clone $this;
        $new->temporaryErrorHandler = $temporaryErrorHandler;
        return $new;
    }

    /**
     * {@inheritDoc}
     *
     * @throws CircularReferenceException|ErrorException|InvalidConfigException|JsonException
     * @throws ContainerExceptionInterface|NotFoundException|NotFoundExceptionInterface|NotInstantiableException
     */
    public function run(): void
    {
        // Register temporary error handler to catch error while container is building.
        $temporaryErrorHandler = $this->createTemporaryErrorHandler();
        $this->registerErrorHandler($temporaryErrorHandler);

        $config = $this->config ?? $this->createConfig();
        $container = $this->container ?? $this->createContainer($config, 'web');

        // Register error handler with real container-configured dependencies.
        /** @var ErrorHandler $actualErrorHandler */
        $actualErrorHandler = $container->get(ErrorHandler::class);
        $this->registerErrorHandler($actualErrorHandler, $temporaryErrorHandler);

        if ($container instanceof Container) {
            $container = $container->get(ContainerInterface::class);
        }

        $this->runBootstrap($config, $container);
        $this->checkEvents($config, $container);

        $worker = RoadRunner\Worker::create();
        /** @var ServerRequestFactoryInterface $serverRequestFactory */
        $serverRequestFactory = $container->get(ServerRequestFactoryInterface::class);
        /** @var StreamFactoryInterface $streamFactory */
        $streamFactory = $container->get(StreamFactoryInterface::class);
        /** @var UploadedFileFactoryInterface $uploadsFactory */
        $uploadsFactory = $container->get(UploadedFileFactoryInterface::class);
        $worker = new RoadRunner\Http\PSR7Worker($worker, $serverRequestFactory, $streamFactory, $uploadsFactory);

        /** @var Application $application */
        $application = $container->get(Application::class);
        $application->start();

        while ($request = $worker->waitRequest()) {
            $request = $request->withAttribute('applicationStartTime', microtime(true));
            $response = null;
            try {
                $response = $application->handle($request);
                $worker->respond($response);
            } catch (Throwable $t) {
                $handler = new ThrowableHandler($t);
                /**
                 * @var ResponseInterface
                 * @psalm-suppress MixedMethodCall
                 */
                $response = $container->get(ErrorCatcher::class)->process($request, $handler);
                $worker->respond($response);
            } finally {
                $application->afterEmit($response ?? null);
                /** @psalm-suppress MixedMethodCall */
                $container->get(StateResetter::class)->reset(); // We should reset the state of such services every request.
                gc_collect_cycles();
            }
        }

        $application->shutdown();
    }

    private function createTemporaryErrorHandler(): ErrorHandler
    {
        if ($this->temporaryErrorHandler !== null) {
            return $this->temporaryErrorHandler;
        }

        $logger = new Logger([new FileTarget("$this->rootPath/runtime/logs/app.log")]);
        return new ErrorHandler($logger, new PlainTextRenderer());
    }

    /**
     * @throws ErrorException
     */
    private function registerErrorHandler(ErrorHandler $registered, ErrorHandler $unregistered = null): void
    {
        $unregistered?->unregister();

        if ($this->debug) {
            $registered->debug();
        }

        $registered->register();
    }
}
