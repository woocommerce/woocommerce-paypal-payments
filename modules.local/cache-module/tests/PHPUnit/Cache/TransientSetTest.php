<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class TransientSetTest extends TestCase
{

    public function testSet()
    {
        $testee = new Transient('group');
        expect('set_transient')
            ->once()
            ->with('groupkey', 'value', 123)
            ->andReturn(true);
        $setValue = $testee->set('key', 'value', 123);
        $this->assertTrue($setValue);
    }

    public function testSetReturnsFalseWhenCacheNotSet()
    {
        $testee = new Transient('group');
        expect('set_transient')
            ->once()
            ->with('groupkey', 'value', 123)
            ->andReturn(false);
        $setValue = $testee->set('key', 'value', 123);
        $this->assertFalse($setValue);
    }

    public function testSetThrowsErrorWhenKeyIsNotAString()
    {
        $testee = new Transient('group');
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->set(123, 'value');
    }
}
