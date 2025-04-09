<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Service\EchoInterface;
use Service\Message;
use Spiral\Goridge\Frame;
use Spiral\Goridge\RelayInterface;
use Spiral\RoadRunner\Worker;
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerGrpcApplicationRunner;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Grpc\EchoService;

final class RoadRunnerGrpcApplicationRunnerTest extends TestCase
{
    public function testSetServices(): void
    {
        $services = [EchoInterface::class];
        $runner = $this->createRunner();

        self::assertEmpty($runner->getServices());

        $runner->setServices($services);

        self::assertNotEmpty($runner->getServices());
        self::assertEquals($services, $runner->getServices());
    }

    public function testRun(): void
    {
        $relay = $this->createRelay(
            'PING',
            [
                'service' => 'service.Echo',
                'method' => 'Ping',
                'context' => [],
            ]
        );
        $relay->expects($this->once())->method('send')->willReturnCallback(function (Frame $frame) {
            return $frame->payload === '{}' . $this->packMessage('PONG');
        });

        $instance = $this
            ->createRunner()
            ->setServices([
                EchoInterface::class => EchoService::class,
            ]);
        $newInstance = $instance->withWorker(
            new Worker($relay)
        );
        $newInstance->run();
        ob_end_clean();
    }

    private function createRunner(): RoadRunnerGrpcApplicationRunner
    {
        return new RoadRunnerGrpcApplicationRunner(
            rootPath: dirname(__DIR__) . '/Support'
        );
    }

    protected function createRelay(string $body, array $header): RelayInterface|\PHPUnit\Framework\MockObject\MockObject
    {
        $body = $this->packMessage($body);
        $header1 = json_encode($header, JSON_THROW_ON_ERROR);
        $header2 = json_encode(['stop' => true]);

        $relay = $this->createMock(RelayInterface::class);
        $relay->expects($this->exactly(2))->method('waitFrame')->willReturnOnConsecutiveCalls(
            new Frame($header1 . $body, [mb_strlen($header1)]),
            new Frame($header2, [mb_strlen($header2)], Frame::CONTROL)
        );


        return $relay;
    }

    private function packMessage(string $message): string
    {
        return (new Message())
            ->setMsg($message)
            ->serializeToString();
    }
}
