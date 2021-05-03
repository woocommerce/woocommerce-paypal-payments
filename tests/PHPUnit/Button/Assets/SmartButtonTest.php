<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Assets;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class SmartButtonTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function test_valid_locale_code($input, $output)
    {
        $testee = $this->getMockBuilder(SmartButton::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new \ReflectionMethod($testee, 'valid_locale_code');
        $method->setAccessible(true);

        when('get_user_locale')->justReturn($input);

        $this->assertSame($output, $method->invoke($testee));
    }

    public function provider()
    {
        return [
            'de_DE' => ['de_DE', 'de_DE'],
            'de_DE_formal' => ['de_DE_formal', 'de_DE'],
        ];
    }
}
