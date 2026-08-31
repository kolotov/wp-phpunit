<?php

declare(strict_types=1);

if (getenv('WP_PHPUNIT_CONTAINERIZED') === '1') {
    exit(0);
}

fwrite(
    STDERR,
    "Local validation must run in the Ubuntu 24.04 Podman environment.\n"
    . "Use: tools/run-local-podman.sh <composer-script>\n"
);

exit(1);
