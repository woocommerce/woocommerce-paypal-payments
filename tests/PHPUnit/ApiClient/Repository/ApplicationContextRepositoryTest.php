<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;
use function Brain\Monkey\Functions\when;

class ApplicationContextRepositoryTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function test_valid_bcp47_code($input, $output)
    {
        $testee = $this->getMockBuilder(ApplicationContextRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new \ReflectionMethod($testee, 'valid_bcp47_code');
        $method->setAccessible(true);

        when('get_user_locale')->justReturn($input);

        $this->assertSame($output, $method->invoke($testee));
    }

    public function provider()
    {
        return [
            'de-DE' => ['de-DE', 'de-DE'],
            'de-DE-formal' => ['de-DE-formal', 'de-DE'],
            'de' => ['de', 'de'],
            'ceb' => ['ceb', 'en'],
        ];
    }
}
