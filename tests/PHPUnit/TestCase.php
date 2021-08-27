<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use Mockery;

class TestCase extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        when('__')->returnArg();

        setUp();
    }

    public function tearDown(): void
    {
        tearDown();
        Mockery::close();
        parent::tearDown();
    }
}
