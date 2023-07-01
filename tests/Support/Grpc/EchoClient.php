<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Grpc;

use Grpc\BaseStub;
use Grpc\UnaryCall;
use Service\Message;

class EchoClient extends BaseStub
{
    public function Ping(Message $message, $metadata = [], $options = []): UnaryCall
    {
        return $this->_simpleRequest(
            '/service.Echo/Ping',
            $message,
            [Message::class, 'decode'],
            $metadata,
            $options
        );
    }
}
