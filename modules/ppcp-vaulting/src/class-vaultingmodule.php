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
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Subscription\Repository\PaymentTokenRepository;

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
				// TODO flush rewrite
			}
		);

		add_action(
			'woocommerce_account_ppcp-paypal-payment-tokens_endpoint',
			function () use ( $container ) {

				/** @var PaymentTokenRepository $payment_token_repository */
				$payment_token_repository = $container->get( 'subscription.repository.payment-token' );

				$tokens = $payment_token_repository->all_for_user_id( get_current_user_id() );
				if ( $tokens ) {
					$renderer = $container->get( 'vaulting.payment-tokens-renderer' );
					echo wp_kses_post( $renderer->render( $tokens ) );
				}
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getKey() {  }
}
