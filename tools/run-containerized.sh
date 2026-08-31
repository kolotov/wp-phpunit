#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME_ENV="$ROOT/containers/phpunit13/runtime.env"
ENGINE="${CONTAINER_ENGINE:-podman}"

if [[ ! -f "$RUNTIME_ENV" ]]; then
    printf 'Missing runtime definition: %s\n' "$RUNTIME_ENV" >&2
    exit 1
fi

# shellcheck disable=SC1090
source "$RUNTIME_ENV"

runtime_fingerprint=$(
    cat \
        "$ROOT/containers/phpunit13/Containerfile" \
        "$RUNTIME_ENV" \
        "$ROOT/containers/phpunit13/entrypoint.sh" \
        | sha256sum \
        | awk '{ print substr($1, 1, 16) }'
)
IMAGE="${WP_PHPUNIT_TEST_IMAGE:-wp-phpunit-package:local-${runtime_fingerprint}}"
TARGET="${1:-quality}"
if [[ $# -gt 0 ]]; then
    shift
fi

usage() {
    cat <<'EOF'
Usage: tools/run-containerized.sh <composer-script> [arguments...]

Runs wp-phpunit package validation in the canonical Ubuntu/PHP container.
The full WordPress PHPUnit matrix belongs to wordpress-develop.
EOF
}

case "$TARGET" in
    test|analyse|compatibility|format|format:check|rector:check|rector:fix|quality|shell) ;;
    -h|--help|help)
        usage
        exit 0
        ;;
    *)
        printf 'Unsupported package validation target: %s\n' "$TARGET" >&2
        usage >&2
        exit 2
        ;;
esac

if ! command -v "$ENGINE" >/dev/null 2>&1; then
    printf 'Container engine is not available: %s\n' "$ENGINE" >&2
    exit 1
fi

if [[ "${WP_PHPUNIT_SKIP_BUILD:-false}" == "true" ]]; then
    "$ENGINE" image inspect "$IMAGE" >/dev/null
else
    RUNTIME_REVISION=${RUNTIME_REVISION:-$(git -C "$ROOT" rev-parse HEAD)}
    "$ENGINE" build --pull \
        --build-arg "RUNTIME_REVISION=$RUNTIME_REVISION" \
        --tag "$IMAGE" \
        --file "$ROOT/containers/phpunit13/Containerfile" \
        "$ROOT"
fi

printf '\n==> wp-phpunit package validation runtime\n'
printf 'Container engine: %s\n' "$ENGINE"
printf 'Test image: %s\n' "$IMAGE"
"$ENGINE" run --rm "$IMAGE" bash -lc '
    printf "Ubuntu: %s\n" "$(. /etc/os-release && printf "%s" "$VERSION_ID")"
    printf "PHP: %s\n" "$(php -r '\''echo PHP_VERSION;'\'')"
    printf "Composer: %s\n" "$(composer --version --no-ansi)"
'

COMMON_ARGS=(
    --rm
    -v "$ROOT:/workspace"
    -w /workspace
    -e WP_PHPUNIT_CONTAINERIZED=1
    -e HOME=/tmp/wp-phpunit-home
    -e COMPOSER_CACHE_DIR=/workspace/.cache/composer
)

if [[ "$ENGINE" == "podman" ]]; then
    COMMON_ARGS+=(--security-opt label=disable --userns=keep-id)
fi

if [[ "$TARGET" == "shell" ]]; then
    exec "$ENGINE" run -it "${COMMON_ARGS[@]}" "$IMAGE" bash
fi

"$ENGINE" run "${COMMON_ARGS[@]}" "$IMAGE" \
    bash -lc 'set -e; mkdir -p "$HOME" "$COMPOSER_CACHE_DIR"; composer install --no-interaction --prefer-dist; exec composer "$@"' \
    wp-phpunit "$TARGET" "$@"
