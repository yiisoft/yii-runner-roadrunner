<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\WorkerInterface;
use Throwable;

use function json_encode;

final class Psr7WorkerMock implements PSR7WorkerInterface
{
    private ServerRequestInterface|Throwable|null $request;
    private int $requestCount = 0;

    public function __construct(ServerRequestInterface|Throwable $request = null)
    {
        $this->request = $request;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function waitRequest(): ?ServerRequestInterface
    {
        $request = $this->request;
        $this->request = null;
        $this->requestCount++;

        if ($request instanceof Throwable) {
            throw $request;
        }

        return $request;
    }

    public function respond(ResponseInterface $response): void
    {
        echo json_encode([
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
        ]);
    }

    public function getWorker(): WorkerInterface
    {
        return new class() implements WorkerInterface {
            public function waitPayload(): ?Payload
            {
                return new Payload(null);
            }

            public function respond(Payload $payload): void
            {
            }

            public function error(string $error): void
            {
            }

            public function stop(): void
            {
            }
        };
    }
}
