<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests;

use Exception;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\StreamFactory;
use HttpSoft\Message\UploadedFileFactory;
use HttpSoft\Message\UriFactory;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\WorkerFactory;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\ThrowableRendererInterface;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Test\Support\Log\SimpleLogger;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Http\Handler\NotFoundHandler;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\PlainTextRendererMock;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\TemporalHostConnection;

use function json_encode;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createContainer(bool $throwException = false): Container
    {
        return new Container(ContainerConfig::create()->withDefinitions([
            EventDispatcherInterface::class => SimpleEventDispatcher::class,
            LoggerInterface::class => SimpleLogger::class,
            ResponseFactoryInterface::class => ResponseFactory::class,
            ServerRequestFactoryInterface::class => ServerRequestFactory::class,
            StreamFactoryInterface::class => StreamFactory::class,
            UriFactoryInterface::class => UriFactory::class,
            UploadedFileFactoryInterface::class => UploadedFileFactory::class,
            ThrowableRendererInterface::class => PlainTextRendererMock::class,

            /**
             * Temporal related definitions.
             */
            DataConverterInterface::class => fn () => DataConverter::createDefault(),
            RPCConnectionInterface::class => fn () => Goridge::create(),
            WorkerFactory::class => fn () => WorkerFactory::create(),
            HostConnectionInterface::class => fn () => new TemporalHostConnection(),

            ErrorCatcher::class => [
                'forceContentType()' => ['text/plain'],
                'withRenderer()' => ['text/plain', PlainTextRendererMock::class],
            ],

            ErrorHandler::class => [
                'reset' => function () {
                    /** @var ErrorHandler $this */
                    $this->debug(false);
                },
            ],

            Application::class => [
                '__construct()' => [
                    'dispatcher' => DynamicReference::to(
                        static function (ContainerInterface $container) use ($throwException) {
                            return $container
                                ->get(MiddlewareDispatcher::class)
                                ->withMiddlewares([
                                    static fn () => new class ($throwException) implements MiddlewareInterface {
                                        public function __construct(private bool $throwException)
                                        {
                                        }

                                        public function process(
                                            ServerRequestInterface $request,
                                            RequestHandlerInterface $handler
                                        ): ResponseInterface {
                                            if ($this->throwException) {
                                                throw new Exception('Failure');
                                            }

                                            return (new ResponseFactory())->createResponse();
                                        }
                                    },
                                ]);
                        },
                    ),
                    'fallbackHandler' => Reference::to(NotFoundHandler::class),
                ],
            ],
        ]));
    }

    protected function createResponse(int $status = Status::OK): ResponseInterface
    {
        return (new ResponseFactory())->createResponse($status);
    }

    protected function createServerRequest(string $method = Method::GET, string $uri = '/'): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $uri);
    }

    protected function getResponseData(int $status = Status::OK, array $headers = [], string $body = ''): string
    {
        return json_encode([
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
        ], JSON_THROW_ON_ERROR);
    }
}
