<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;


use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class TransientDeleteTest extends TestCase
{

    public function testDelete() {
        $testee = new Transient('group');
        expect('delete_transient')
            ->once()
            ->with('groupkey')
            ->andReturn(true);
        $this->assertTrue($testee->delete('key'));
    }

    public function testDeleteFails() {
        $testee = new Transient('group');
        expect('delete_transient')
            ->once()
            ->with('groupkey')
            ->andReturn(false);
        $this->assertFalse($testee->delete('key'));
    }

    public function testDeleteThrowsErrorIfKeyIsNotAString() {
        $testee = new Transient('group');
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->delete(123);
    }

}