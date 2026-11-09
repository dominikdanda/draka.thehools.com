<?php

// Run only in the disposable CI/test checkout. No Laravel bootstrap or database access.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "CI directory preparation requires APP_ENV=testing.\n");
    exit(1);
}

umask(0077);

foreach ([
    'bootstrap/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/framework/testing',
    'storage/logs',
] as $directory) {
    if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
        fwrite(STDERR, "Cannot create CI directory: {$directory}\n");
        exit(1);
    }

    if (PHP_OS_FAMILY !== 'Windows' && ! chmod($directory, 0700)) {
        fwrite(STDERR, "Cannot set CI directory permissions: {$directory}\n");
        exit(1);
    }

    clearstatcache(true, $directory);
    if (! is_writable($directory)) {
        fwrite(STDERR, "CI directory is not writable: {$directory}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Laravel CI runtime directories are ready.\n");
