<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan(__DIR__ . '/config', isDev: false)
    ->addPathToScan(__DIR__ . '/src', isDev: false)
    ->addPathToScan(__DIR__ . '/tests', isDev: true)
    // `temporal/sdk` is an optional integration.
    ->ignoreErrorsOnPackages(['temporal/sdk'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // `spiral/roadrunner-cli` only ships the `rr` binary (no PHP symbols to detect); it is used via
    // `vendor/bin/rr` (see README.md / CI workflows), not via any class reference.
    ->ignoreErrorsOnPackages(['spiral/roadrunner-cli'], [ErrorType::UNUSED_DEPENDENCY]);

if (!extension_loaded('grpc')) {
    $config->ignoreUnknownClasses(['Grpc\ChannelCredentials']);
}

return $config;
