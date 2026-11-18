<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $app->make('db')->purge();
        $app->make('config')->set('database.default', 'sqlite');
        $app->make('config')->set('database.connections', [
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        return $app;
    }
}
