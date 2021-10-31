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
use Yiisoft\Yii\Runner\BootstrapRunner;
use Yiisoft\Yii\Runner\ConfigFactory;
use Yiisoft\Yii\Runner\RunnerInterface;
use Yiisoft\Yii\Runner\ThrowableHandler;
use Yiisoft\Yii\Web\Application;
use Yiisoft\Yii\Web\Exception\HeadersHaveBeenSentException;

use function dirname;
use function microtime;

final class RoadRunnerApplicationRunner implements RunnerInterface
{
    private bool $debug;
    private ?string $environment;

    public function __construct(bool $debug, ?string $environment)
    {
        $this->debug = $debug;
        $this->environment = $environment;
    }

    /**
     * @throws CircularReferenceException|ErrorException|HeadersHaveBeenSentException|InvalidConfigException
     * @throws NotFoundException|NotInstantiableException
     */
    public function run(): void
    {
        // Register temporary error handler to catch error while container is building.
        $errorHandler = $this->createTemporaryErrorHandler();
        $this->registerErrorHandler($errorHandler);

        $config = ConfigFactory::create($this->environment);

        $container = new Container(
            $config->get('web'),
            $config->get('providers-web'),
            [],
            $this->debug,
            $config->get('delegates-web')
        );

        // Register error handler with real container-configured dependencies.
        $this->registerErrorHandler($container->get(ErrorHandler::class), $errorHandler);

        // Run bootstrap
        $this->runBootstrap($container, $config->get('bootstrap-web'));

        $container = $container->get(ContainerInterface::class);

        if ($this->debug) {
            /** @psalm-suppress MixedMethodCall */
            $container->get(ListenerConfigurationChecker::class)->check($config->get('events-web'));
        }

        $worker = RoadRunner\Worker::create();
        $serverRequestFactory = $container->get(ServerRequestFactoryInterface::class);
        $streamFactory = $container->get(StreamFactoryInterface::class);
        $uploadsFactory = $container->get(UploadedFileFactoryInterface::class);
        $worker = new RoadRunner\Http\PSR7Worker($worker, $serverRequestFactory, $streamFactory, $uploadsFactory);

        /** @var Application */
        $application = $container->get(Application::class);
        $application->start();

        while ($request = $worker->waitRequest()) {
            $request = $request->withAttribute('applicationStartTime', microtime(true));
            $response = null;
            try {
                $response = $application->handle($request);
                $worker->respond($response);
            } catch (\Throwable $t) {
                $handler = new ThrowableHandler($t);
                /**
                 * @var ResponseInterface
                 * @psalm-suppress MixedMethodCall
                 */
                $response = $container->get(ErrorCatcher::class)->process($request, $handler);
                $worker->respond($response);
            } finally {
                $application->afterEmit($response ?? null);
                $container->get(StateResetter::class)->reset(); // We should reset the state of such services every request.
                gc_collect_cycles();
            }
        }

        $application->shutdown();
    }

    private function createTemporaryErrorHandler(): ErrorHandler
    {
        $logger = new Logger([new FileTarget(dirname(__DIR__) . '/runtime/logs/app.log')]);
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

    private function runBootstrap(Container $container, array $bootstrapList): void
    {
        (new BootstrapRunner($container, $bootstrapList))->run();
    }
}
