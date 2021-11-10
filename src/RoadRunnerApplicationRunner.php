<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner;

use Spiral\RoadRunner;
use ErrorException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Throwable;
use Yiisoft\Config\Config;
use Yiisoft\Di\Container;
use Yiisoft\Di\StateResetter;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Renderer\PlainTextRenderer;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Definitions\Exception\NotFoundException;
use Yiisoft\Definitions\Exception\NotInstantiableException;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Yii\Event\ListenerConfigurationChecker;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Runner\BootstrapRunner;
use Yiisoft\Yii\Runner\ConfigFactory;
use Yiisoft\Yii\Runner\RunnerInterface;
use Yiisoft\Yii\Runner\ThrowableHandler;

use function gc_collect_cycles;
use function microtime;

final class RoadRunnerApplicationRunner implements RunnerInterface
{
    private bool $debug;
    private string $rootPath;
    private ?string $environment;
    private ?Config $config = null;
    private ?ContainerInterface $container = null;
    private ?ErrorHandler $temporaryErrorHandler = null;
    private ?string $bootstrapGroup = 'bootstrap-web';
    private ?string $eventsGroup = 'events-web';

    public function __construct(string $rootPath, bool $debug, ?string $environment)
    {
        $this->rootPath = $rootPath;
        $this->debug = $debug;
        $this->environment = $environment;
    }

    public function withBootstrap(string $bootstrapGroup): self
    {
        $new = clone $this;
        $new->bootstrapGroup = $bootstrapGroup;
        return $new;
    }

    public function withoutBootstrap(): self
    {
        $new = clone $this;
        $new->bootstrapGroup = null;
        return $new;
    }

    public function withEvents(string $eventsGroup): self
    {
        $new = clone $this;
        $new->eventsGroup = $eventsGroup;
        return $new;
    }

    public function withoutEvents(): self
    {
        $new = clone $this;
        $new->eventsGroup = null;
        return $new;
    }

    public function withConfig(Config $config): self
    {
        $new = clone $this;
        $new->config = $config;
        return $new;
    }

    public function withContainer(ContainerInterface $container): self
    {
        $new = clone $this;
        $new->container = $container;
        return $new;
    }

    public function withTemporaryErrorHandler(ErrorHandler $temporaryErrorHandler): self
    {
        $new = clone $this;
        $new->temporaryErrorHandler = $temporaryErrorHandler;
        return $new;
    }

    /**
     * @throws CircularReferenceException|ErrorException|InvalidConfigException
     * @throws NotFoundException|NotInstantiableException
     */
    public function run(): void
    {
        // Register temporary error handler to catch error while container is building.
        $temporaryErrorHandler = $this->createTemporaryErrorHandler();
        $this->registerErrorHandler($temporaryErrorHandler);

        $config = $this->config ?? ConfigFactory::create($this->rootPath, $this->environment);

        $container = $this->container ?? new Container(
            $config->get('web'),
            $config->get('providers-web'),
            [],
            $this->debug,
            $config->get('delegates-web')
        );

        // Register error handler with real container-configured dependencies.
        /** @var ErrorHandler $actualErrorHandler */
        $actualErrorHandler = $container->get(ErrorHandler::class);
        $this->registerErrorHandler($actualErrorHandler, $temporaryErrorHandler);

        if ($container instanceof Container) {
            $container = $container->get(ContainerInterface::class);
        }

        // Run bootstrap
        if ($this->bootstrapGroup !== null) {
            $this->runBootstrap($container, $config->get($this->bootstrapGroup));
        }

        if ($this->debug && $this->eventsGroup !== null) {
            /** @psalm-suppress MixedMethodCall */
            $container->get(ListenerConfigurationChecker::class)->check($config->get($this->eventsGroup));
        }

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

    private function runBootstrap(ContainerInterface $container, array $bootstrapList): void
    {
        (new BootstrapRunner($container, $bootstrapList))->run();
    }
}
