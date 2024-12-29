<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Temporal;

final class TemporalDeclarationProvider
{
    public function __construct(
        /** @var class-string[] $workflows */
        private readonly array $workflows,
        /** @var class-string[] $activities */
        private readonly array $activities,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function getWorkflows(): array
    {
        return $this->workflows;
    }

    /**
     * @return class-string[]
     */
    public function getActivities(): array
    {
        return $this->activities;
    }
}
