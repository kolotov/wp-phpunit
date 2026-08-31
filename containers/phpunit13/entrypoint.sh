#!/usr/bin/env bash
set -euo pipefail

PHP_MINOR="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
export PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/etc/php/${PHP_MINOR}/cli/conf.d"

validate_extension_state() {
    local extension=$1
    local enabled=$2

    case "$enabled" in
        true|false) ;;
        *)
            printf 'Invalid %s state: %s\n' "$extension" "$enabled" >&2
            exit 2
            ;;
    esac
}

XDEBUG_ENABLED=${LOCAL_PHP_XDEBUG:-false}
MEMCACHED_ENABLED=${LOCAL_PHP_MEMCACHED:-false}
PCOV_ENABLED_REQUESTED=${LOCAL_PHP_PCOV:-false}
validate_extension_state xdebug "$XDEBUG_ENABLED"
validate_extension_state memcached "$MEMCACHED_ENABLED"
validate_extension_state pcov "$PCOV_ENABLED_REQUESTED"

if [[ "$(id -u)" != "0" ]]; then
    if [[ "$XDEBUG_ENABLED" == true || "$MEMCACHED_ENABLED" == true || "$PCOV_ENABLED_REQUESTED" == true ]]; then
        printf '%s\n' 'Enabling optional PHP extensions requires a root container process.' >&2
        exit 1
    fi
    export PCOV_ENABLED=0
    exec "$@"
fi

set_extension_state() {
    local extension=$1
    local enabled=$2

    if [[ "$enabled" == true ]]; then
        phpenmod -v "$PHP_MINOR" -s ALL "$extension"
    else
        phpdismod -v "$PHP_MINOR" -s ALL "$extension"
    fi
}

set_extension_state xdebug "$XDEBUG_ENABLED"
set_extension_state memcached "$MEMCACHED_ENABLED"
set_extension_state pcov "$PCOV_ENABLED_REQUESTED"

if [[ -f "/etc/php/${PHP_MINOR}/mods-available/opcache.ini" ]]; then
    if [[ "$XDEBUG_ENABLED" == "true" ]]; then
        phpdismod -v "$PHP_MINOR" -s ALL opcache
    else
        phpenmod -v "$PHP_MINOR" -s ALL opcache
    fi
fi

if [[ "$PCOV_ENABLED_REQUESTED" == "true" ]]; then
    export PCOV_ENABLED=1
else
    export PCOV_ENABLED=0
fi

if [[ "$(id -u)" == "0" && -n "${PHP_FPM_UID:-}" && -n "${PHP_FPM_GID:-}" ]]; then
    current_uid="$(id -u wp_php)"
    current_gid="$(id -g wp_php)"
    if [[ "$current_gid" != "$PHP_FPM_GID" ]]; then
        groupmod -o -g "$PHP_FPM_GID" wp_php
    fi
    if [[ "$current_uid" != "$PHP_FPM_UID" ]]; then
        usermod -o -u "$PHP_FPM_UID" wp_php
    fi
fi

exec "$@"
