<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Test environment bootstrap
|--------------------------------------------------------------------------
|
| The Docker container exports APP_ENV, DB_* and REDIS_* into the process
| environment, and PHP copies those into $_SERVER. Laravel's Env repository
| reads $_SERVER *before* $_ENV, so the container values would win over the
| <env> entries in phpunit.xml. The suite would then run with APP_ENV=local
| (CSRF middleware active → every write returns HTTP 419) against the
| development database.
|
| Normalising $_SERVER here guarantees the testing configuration always
| applies, regardless of how the container was started.
|
| Note what is *not* overridden: DB_HOST, DB_USERNAME and DB_PASSWORD stay as
| the container provides them. The test database lives on the same server as
| the development one, only with a different name.
|
*/

$overrides = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'pgsql',
    // docker/.env-common supplies TEST_DB_DATABASE; fall back to the documented
    // default so the suite still runs if the variable is missing.
    'DB_DATABASE' => getenv('TEST_DB_DATABASE') ?: 'feedbacker_test',
    // Uploads are exercised against a faked local disk, so the suite never
    // needs MinIO to be running.
    'FILESYSTEM_DISK' => 'local',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($overrides as $key => $value) {
    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}
