<?php

declare(strict_types=1);

return [
    static function (): void {
        global $bootstrapWebCalled;
        $bootstrapWebCalled = true;
    },
];
