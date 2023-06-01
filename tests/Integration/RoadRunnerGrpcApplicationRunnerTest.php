<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests\Integration;

use Exception;
use Grpc\ChannelCredentials;
use PHPUnit\Framework\TestCase;
use Service\Message;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Grpc\EchoClient;
use const Grpc\STATUS_OK;

final class RoadRunnerGrpcApplicationRunnerTest extends TestCase
{
    /**
     * Testing a simple request over an insecure connection
     *
     * @throws Exception
     *
     * @return void
     */
    public function testSimpleInsecureRequest(): void
    {
        $client = new EchoClient('0.0.0.0:9001', [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);

        $message = new Message();
        $message->setMsg('PING');

        /** @var Message $response */
        [$response, $status] = $client->Ping($message)->wait();

        $this->assertSame(STATUS_OK, $status->code);
        $this->assertSame('PONG', $response->getMsg());
    }
}
