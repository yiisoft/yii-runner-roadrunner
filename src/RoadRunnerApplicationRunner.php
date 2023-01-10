<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner;

use ErrorException;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
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

use function gc_collect_cycles;

/**
 * `RoadRunnerApplicationRunner` runs the Yii HTTP application using RoadRunner.
 */
final class RoadRunnerApplicationRunner extends ApplicationRunner
{
    private ?ErrorHandler $temporaryErrorHandler = null;
    private ?PSR7WorkerInterface $psr7Worker = null;

    /**
     * @param string $rootPath The absolute path to the project root.
     * @param bool $debug Whether the debug mode is enabled.
     * @param string|null $environment The environment name.
     */
    public function __construct(string $rootPath, bool $debug, ?string $environment)
    {
        parent::__construct($rootPath, $debug, 'web', $environment);
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

        $config = $this->getConfig();
        $this->runBootstrap($config, $container);
        $this->checkEvents($config, $container);

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
}
