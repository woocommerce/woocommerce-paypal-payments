<?php
/**
 * The Axo module services.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Axo\Assets\AxoManager;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Axo\Helper\ApmApplies;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CartCheckoutDetector;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(

	// If AXO can be configured.
	'axo.eligible'                          => static function ( ContainerInterface $container ): bool {
		$apm_applies = $container->get( 'axo.helpers.apm-applies' );
		assert( $apm_applies instanceof ApmApplies );

		return $apm_applies->for_country_currency() && $apm_applies->for_settings();
	},

	'axo.helpers.apm-applies'               => static function ( ContainerInterface $container ) : ApmApplies {
		return new ApmApplies(
			$container->get( 'axo.supported-country-currency-matrix' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'api.shop.country' )
		);
	},

	// If AXO is configured and onboarded.
	'axo.available'                         => static function ( ContainerInterface $container ): bool {
		return true;
	},

	'axo.url'                               => static function ( ContainerInterface $container ): string {
		$path = realpath( __FILE__ );
		if ( false === $path ) {
			return '';
		}
		return plugins_url(
			'/modules/ppcp-axo/',
			dirname( $path, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},

	'axo.manager'                           => static function ( ContainerInterface $container ): AxoManager {
		return new AxoManager(
			$container->get( 'axo.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.settings.status' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'wcgateway.url' )
		);
	},

	'axo.gateway'                           => static function ( ContainerInterface $container ): AxoGateway {
		return new AxoGateway(
			$container->get( 'wcgateway.settings.render' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.url' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'axo.card_icons' ),
			$container->get( 'axo.card_icons.axo' ),
			$container->get( 'api.endpoint.order' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	'axo.card_icons'                        => static function ( ContainerInterface $container ): array {
		return array(
			array(
				'title' => 'Visa',
				'file'  => 'visa-dark.svg',
			),
			array(
				'title' => 'MasterCard',
				'file'  => 'mastercard-dark.svg',
			),
			array(
				'title' => 'American Express',
				'file'  => 'amex.svg',
			),
			array(
				'title' => 'Discover',
				'file'  => 'discover.svg',
			),
		);
	},

	'axo.card_icons.axo'                    => static function ( ContainerInterface $container ): array {
		return array(
			array(
				'title' => 'Visa',
				'file'  => 'visa-light.svg',
			),
			array(
				'title' => 'MasterCard',
				'file'  => 'mastercard-light.svg',
			),
			array(
				'title' => 'Amex',
				'file'  => 'amex-light.svg',
			),
			array(
				'title' => 'Discover',
				'file'  => 'discover-light.svg',
			),
			array(
				'title' => 'Diners Club',
				'file'  => 'dinersclub-light.svg',
			),
			array(
				'title' => 'JCB',
				'file'  => 'jcb-light.svg',
			),
			array(
				'title' => 'UnionPay',
				'file'  => 'unionpay-light.svg',
			),
		);
	},

	/**
	 * The matrix which countries and currency combinations can be used for AXO.
	 */
	'axo.supported-country-currency-matrix' => static function ( ContainerInterface $container ) : array {
		/**
		 * Returns which countries and currency combinations can be used for AXO.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_axo_supported_country_currency_matrix',
			array(
				'US' => array(
					'AUD',
					'CAD',
					'EUR',
					'GBP',
					'JPY',
					'USD',
				),
			)
		);
	},

	'axo.checkout-config-notice'            => static function ( ContainerInterface $container ) : string {
		$checkout_page_link = esc_url( get_edit_post_link( wc_get_page_id( 'checkout' ) ) ?? '' );
		$block_checkout_docs_link = __(
			'https://woocommerce.com/document/cart-checkout-blocks-status/#reverting-to-the-cart-and-checkout-shortcodes',
			'woocommerce-paypal-payments'
		);

		if ( CartCheckoutDetector::has_elementor_checkout() ) {
			$notice_content = sprintf(
				/* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store currently uses the <code>Elementor Checkout widget</code>. To enable Fastlane and accelerate payments, the page must include either the <code>Classic Checkout</code> or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the classic layout.',
					'woocommerce-paypal-payments'
				),
				esc_url( $checkout_page_link ),
				esc_url( $block_checkout_docs_link )
			);
		} elseif ( CartCheckoutDetector::has_block_checkout() ) {
			$notice_content = sprintf(
				/* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store currently uses the WooCommerce <code>Checkout</code> block. To enable Fastlane and accelerate payments, the page must include either the <code>Classic Checkout</code> or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the classic layout.',
					'woocommerce-paypal-payments'
				),
				esc_url( $checkout_page_link ),
				esc_url( $block_checkout_docs_link )
			);
		} elseif ( ! CartCheckoutDetector::has_classic_checkout() ) {
			$notice_content = sprintf(
			/* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store does not seem to be properly configured or uses an incompatible <code>third-party Checkout</code> solution. To enable Fastlane and accelerate payments, the page must include either the <code>Classic Checkout</code> or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the classic layout.',
					'woocommerce-paypal-payments'
				),
				esc_url( $checkout_page_link ),
				esc_url( $block_checkout_docs_link )
			);
		} else {
			return '';
		}

		return '<div class="ppcp-notice ppcp-notice-error"><p>' . $notice_content . '</p></div>';
	},

	'axo.smart-button-location-notice'      => static function ( ContainerInterface $container ) : string {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		if ( $settings->has( 'axo_enabled' ) && $settings->get( 'axo_enabled' ) ) {
			$fastlane_settings_url = admin_url(
				sprintf(
					'admin.php?page=wc-settings&tab=checkout&section=%1$s&ppcp-tab=%2$s#field-axo_heading',
					PayPalGateway::ID,
					CreditCardGateway::ID
				)
			);

			$notice_content = sprintf(
			/* translators: %1$s: URL to the Checkout edit page. */
				__(
					'<span class="highlight">Important:</span> The <code>Cart</code> & <code>Classic Cart</code> <strong>Smart Button Locations</strong> cannot be disabled while <a href="%1$s">Fastlane</a> is active.',
					'woocommerce-paypal-payments'
				),
				esc_url( $fastlane_settings_url )
			);
		} else {
			return '';
		}

		return '<div class="ppcp-notice ppcp-notice-warning"><p>' . $notice_content . '</p></div>';
	},
	'axo.endpoint.frontend-logger'          => static function ( ContainerInterface $container ): FrontendLoggerEndpoint {
		return new FrontendLoggerEndpoint(
			$container->get( 'button.request-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
);
