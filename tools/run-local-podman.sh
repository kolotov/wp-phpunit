#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

export CONTAINER_ENGINE=podman
exec "$ROOT/tools/run-containerized.sh" "$@"
