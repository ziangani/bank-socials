<?php

namespace Tests;

use Mockery;
use Mockery\MockInterface;

abstract class MockeryTestCase extends TestCase
{
    protected function mock(string $class): MockInterface
    {
        return Mockery::mock($class);
    }

    protected function partialMock(string $class): MockInterface
    {
        return Mockery::mock($class)->makePartial();
    }

    protected function spy(string $class): MockInterface
    {
        return Mockery::spy($class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($container = Mockery::getContainer()) {
            $container->mockery_close();
        }
    }
}
