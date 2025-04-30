<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\RoadRunner\Tests\Support;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yiisoft\ErrorHandler\ErrorData;
use Yiisoft\ErrorHandler\ThrowableRendererInterface;

use function json_encode;

final class PlainTextRendererMock implements ThrowableRendererInterface
{
    public function render(Throwable $t, ?ServerRequestInterface $request = null): ErrorData
    {
        return $this->renderVerbose($t, $request);
    }

    public function renderVerbose(Throwable $t, ?ServerRequestInterface $request = null): ErrorData
    {
        return new ErrorData(json_encode([
            'error-message' => $t->getMessage(),
            'request-method' => (string) $request?->getMethod(),
            'request-uri' => (string) $request?->getUri(),
            'request-attribute-exists' => (bool) $request?->getAttribute('applicationStartTime'),
        ], JSON_THROW_ON_ERROR));
    }
}
