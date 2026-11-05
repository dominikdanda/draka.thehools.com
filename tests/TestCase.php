<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';

        // Never load developer credentials or an existing configuration cache.
        $app->loadEnvironmentFrom('.env.testing.isolated');
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $connection = [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections', [
                'sqlite' => $connection,
                'lobby' => $connection,
            ]);
        });
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
