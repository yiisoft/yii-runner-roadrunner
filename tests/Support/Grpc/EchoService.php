<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Grpc;

use Service\EchoInterface;
use Service\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * Sample GRPC PHP service for server
 */
class EchoService implements EchoInterface
{
    public function Ping(ContextInterface $ctx, Message $in): Message
    {
        return (new Message())->setMsg('PONG');
    }
}
