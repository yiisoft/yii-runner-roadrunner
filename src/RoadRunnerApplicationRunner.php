<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner;

use ErrorException;
use Exception;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Temporal\Worker\WorkerOptions;
use Temporal\WorkerFactory;
use Throwable;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Definitions\Exception\NotInstantiableException;
use Yiisoft\Di\NotFoundException;
use Yiisoft\Di\StateResetter;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Runner\ApplicationRunner;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

use function gc_collect_cycles;

/**
 * `RoadRunnerApplicationRunner` runs the Yii HTTP application using RoadRunner.
 */
final class RoadRunnerApplicationRunner extends ApplicationRunner
{
    private ?ErrorHandler $temporaryErrorHandler = null;
    private ?PSR7WorkerInterface $psr7Worker = null;
    private bool $isTemporalEnabled = false;

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
     */
    public function withTemporaryErrorHandler(ErrorHandler $temporaryErrorHandler): self
    {
        $new = clone $this;
        $new->temporaryErrorHandler = $temporaryErrorHandler;
        return $new;
    }

    /**
     * Returns a new instance with the specified PSR-7 worker instance {@see PSR7WorkerInterface}.
     *
     * @param PSR7WorkerInterface $worker The PSR-7 worker instance.
     */
    public function withPsr7Worker(PSR7WorkerInterface $worker): self
    {
        $new = clone $this;
        $new->psr7Worker = $worker;
        return $new;
    }

    /**
     * Returns a new instance with enabled temporal support.
     */
    public function withEnabledTemporal(bool $value): self
    {
        if (!$this->isTemporalSDKInstalled()) {
            throw new Exception('Temporal SDK is not installed. To install the SDK run `composer require temporal/sdk`.');
        }
        $new = clone $this;
        $new->isTemporalEnabled = $value;
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

        $config = $this->getConfig();
        $container = $this->getContainer($config, 'web');

        // Register error handler with real container-configured dependencies.
        /** @var ErrorHandler $actualErrorHandler */
        $actualErrorHandler = $container->get(ErrorHandler::class);
        $this->registerErrorHandler($actualErrorHandler, $temporaryErrorHandler);

        $this->runBootstrap($config, $container);
        $this->checkEvents($config, $container);

        $env = Environment::fromGlobals();

        if ($env->getMode() === Mode::MODE_TEMPORAL) {
            if (!$this->isTemporalEnabled) {
                throw new RuntimeException(
                    'Temporal support is disabled. You should call `withEnabledTemporal(true)` to enable temporal support.',
                );
            }
            $this->runTemporal($container);
            return;
        }
        if ($env->getMode() === Mode::MODE_HTTP) {
            $this->runRoadRunner($container);
            return;
        }

        // Leave support to run the application with built-in php server with: php -S 127.0.0.0:8080 public/index.php
        $runner = new HttpApplicationRunner($this->rootPath, $this->debug, $this->environment);
        $runner->run();
    }

    private function createTemporaryErrorHandler(): ErrorHandler
    {
        if ($this->temporaryErrorHandler !== null) {
            return $this->temporaryErrorHandler;
        }

        $logger = new Logger([new FileTarget("$this->rootPath/runtime/logs/app.log")]);
        return new ErrorHandler($logger, new HtmlRenderer());
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

    private function afterRespond(
        Application $application,
        ContainerInterface $container,
        ?ResponseInterface $response,
    ): void {
        $application->afterEmit($response);
        /** @psalm-suppress MixedMethodCall */
        $container
            ->get(StateResetter::class)
            ->reset(); // We should reset the state of such services every request.
        gc_collect_cycles();
    }

    private function runRoadRunner(ContainerInterface $container): void
    {
        $worker = new RoadRunnerWorker($container, $this->psr7Worker);

        /** @var Application $application */
        $application = $container->get(Application::class);
        $application->start();

        while (true) {
            $request = $worker->waitRequest();
            $response = null;

            if ($request === null) {
                break;
            }

            if ($request instanceof Throwable) {
                $response = $worker->respondWithError($request);
                $this->afterRespond($application, $container, $response);
                continue;
            }

            try {
                $response = $application->handle($request);
                $worker->respond($response);
            } catch (Throwable $t) {
                $response = $worker->respondWithError($t, $request);
            } finally {
                $this->afterRespond($application, $container, $response);
            }
        }

        $application->shutdown();
    }

    private function runTemporal(ContainerInterface $container): void
    {
        /**
         * @var WorkerFactory $factory
         */
        $factory = $container->get(WorkerFactory::class);
        /**
         * @var WorkerOptions $workerOptions
         */
        $workerOptions = $container->get(WorkerOptions::class);

        $worker = $factory->newWorker(
            'default',
            $workerOptions,
        );
        /**
         * @var object[] $workflows
         */
        $workflows = $container->get('tag@temporal.workflow');
        /**
         * @var object[] $activities
         */
        $activities = $container->get('tag@temporal.activity');

        foreach ($workflows as $workflow) {
            $worker->registerWorkflowTypes($workflow::class);
        }

        foreach ($activities as $activity) {
            $worker->registerActivity($activity::class);
        }

        $factory->run();
    }

    private function isTemporalSDKInstalled(): bool
    {
        return class_exists(WorkerFactory::class);
    }
}
