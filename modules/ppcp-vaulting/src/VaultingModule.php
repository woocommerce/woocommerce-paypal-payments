<?php
/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Vaulting\Endpoint\DeletePaymentTokenEndpoint;

/**
 * Class StatusReportModule
 */
class VaultingModule implements ModuleInterface {


	/**
	 * {@inheritDoc}
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container A services container instance.
	 */
	public function run( ContainerInterface $container ): void {

		add_filter(
			'woocommerce_account_menu_items',
			function( $menu_links ) {
				$menu_links = array_slice( $menu_links, 0, 5, true )
				+ array( 'ppcp-paypal-payment-tokens' => 'PayPal payments' )
				+ array_slice( $menu_links, 5, null, true );

				return $menu_links;
			},
			40
		);

		add_action(
			'init',
			function () {
				add_rewrite_endpoint( 'ppcp-paypal-payment-tokens', EP_PAGES );
			}
		);

		add_action(
			'woocommerce_account_ppcp-paypal-payment-tokens_endpoint',
			function () use ( $container ) {
				$payment_token_repository = $container->get( 'vaulting.repository.payment-token' );
				$renderer                 = $container->get( 'vaulting.payment-tokens-renderer' );

				$tokens = $payment_token_repository->all_for_user_id( get_current_user_id() );
				if ( $tokens ) {
					echo wp_kses_post( $renderer->render( $tokens ) );
				} else {
					echo wp_kses_post( $renderer->render_no_tokens() );
				}
			}
		);

		$asset_loader = $container->get( 'vaulting.assets.myaccount-payments' );
		add_action(
			'wp_enqueue_scripts',
			function () use ( $asset_loader ) {
				if ( is_account_page() && $this->is_payments_page() ) {
					$asset_loader->enqueue();
					$asset_loader->localize();
				}
			}
		);

		add_action(
			'wc_ajax_' . DeletePaymentTokenEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'vaulting.endpoint.delete' );
				assert( $endpoint instanceof DeletePaymentTokenEndpoint );

				$endpoint->handle_request();
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getKey() {  }

	/**
	 * Check if is payments page.
	 *
	 * @return bool Whethen page is payments or not.
	 */
	private function is_payments_page(): bool {
		global $wp;
		$request = explode( '/', wp_parse_url( $wp->request, PHP_URL_PATH ) );
		if ( end( $request ) === 'ppcp-paypal-payment-tokens' ) {
			return true;
		}

		return false;
	}
}
