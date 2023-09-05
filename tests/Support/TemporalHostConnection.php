<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests\Support;

use Temporal\Worker\Transport\CommandBatch;
use Temporal\Worker\Transport\HostConnectionInterface;

final class TemporalHostConnection implements HostConnectionInterface
{
    private array $batches = [];

    public function addCommandBatch(CommandBatch $batch): void
    {
        $this->batches[] = $batch;
    }

    public function waitBatch(): ?CommandBatch
    {
        if ($this->batches === []) {
            return null;
        }
        return array_shift($this->batches);
    }

    public function send(string $frame): void
    {
    }

    public function error(\Throwable $error): void
    {
    }
}
