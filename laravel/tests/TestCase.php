<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithFixtures;
use Tests\Fixtures\TestFixturesSeeder;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;
    use InteractsWithFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSchemaIsReady();

        // Reference data plus the canonical dataset, all inside the test
        // transaction so nothing leaks into the next test.
        $this->seed([
            RolePermissionSeeder::class,
            TestFixturesSeeder::class,
        ]);

        // Spatie caches permissions per process; a stale cache from a previous
        // test must not leak into this one.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Схема в тестах не создаётся миграциями, а клонируется из БД разработки
     * командой `make test_db_migration`. При её отсутствии выводится понятное
     * сообщение вместо неочевидной ошибки SQL.
     */
    private function ensureSchemaIsReady(): void
    {
        try {
            $ready = Schema::hasTable('users')
                && Schema::hasTable('spaces')
                && Schema::hasTable('testimonials')
                && Schema::hasTable('roles');
        } catch (Throwable) {
            $ready = false;
        }

        if (! $ready) {
            $this->fail(
                'The test database schema is missing or incomplete. '.
                'Run `make test_db_migration` from the docker/ directory '.
                '(`make test` does it for you).'
            );
        }
    }
}
