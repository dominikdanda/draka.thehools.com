<?php

// Override inherited shell values before Laravel can load configuration.
foreach ([
    'APP_ENV' => 'testing',
    'APP_URL' => 'http://localhost',
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/thehools-tests-no-config-cache-'.getmypid().'.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
