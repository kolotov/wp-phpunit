# Project Agent Policy

## Supported runtime

This repository is intentionally modern-only.

- Support PHP 8.5 only.
- Support PHPUnit 13 only.
- Support the latest WordPress 7.x line only.
- The `phpunit-13` branch is the only migration target for this work.

## Explicitly unsupported compatibility

Do not preserve, restore, add, or emulate backward compatibility for any of the following:

- PHP 8.4 or earlier.
- PHPUnit 12 or earlier.
- WordPress 6.x or earlier.
- `yoast/phpunit-polyfills` or any API surface inherited from it.
- `PHPUnit_Framework_*`, `PHPUnit_Util_*`, or other removed PHPUnit aliases/internals.
- Legacy PHPUnit DocBlock metadata when PHPUnit 13 attributes are available.
- Compatibility adapters spanning multiple PHPUnit generations.
- Historical callback shapes, helper methods, lifecycle behavior, signatures, aliases, or shims solely because older PHPUnit/PHP/WordPress versions exposed them.

Do not add a compatibility shim unless it is independently required by the current PHP 8.5 + PHPUnit 13 + latest WordPress 7.x contract.

## Regression review baseline

When reviewing regressions, weakening, or migration completeness:

1. Use PHP 8.5, PHPUnit 13, and the latest WordPress 7.x behavior as the compatibility baseline.
2. Do not treat older PHPUnit, Yoast PHPUnit Polyfills, older PHP versions, or older WordPress branches as compatibility authorities.
3. Prefer native PHPUnit 13 public APIs, attributes, and lifecycle methods.
4. Preserve WordPress test-harness behavior that is still required by the current WordPress 7.x test suite and current plugin/theme integration use cases.
5. Reject changes that weaken assertions, cleanup, isolation, coverage precision, or test discovery under the supported modern stack.
6. A green test run is not sufficient by itself; manually inspect lifecycle, global state, hook cleanup, factories, error/deprecation handling, and public harness contracts that are relevant to the supported stack.

## Repository relationship

This repository is paired with `kolotov/wordpress-develop` on branch `phpunit-13`.
The paired WordPress repository must consume this package from `kolotov/wp-phpunit` using `dev-phpunit-13` during migration development and must move to the final public branch/tag before final merge.

The full WordPress PHPUnit matrix is owned by `wordpress-develop`; do not duplicate it in this repository. This repository owns only package validation plus the shared canonical runtime definition consumed by both repositories.

## Local validation environment

- Run all local package tests and quality validation inside Podman containers.
- `containers/phpunit13/Containerfile` and `containers/phpunit13/runtime.env` are the single canonical runtime definition for both repositories.
- `wp-phpunit` local Podman and GitHub Actions must both execute package validation through `tools/run-containerized.sh`; local callers use `tools/run-local-podman.sh` and CI uses Docker as the engine.
- GitHub Actions must publish the package-tested runtime image under the exact provider commit SHA. Published `wordpress-develop` validation must consume that image, MariaDB, and Memcached by immutable digest; it must not rebuild the provider Containerfile independently.
- `wordpress-develop/composer.lock` and its runtime image lock must name the same provider commit. Do not keep a copied Containerfile in `wordpress-develop`.
- Unpublished paired validation must use one `WP_PHPUNIT_SOURCE_HOST` checkout for both runtime definition and Composer path repository so mixed provider states are impossible.
- Treat `containers/phpunit13/runtime.env` as the sole source of runtime and service versions; advance versions there deliberately before updating any dependent lock or documentation.
- Do not treat host PHP, Composer, Node, npm, PHPUnit, MariaDB, Memcached, Make targets, or direct host test commands as valid validation.
- Direct Composer validation scripts are internal execution commands and must run only inside the canonical container.
- Do not suppress, hide, filter, or downgrade warnings, audit findings, notices, or failures in validation commands. Fix root causes or classify them explicitly in separate gates, but preserve the diagnostics.

## Git safety

- Do not push unless the user explicitly authorizes the push.
- Never force-push unless the user explicitly authorizes a force push.
- Preserve unrelated working-tree changes.
- Stage and commit only reviewed logical units.
