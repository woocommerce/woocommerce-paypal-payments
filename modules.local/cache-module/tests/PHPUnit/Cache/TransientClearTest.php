<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class TransientClearTest extends TestCase
{

    public function testClearWithObjectCache()
    {
        $testee = new Transient('group');
        expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);
        expect('wp_cache_flush')
            ->once()
            ->andReturn(true);
        $this->assertTrue($testee->clear());
    }

    public function testClearWithObjectCacheFails()
    {
        $testee = new Transient('group');
        expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);
        expect('wp_cache_flush')
            ->once()
            ->andReturn(false);
        $this->assertFalse($testee->clear());
    }

    public function testClearReturnsFalseWhenObjectCacheIsNotUsed()
    {
        $testee = new Transient('group');
        expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(false);
        expect('wc_delete_expired_transients')
            ->once()
            ->andReturn(true);
        $this->assertFalse($testee->clear());
    }
}
