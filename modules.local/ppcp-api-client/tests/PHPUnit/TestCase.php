<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient;


use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
class TestCase extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        setUp();
    }
    public function tearDown(): void
    {
        tearDown();
        \Mockery::close();
        parent::tearDown();
    }

}