<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Temporal;

final class TemporalDeclarationProvider
{
    public function __construct(
        private readonly array $workflows,
        private readonly array $activities,
    ) {
    }

    public function getWorkflows(): array
    {
        return $this->workflows;
    }

    public function getActivities(): array
    {
        return $this->activities;
    }
}
