<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class CacheGetTest extends TestCase
{

    public function testGetHasValueInCache() {
        $this->markTestIncomplete(
            'This test has not been implemented yet. Problem is the $found reference'
        );
        $testee = new Cache('group');
        $expected = 'value';
        expect('wp_cache_get')
            ->once()
            ->andReturnUsing(function($key, $group, $force, &$lastFound) use ($expected) {
                $lastFound = true;
                return $expected;
            });
        $result = $testee->get('key', 'default');
        $this->assertEquals($expected, $result);
    }

    public function testGetHasValueNotInCache() {
        $this->markTestIncomplete(
            'This test has not been implemented yet. Problem is the $found reference'
        );
        $testee = new Cache('group');
        $expected = 'value';
        expect('wp_cache_get')
            ->once()
            ->with('key', 'group', false, false)
            ->andReturn(false);
        $result = $testee->get('key', $expected);
        $this->assertEquals($expected, $result);
    }

    public function testGetThrowsExceptionWhenKeyIsNotAString() {

        $testee = new Cache('group');
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->get(123);
    }
}