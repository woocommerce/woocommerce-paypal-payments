<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class TransientGetTest extends TestCase
{

    public function testGetHasValueInCache()
    {
        $testee = new Transient('group');
        $expected = 'value';
        expect('get_transient')
            ->once()
            ->with('groupkey')
            ->andReturn($expected);
        $result = $testee->get('key', 'default');
        $this->assertEquals($expected, $result);
    }

    public function testGetHasValueNotInCache()
    {
        $testee = new Transient('group');
        $expected = 'value';
        expect('get_transient')
            ->once()
            ->with('groupkey')
            ->andReturn(false);
        $result = $testee->get('key', $expected);
        $this->assertEquals($expected, $result);
    }

    public function testGetThrowsExceptionWhenKeyIsNotAString()
    {
        $testee = new Transient('group');
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->get(123);
    }
}
