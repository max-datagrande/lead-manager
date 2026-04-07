<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
  protected function setUp(): void
  {
    // Crear la app ANTES de parent::setUp() para verificar la conexion
    // parent::setUp() dispara RefreshDatabase -> migrate:fresh
    $this->refreshApplication();

    $driver = $this->app['db']->connection()->getDriverName();

    if ($driver !== 'sqlite') {
      fwrite(STDERR, "\n\n" .
        "!! FAILED TESTS: connection detected to '{$driver}' !!\n" .
        "!! The tests MUST run in SQLite.              !!\n" .
        "!! Run: php artisan config:clear                   !!\n\n"
      );
      exit(1);
    }

    parent::setUp();
  }
}
