<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests;

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpWorker;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Psr7WorkerMock;

use function json_encode;
use function microtime;

final class RoadRunnerHttpWorkerTest extends TestCase
{
    public function testRespond(): void
    {
        $this->expectOutputString($this->getResponseData());

        $worker = new RoadRunnerHttpWorker($this->createContainer(), new Psr7WorkerMock());
        $worker->respond($this->createResponse());
    }

    public function testRespondWithErrorWithPassingRequest(): void
    {
        $errorMessage = 'Some error';
        $throwable = new RuntimeException($errorMessage);
        $headers = ['Content-Type' => ['text/plain']];
        $body = json_encode([
            'error-message' => $errorMessage,
            'request-method' => Method::GET,
            'request-uri' => '/',
            'request-attribute-exists' => false,
        ]);

        $worker = new RoadRunnerHttpWorker($this->createContainer(), new Psr7WorkerMock());

        $this->expectOutputString($this->getResponseData(Status::INTERNAL_SERVER_ERROR, $headers, $body));

        $response = $worker->respondWithError($throwable, $this->createServerRequest());

        $this->assertSame(Status::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame(Status::TEXTS[Status::INTERNAL_SERVER_ERROR], $response->getReasonPhrase());
        $this->assertSame($headers, $response->getHeaders());
        $this->assertSame($body, (string) $response->getBody());
    }

    public function testRespondWithErrorWithoutPassingRequest(): void
    {
        $headers = [];
        $body = json_encode([
            'error-message' => 'Some error',
            'request-method' => '',
            'request-uri' => '',
            'request-attribute-exists' => false,
        ]);

        $worker = new RoadRunnerHttpWorker($this->createContainer(), new Psr7WorkerMock());

        $this->expectOutputString($this->getResponseData(Status::BAD_REQUEST, $headers, $body));

        $response = $worker->respondWithError(new RuntimeException('Some error'));

        $this->assertSame(Status::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(Status::TEXTS[Status::BAD_REQUEST], $response->getReasonPhrase());
        $this->assertSame($headers, $response->getHeaders());
        $this->assertSame($body, (string) $response->getBody());
    }

    public function testWaitRequestWithNullReturn(): void
    {
        $worker = new RoadRunnerHttpWorker($this->createContainer(), new Psr7WorkerMock());

        $this->assertNull($worker->waitRequest());
    }

    public function testWaitRequestWithRequestInstanceReturn(): void
    {
        $worker = new RoadRunnerHttpWorker($this->createContainer(), new Psr7WorkerMock($this->createServerRequest()));
        $request = $worker->waitRequest();

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertTrue($request->getAttribute('applicationStartTime') <= microtime(true));
    }

    public function testWaitRequestWithThrowableInstanceReturn(): void
    {
        $throwable = new RuntimeException();
        $worker = new RoadRunnerHttpWorker($this->createContainer(), new Psr7WorkerMock($throwable));
        $request = $worker->waitRequest();

        $this->assertInstanceOf(RuntimeException::class, $request);
        $this->assertSame($throwable, $request);
    }
}
