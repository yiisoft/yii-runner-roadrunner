# Upgrading Instructions for Yii RoadRunner Runner

This file contains the upgrade notes. These notes highlight changes that could break your
application when you upgrade the package from one version to another.

> **Important!** The following upgrading instructions are cumulative. That is, if you want
> to upgrade from version A to version C and there is version B between A and C, you need
> to following the instructions for both A and B.

## Upgrade from 3.x

- Increased minimum PHP version to 8.2 and RoadRunner to 3.0. RoadRunner 2023.x–2025.x is not supported.
- The `spiral/roadrunner-http`, `spiral/roadrunner-worker`, `spiral/roadrunner-grpc` and `spiral/roadrunner-cli`
  dependencies are replaced with `roadrunner/http`, `roadrunner/worker`, `roadrunner/grpc` and `roadrunner/cli`.
  The PHP namespaces (`Spiral\RoadRunner\...`) are the same. If your `composer.json` requires the old package names,
  replace them with the new ones.
- Get the RoadRunner 3 binary with `./vendor/bin/rr get-binary`. You can remove the `spiral/roadrunner` package
  if your `composer.json` still requires it.
- Update `.rr.yaml` according to the [RoadRunner 3 migration guide](https://docs.roadrunner.dev/docs/general/v3-migration):
  - `http.middleware` now runs from left to right; reverse the list to keep its previous order;
  - `logs.file_logger_options` is removed; list the log file in `logs.output` and use an external tool for rotation.
- The Temporal integration requires a `temporal/sdk` version that supports RoadRunner 3.

## Upgrade from 3.1.0

- Added error handler at the run of the application `RoadRunnerGrpcApplicationRunner`
- DI container is now used for getting service classes
- Added calls to run bootstrap and checking events in the run method of `gRPC` application

## Upgrade from 2.x

- `RoadRunnerApplicationRunner` has been renamed to `RoadRunnerHttpApplicationRunner`
- Increased minimum PHP version to 8.1 and RoadRunner to 2023.1.*
