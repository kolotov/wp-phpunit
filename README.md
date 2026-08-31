# WP PHPUnit for PHPUnit 13

A WordPress integration-test harness for PHP 8.5 and PHPUnit 13.

This is an independent, unofficial fork of the WordPress Core PHPUnit library.
It was created for personal use and is published for anyone who needs a modern
WordPress test harness without the historical PHPUnit compatibility layers.

## Runtime requirements

This project intentionally targets a **current-only** runtime. The versions below
are the active project baseline, not the lower bounds of a backward-compatible
version range:

- PHP 8.5
- PHPUnit 13
- current WordPress 7.x line
- current service/runtime versions declared in `containers/phpunit13/runtime.env`
  (including the MariaDB baseline used by CI)

PHP 8.4 or earlier and PHPUnit 12 or earlier are intentionally unsupported.
When this project advances its declared baseline, compatibility with the previous
baseline is not retained automatically.

## Installation

Add this repository to the consuming project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kolotov/wp-phpunit"
        }
    ],
    "require-dev": {
        "wp-phpunit/wp-phpunit": "dev-phpunit-13"
    }
}
```

Pin a commit or use a tagged release for reproducible builds.

## Scope

The repository provides the WordPress bootstrap, fixtures, factories, and base
test cases needed by plugin and theme integration tests. Compatibility shims
for historical PHPUnit releases are outside its scope.

The `wp-phpunit` CI validates this package only: its own tests, compatibility
checks, formatting, and Rector rules. The full WordPress PHPUnit
matrix belongs to the paired `wordpress-develop` repository and is not duplicated
here.

## Shared canonical runtime

This repository owns the single canonical PHPUnit 13 runtime definition:

- `containers/phpunit13/Containerfile`
- `containers/phpunit13/runtime.env`

The image is based on Ubuntu 24.04. Exact runtime versions are declared in
`containers/phpunit13/runtime.env`, including PHP, MariaDB, Memcached, Imagick,
ImageMagick, Ghostscript, Xdebug, timezonedb, PCOV, Node, npm, and Composer. The
Containerfile asserts those versions during the build so drift fails visibly.

Both repositories use this same definition without rebuilding published runtime
state independently:

- `wp-phpunit` builds the runtime locally with Podman. GitHub Actions builds it
  once, runs package quality in that exact image, and publishes the tested image
  to `ghcr.io/kolotov/wp-phpunit-runtime` under the provider commit SHA.
- Published `wordpress-develop` validation requires `composer.lock` and
  `tools/phpunit13/runtime-image.lock` to name the same `wp-phpunit` provider
  commit. The runtime, MariaDB, and Memcached images are consumed by immutable
  `@sha256:` digests.
- Unpublished paired changes use one explicit `WP_PHPUNIT_SOURCE_HOST` path. That
  single checkout supplies both the runtime definition and the Composer path
  repository, so mixed provider/runtime states are rejected by construction.

The GHCR runtime package must be readable by the paired WordPress repository
(public package visibility is the simplest community setup). Runtime version
changes are made here first, validated in both repositories, published, and only
then locked by `wordpress-develop` after Composer refresh.

## Local package validation

Local validation runs exclusively through Podman:

```shell
./tools/run-local-podman.sh quality
```

GitHub Actions invokes the same `tools/run-containerized.sh` runner with Docker.
Host PHP or Composer execution is not considered valid project validation.
Warnings, notices, audit findings, and failures must not be suppressed to make
validation output look cleaner.

## Relationship to WordPress Core

This repository is paired with
[`kolotov/wordpress-develop`](https://github.com/kolotov/wordpress-develop) on
branch `phpunit-13`. During migration development, unpublished paired changes may
be tested through an explicit Composer path repository. Before final merge,
`wordpress-develop` must consume the published `wp-phpunit` branch/tag through
normal Composer VCS resolution and regenerate `composer.lock` with Composer.

## Support policy

The modern-only boundary is deliberate architecture, not an unfinished migration.
The project removes historical compatibility tails so the harness can use current
PHP, PHPUnit, and WordPress APIs directly, keep one reproducible runtime, and avoid
maintaining branches, polyfills, aliases, and test paths for versions this fork
does not run.

The following legacy compatibility mechanisms are outside this project's scope:

- PHPUnit 12 and earlier
- PHP versions earlier than 8.5
- unsupported older WordPress generations
- legacy database/runtime variants outside the declared current baseline
- `yoast/phpunit-polyfills`
- `PHPUnit_Framework_*` and `PHPUnit_Util_*` aliases
- DocBlock-based PHPUnit metadata where PHPUnit 13 attributes are available
- compatibility adapters spanning multiple PHPUnit generations

Failures that exist only on an unsupported PHP, PHPUnit, WordPress, database, or
service version are **not regressions for this project**. Do not restore removed
compatibility shims, version branches, aliases, polyfills, skipped legacy paths,
or PHPUnit-internal API usage merely to make such versions pass. Expanding the
support matrix requires an explicit support-policy change first, together with a
maintained CI/runtime definition for the newly supported version.

Within the declared current baseline, regressions remain regressions and must not
be hidden by weakening, skipping, or suppressing tests or diagnostics.

PHPUnit extensions and consuming tests must use PHPUnit 13 public APIs, native
lifecycle methods, and PHP attributes. The harness retains only WordPress-specific
lifecycle behavior that is still required by the supported current WordPress 7.x
suite.
