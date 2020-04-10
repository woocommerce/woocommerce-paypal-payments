<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use PHPUnit\Framework\TestCase;
use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use function Brain\Monkey\Functions\expect;

class GetMultipleTest extends TestCase
{

    public function testGetMultiple() {
        $testee = new Transient('group');
        expect('get_transient')
            ->times(3)
            ->andReturn(1);
        $keys = [
            'key1',
            'key2',
            'key3',
        ];
        $result = $testee->getMultiple($keys);
        $diff = array_diff(array_keys($result), $keys);
        $this->assertEmpty($diff);
        foreach ($result as $value) {
            $this->assertTrue($value === 1);
        }
    }
    public function testGetMultipleThrowsErrorIfParamIsNotIterateable() {
        $testee = new Transient('group');
        $this->expectException(InvalidCacheArgumentException::class);
        $keys = new \stdClass();
        $testee->getMultiple($keys);
    }
    public function testGetMultipleThrowsErrorIfOneKeyIsNotAString() {
        $testee = new Transient('group');
        $this->expectException(InvalidCacheArgumentException::class);
        $keys = [1];
        $testee->getMultiple($keys);
    }

}