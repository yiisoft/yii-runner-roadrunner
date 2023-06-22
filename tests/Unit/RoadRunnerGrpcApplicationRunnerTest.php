<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests\Unit;

use Mockery;
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
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    public function testSetServices(): void
    {
        $services = [
            EchoInterface::class => EchoService::class,
        ];
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
        $relay->shouldReceive('send')->once()->withArgs(function (Frame $frame) {
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
            rootPath: __DIR__ . '/Support'
        );
    }

    protected function createRelay(string $body, array $header): RelayInterface
    {
        $body = $this->packMessage($body);
        $header = json_encode($header);

        $relay = Mockery::mock(RelayInterface::class);
        $relay->shouldReceive('waitFrame')->once()->andReturn(
            new Frame($header . $body, [mb_strlen($header)])
        );

        $header = json_encode(['stop' => true]);
        $relay->shouldReceive('waitFrame')->once()->andReturn(
            new Frame($header, [mb_strlen($header)], Frame::CONTROL)
        );

        return $relay;
    }

    private function packMessage(string $message): string
    {
        return (new Message())
            ->setMsg($message)
            ->serializeToString();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
