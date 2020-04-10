<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;


use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class DeleteMultipleTest extends TestCase
{

    public function testDeleteMultiple() {

        $testee = new Transient('group');
        $keys = ['key1', 'key2'];
        expect('delete_transient')->once()->with('groupkey1')->andReturn(true);
        expect('delete_transient')->once()->with('groupkey2')->andReturn(true);

        $this->assertTrue($testee->deleteMultiple($keys));
    }
    public function testDeleteMultipleThrowsErrorIfKeysAreNotIterateable() {

        $testee = new Transient('group');
        $keys = new \stdClass();
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->deleteMultiple($keys);
    }
    public function testDeleteMultipleThrowsErrorIfKeysAreNotStrings() {

        $testee = new Transient('group');
        $keys = [1];
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->deleteMultiple($keys);
    }
}