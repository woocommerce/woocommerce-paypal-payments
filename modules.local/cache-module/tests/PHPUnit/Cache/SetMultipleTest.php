<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;


use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;

class SetMultipleTest extends TestCase
{

    public function testSetMultiple() {
        $testee = new Transient('group');
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        expect('set_transient')
            ->times(3)
            ->andReturnUsing(
                function($key, $value) use ($values) {
                    $key = str_replace('group', '', $key);
                    return isset($values[$key]) && $values[$key] === $value;
                }
            );

        $this->assertTrue($testee->setMultiple($values));
    }

    public function testSetMultipleThrowsErrorIfNotIterateable() {
        $testee = new Transient('group');
        $values = new \stdClass();
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->setMultiple($values);
    }

    public function testSetMultipleThrowsErrorIfKeyIsNotString() {
        $testee = new Transient('group');
        $values = [1];
        $this->expectException(InvalidCacheArgumentException::class);
        $testee->setMultiple($values);
    }
}