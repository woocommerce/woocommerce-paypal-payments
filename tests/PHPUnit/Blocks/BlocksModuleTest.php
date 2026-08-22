<?php
declare(strict_types=1);

namespace {
	if ( ! function_exists( 'woocommerce_store_api_register_payment_requirements' ) ) {
		function woocommerce_store_api_register_payment_requirements( array $args ): void {
			$GLOBALS['ppcp_test_payment_requirements'] = $args;
		}
	}
}

namespace WooCommerce\PayPalCommerce\Blocks {
	use Mockery;
	use WooCommerce\PayPalCommerce\Button\Helper\Context;
	use WooCommerce\PayPalCommerce\TestCase;
	use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

	/**
	 * @covers \WooCommerce\PayPalCommerce\Blocks\BlocksModule
	 */
	class BlocksModuleTest extends TestCase {
		/**
		 * @dataProvider continuationProvider
		 */
		public function testPaymentRequirementsUseContextWithoutBuildingSmartButtonData(
			bool $is_continuation,
			array $expected
		): void {
			$context = Mockery::mock(Context::class);
			$context->shouldReceive('is_paypal_continuation')->once()->andReturn($is_continuation);

			$container = Mockery::mock(ContainerInterface::class);
			$container->shouldReceive('get')->once()->with('button.helper.context')->andReturn($context);
			$container->shouldNotReceive('get')->with('button.smart-button');

			unset($GLOBALS['ppcp_test_payment_requirements']);

			$this->assertTrue(( new BlocksModule() )->run($container));
			$this->assertArrayHasKey('data_callback', $GLOBALS['ppcp_test_payment_requirements']);
			$this->assertSame($expected, $GLOBALS['ppcp_test_payment_requirements']['data_callback']());
		}

		public function continuationProvider(): array {
			return array(
				'continuation' => array(true, array('ppcp_continuation')),
				'ordinary cart' => array(false, array()),
			);
		}
	}
}
