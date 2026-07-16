<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;

/**
 * Pins the browser-facing WC-AJAX registration of the shared order endpoints:
 * the wc_ajax_ppc-* action names both SDK frontends POST to.
 *
 * Note: ppc-update-shipping is currently registered by the ppcp-blocks module
 * and only when WC Blocks is available (BlocksModule::run() returns early
 * otherwise) — the relocation ticket moves it to a neutral module. This test
 * asserts the names under a normal boot; renaming an ENDPOINT constant or
 * dropping a registration breaks it.
 *
 * @covers \WooCommerce\PayPalCommerce\Button\ButtonModule
 * @covers \WooCommerce\PayPalCommerce\Blocks\BlocksModule
 */
class WcAjaxHookRegistrationContractTest extends TestCase {

	/**
	 * The wc_ajax_ actions of the shared order endpoints.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function wcAjaxHookProvider(): array {
		return array(
			'ppc-create-order'    => array( 'wc_ajax_ppc-create-order' ),
			'ppc-change-cart'     => array( 'wc_ajax_ppc-change-cart' ),
			'ppc-approve-order'   => array( 'wc_ajax_ppc-approve-order' ),
			'ppc-update-shipping' => array( 'wc_ajax_ppc-update-shipping' ),
		);
	}

	/**
	 * GIVEN the plugin booted normally (WC Blocks available)
	 * WHEN the modules have run
	 * THEN each shared order endpoint is registered under its wc_ajax_ppc-* action
	 *
	 * @dataProvider wcAjaxHookProvider
	 *
	 * @param string $hook The wc_ajax_ action name.
	 */
	public function test_shared_order_endpoint_is_registered( string $hook ): void {
		$this->assertNotFalse(
			has_action( $hook ),
			"The $hook action must be registered — it is the browser-facing entry point of a shared order endpoint"
		);
	}
}
