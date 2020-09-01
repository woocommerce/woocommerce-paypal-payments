<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Hamcrest\Matchers;
use Inpsyde\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use Inpsyde\PayPalCommerce\TestCase;
use Mockery\MockInterface;
use Psr\Container\ContainerInterface;
use function Brain\Monkey\Functions\expect;

class ApplicationContextRepositoryTest extends TestCase
{
    private static $mocks = [];

    /**
     * @dataProvider currentContextData
     */
    public function testCurrentContext(
        array $container,
        string $userLocale,
        string $shippingPreference,
        array $expected
    ): void
    {
        $brandName = 'Acme Corp.';

        // Config
        foreach ($container as $key => $value) {
            $this->buildTestee()[0]->shouldReceive('has')
                ->with(Matchers::identicalTo($key))
                ->andReturn(true);
            $this->buildTestee()[0]->shouldReceive('get')
                ->with(Matchers::identicalTo($key))
                ->andReturn($value);
        }

	    expect('home_url')
		    ->andReturn('https://example.com/');
	    expect('wc_get_checkout_url')
		    ->andReturn('https://example.com/checkout/');

        expect('get_user_locale')
            ->andReturn($userLocale);

        /* @var ApplicationContextRepository $testee */
        $testee = $this->buildTestee()[1];

        $context = $testee->current_context($shippingPreference);

        self::assertInstanceOf(
            ApplicationContext::class,
            $context
        );

        foreach ($expected as $method => $value) {
            self::assertSame(
                $value,
                $context->{$method}(),
                "Test failed for method {$method}"
            );
        }
    }

    /**
     * @see testCurrentContext
     */
    public function currentContextData(): array
    {
        return [
            'default test' => [
                'container' => [
                    'brand_name' => 'Acme corp.',
                    'landing_page' => ApplicationContext::LANDING_PAGE_BILLING,
                ],
                'user_locale' => 'de_DE',
                'shippingPreference' => ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING,
                'expected' => [
                    'locale' => 'de-DE',
                    'brand_name' => 'Acme corp.',
                    'landing_page' => ApplicationContext::LANDING_PAGE_BILLING,
                    'shipping_preference' => ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING,
                ],
            ],
        ];
    }

    public function tearDown(): void
    {
        self::$mocks = [];
        parent::tearDown();
    }

    /**
     * @return MockInterface[]
     */
    private function buildTestee(): array
    {
        if (! self::$mocks) {
            $config = \Mockery::mock(ContainerInterface::class);
            $testee = new ApplicationContextRepository($config);

            self::$mocks = [
                $config,
                $testee,
            ];
        }

        return self::$mocks;
    }
}
