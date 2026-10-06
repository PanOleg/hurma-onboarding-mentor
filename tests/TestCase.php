<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bindFakes();
    }

    protected function bindFakes(): void
    {
        // Task 6 and Task 11 add bindings here.
    }
}
