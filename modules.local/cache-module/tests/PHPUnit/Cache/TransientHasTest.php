<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;


use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class TransientHasTest extends TestCase
{

    public function testHas() {
        $testee = new Transient('group');
        expect('get_transient')->with('groupkey')->andReturn(1);
        $this->assertTrue($testee->has('key'));
    }
}