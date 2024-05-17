<?php
/**
 * The blocks module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'wcgateway.button.locations'                       => function ( ContainerInterface $container, array $locations ): array {
		return array_merge(
			$locations,
			array(
				'checkout-block-express' => _x( 'Express Checkout', 'Name of Buttons Location', 'woocommerce-paypal-payments' ),
				'cart-block'             => _x( 'Cart', 'Name of Buttons Location', 'woocommerce-paypal-payments' ),
			)
		);
	},
	'wcgateway.settings.pay-later.messaging-locations' => function ( ContainerInterface $container, array $locations ): array {
		unset( $locations['checkout-block-express'] );
		unset( $locations['cart-block'] );
		return $locations;
	},

	'wcgateway.settings.fields'                        => function ( ContainerInterface $container, array $fields ): array {
		$insert_after = function( array $array, string $key, array $new ): array {
			$keys = array_keys( $array );
			$index = array_search( $key, $keys, true );
			$pos = false === $index ? count( $array ) : $index + 1;

			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
		};

		$label = __(
			'Enable this option to require customers to manually confirm express payments on the checkout page.
<p class="description">This ensures they can review the order, update shipping options, and fill in eventual custom fields necessary for the transaction.</p>
<p class="description">If this is disabled, the system will automatically synchronize shipping options with PayPal and bypass the final checkout confirmation. This expedites the checkout process but prevents buyers from filling in eventual custom fields and reviewing final details before finalizing the payment.</p>',
			'woocommerce-paypal-payments'
		);

		if ( wc_terms_and_conditions_page_id() > 0 ) {
			$label .= __(
				'<div class="ppcp-notice ppcp-notice-warning"><p><span class="highlight">Important:</span> Your store has a <a href="/wp-admin/admin.php?page=wc-settings&tab=advanced" target="_blank">Terms and Conditions</a> page configured. Buyers who use a PayPal express payment method will not be able to consent to the terms on the <code>Classic Checkout</code>, as the final checkout confirmation will be skipped.</p></div>',
				'woocommerce-paypal-payments'
			);
		}

		$subscription_helper = $container->get( 'wc-subscriptions.helper' );

		return $insert_after(
			$fields,
			'smart_button_locations',
			array(
				'blocks_final_review_enabled' => array(
					'title'        => __( 'Require final confirmation on checkout', 'woocommerce-paypal-payments' ),
					'type'         => 'checkbox',
					'label'        => $label,
					'default'      => false,
					'screens'      => array( State::STATE_START, State::STATE_ONBOARDED ),
					'requirements' => array(),
					'gateway'      => 'paypal',
					'class'        => array( 'ppcp-grayed-out-text' ),
					'input_class'  => $subscription_helper->plugin_is_active() ? array( 'ppcp-disabled-checkbox' ) : array(),
				),
			)
		);
	},

	'button.pay-now-contexts'                          => function ( ContainerInterface $container, array $contexts ): array {
		if ( ! $container->get( 'blocks.settings.final_review_enabled' ) ) {
			$contexts[] = 'checkout-block';
			$contexts[] = 'cart-block';
		}

		return $contexts;
	},

	'button.handle-shipping-in-paypal'                 => function ( ContainerInterface $container ): bool {
		return ! $container->get( 'blocks.settings.final_review_enabled' );
	},
);
