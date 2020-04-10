<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Provider;


use Inpsyde\CacheModule\Cache\Cache;
use Inpsyde\CacheModule\Cache\Transient;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class CacheProviderTest extends TestCase
{

    public function test_transientForKey() {
        $testee = new CacheProvider();
        $result = $testee->transientForKey('group');

        expect('set_transient')
            ->once()
            ->with('groupkey', 'value', 0)
            ->andReturn(true);
        $this->assertTrue($result->set('key', 'value'), 'Group has not been set correctly.');
        $this->assertTrue(is_a($result, Transient::class));
    }
    public function test_cacheOrTransientForKeyReturnsCache() {
        $testee = new CacheProvider();
        expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);
        $result = $testee->cacheOrTransientForKey('group');

        $this->assertInstanceOf(Cache::class, $result);
    }
    public function test_cacheOrTransientForKeyReturnsTransient() {
        $testee = new CacheProvider();
        expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(false);
        $result = $testee->cacheOrTransientForKey('group');
        $this->assertInstanceOf(Transient::class, $result);
    }

    public function test_cacheForKey() {
        $testee = new CacheProvider();
        $result = $testee->cacheForKey('group');
        expect('wp_cache_set')
            ->once()
            ->with('key', 'value', 'group', 0)
            ->andReturn(true);
        $this->assertTrue($result->set('key', 'value'), 'Group has not been set correctly.');
        $this->assertTrue(is_a($result, Cache::class));
    }
}