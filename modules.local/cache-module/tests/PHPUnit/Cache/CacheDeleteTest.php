<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class CacheDeleteTest extends TestCase
{

    public function testDelete()
    {
        $testee = new Cache('group');
        expect('wp_cache_delete')
            ->once()
            ->with('key', 'group')
            ->andReturn(true);
        $this->assertTrue($testee->delete('key'));
    }

    public function testDeleteFails()
    {
        $testee = new Cache('group');
        expect('wp_cache_delete')
            ->once()
            ->with('key', 'group')
            ->andReturn(false);
        $this->assertFalse($testee->delete('key'));
    }

    public function testDeleteThrowsErrorIfKeyIsNotAString()
    {
        $testee = new Cache('group');
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->delete(123);
    }
}
