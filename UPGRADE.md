# Upgrading Instructions for Yii RoadRunner Runner

This file contains the upgrade notes. These notes highlight changes that could break your
application when you upgrade the package from one version to another.

> **Important!** The following upgrading instructions are cumulative. That is, if you want
> to upgrade from version A to version C and there is version B between A and C, you need
> to following the instructions for both A and B.

## Upgrade from 3.x

- If you defined custom `yiisoft/error-handler`'s `ErrorCatcher` DI configuration replace it with `ThrowableResponseFactory`.

## Upgrade from 2.x

- `RoadRunnerApplicationRunner` has been renamed to `RoadRunnerHttpApplicationRunner`
- Increased minimum PHP version to 8.1 and RoadRunner to 2023.1.*
