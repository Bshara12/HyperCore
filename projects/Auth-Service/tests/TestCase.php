<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\InteractsWithJwtKeys; // تأكد أن المسار صحيح

abstract class TestCase extends BaseTestCase
{
  use CreatesApplication, InteractsWithJwtKeys;

  protected function setUp(): void
  {
    parent::setUp();
    $this->setupJwtKeys();
  }

  protected function tearDown(): void
  {
    $this->cleanupJwtKeys();
    parent::tearDown();
  }
}
