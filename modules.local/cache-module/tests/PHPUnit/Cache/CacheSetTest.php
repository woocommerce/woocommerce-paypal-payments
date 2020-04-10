<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;


use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class CacheSetTest extends TestCase
{

    public function testSet() {
        $testee = new Cache('group');
        expect('wp_cache_set')
            ->once()
            ->with('key', 'value', 'group', 123)
            ->andReturn(true);
        $setValue = $testee->set('key', 'value', 123);
        $this->assertTrue($setValue);
    }

    public function testSetReturnsFalseWhenCacheNotSet() {
        $testee = new Cache('group');
        expect('wp_cache_set')
            ->once()
            ->with('key', 'value', 'group', 123)
            ->andReturn(false);
        $setValue = $testee->set('key', 'value', 123);
        $this->assertFalse($setValue);
    }

    public function testSetThrowsErrorWhenKeyIsNotAString() {
        $testee = new Cache('group');
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->set(123, 'value');
    }
}