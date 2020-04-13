<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class CacheClearTest extends TestCase
{

    public function testClear()
    {
        $testee = new Cache('group');
        expect('wp_cache_flush')
            ->once()
            ->andReturn(true);
        $this->assertTrue($testee->clear());
    }

    public function testClearReturnsFalseWhenCacheWasNotCleared()
    {
        $testee = new Cache('group');
        expect('wp_cache_flush')
            ->once()
            ->andReturn(false);
        $this->assertFalse($testee->clear());
    }
}
