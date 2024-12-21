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
use ReflectionClass;
use RuntimeException;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\WorkerFactoryInterface;
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
use Yiisoft\Yii\Runner\RoadRunner\Temporal\TemporalDeclarationProvider;

use function gc_collect_cycles;
use function interface_exists;

/**
 * `RoadRunnerHttpApplicationRunner` runs the Yii HTTP application using RoadRunner.
 */
final class RoadRunnerHttpApplicationRunner extends ApplicationRunner
{
    private ?ErrorHandler $temporaryErrorHandler = null;
    private ?PSR7WorkerInterface $psr7Worker = null;
    private bool $isTemporalEnabled = false;

    /**
     * @param string $rootPath The absolute path to the project root.
     * @param bool $debug Whether the debug mode is enabled.
     * @param bool $checkEvents Whether to check events' configuration.
     * @param string|null $environment The environment name.
     * @param string $bootstrapGroup The bootstrap configuration group name.
     * @param string $eventsGroup The events' configuration group name.
     * @param string $diGroup The container definitions' configuration group name.
     * @param string $diProvidersGroup The container providers' configuration group name.
     * @param string $diDelegatesGroup The container delegates' configuration group name.
     * @param string $diTagsGroup The container tags' configuration group name.
     * @param string $paramsGroup The configuration parameters group name.
     * @param array $nestedParamsGroups Configuration group names that are included into configuration parameters group.
     * This is needed for recursive merging of parameters.
     * @param array $nestedEventsGroups Configuration group names that are included into events' configuration group.
     * This is needed for reverse and recursive merge of events' configurations.
     *
     * @psalm-param list<string> $nestedParamsGroups
     * @psalm-param list<string> $nestedEventsGroups
     */
    public function __construct(
        string $rootPath,
        bool $debug = false,
        bool $checkEvents = false,
        ?string $environment = null,
        string $bootstrapGroup = 'bootstrap-web',
        string $eventsGroup = 'events-web',
        string $diGroup = 'di-web',
        string $diProvidersGroup = 'di-providers-web',
        string $diDelegatesGroup = 'di-delegates-web',
        string $diTagsGroup = 'di-tags-web',
        string $paramsGroup = 'params-web',
        array $nestedParamsGroups = ['params'],
        array $nestedEventsGroups = ['events'],
    ) {
        parent::__construct(
            $rootPath,
            $debug,
            $checkEvents,
            $environment,
            $bootstrapGroup,
            $eventsGroup,
            $diGroup,
            $diProvidersGroup,
            $diDelegatesGroup,
            $diTagsGroup,
            $paramsGroup,
            $nestedParamsGroups,
            $nestedEventsGroups,
        );
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

        $container = $this->getContainer();

        // Register error handler with real container-configured dependencies.
        /** @var ErrorHandler $actualErrorHandler */
        $actualErrorHandler = $container->get(ErrorHandler::class);
        $this->registerErrorHandler($actualErrorHandler, $temporaryErrorHandler);

        $this->runBootstrap();
        $this->checkEvents();

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

        throw new RuntimeException(sprintf(
            'Unsupported mode "%s", modes are supported: "%s".',
            $env->getMode(),
            implode('", "', [Mode::MODE_HTTP, Mode::MODE_TEMPORAL]),
        ));
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
        $worker = new RoadRunnerHttpWorker($container, $this->psr7Worker);

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
        /** @var TemporalDeclarationProvider $temporalDeclarationProvider */
        $temporalDeclarationProvider = $container->get(TemporalDeclarationProvider::class);
        /** @var HostConnectionInterface $host */
        $host = $container->get(HostConnectionInterface::class);

        /** @var WorkerFactoryInterface $factory */
        $factory = $container->get(WorkerFactoryInterface::class);
        $worker = $factory->newWorker('default');

        $workflows = $temporalDeclarationProvider->getWorkflows();
        $activities = $temporalDeclarationProvider->getActivities();

        $worker->registerWorkflowTypes(...$workflows);

        /** @psalm-suppress MixedReturnStatement,MixedInferredReturnType */
        $activityFactory = static fn (ReflectionClass $class): object => $container->get($class->getName());
        $activityFinalizer = static function () use ($container): void {
            /** @psalm-suppress MixedMethodCall */
            $container
                ->get(StateResetter::class)
                ->reset(); // We should reset the state of such services every request.
            gc_collect_cycles();
        };

        foreach ($activities as $activity) {
            $worker->registerActivity($activity, $activityFactory);
        }
        $worker->registerActivityFinalizer($activityFinalizer);

        $factory->run($host);
    }

    private function isTemporalSDKInstalled(): bool
    {
        return interface_exists(WorkerFactoryInterface::class);
    }
}
