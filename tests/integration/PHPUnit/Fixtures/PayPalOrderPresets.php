<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Fixtures;

/**
 * PayPal-order-shaped array presets for the WC-AJAX endpoint contract tests.
 *
 * Materialize into real api-client entities via the order factory, e.g.:
 *
 *   $order = $container->get( 'api.factory.order' )
 *       ->from_paypal_response( json_decode( (string) wp_json_encode( $preset ) ) );
 *
 * Real entities (unlike Mockery mocks) survive serialization into the WC
 * session through SessionHandler::replace_order() and expose working
 * purchase units for the ownership checks and the WC order creator.
 */
final class PayPalOrderPresets {

	/**
	 * A minimal PayPal order with one purchase unit.
	 *
	 * @param string $id The PayPal order id.
	 * @param string $status The order status (CREATED | APPROVED | COMPLETED).
	 * @param string $custom_id The purchase unit custom id (compute at runtime from the WC session).
	 * @param string $value The amount value.
	 * @param string $currency The currency code.
	 * @param array  $overrides Recursive overrides merged into the preset.
	 * @return array
	 */
	public static function order(
		string $id,
		string $status,
		string $custom_id = '',
		string $value = '10.00',
		string $currency = 'USD',
		array $overrides = array()
	): array {
		return array_replace_recursive(
			array(
				'id'             => $id,
				'intent'         => 'CAPTURE',
				'status'         => $status,
				'purchase_units' => array(
					array(
						'reference_id' => 'default',
						'custom_id'    => $custom_id,
						'amount'       => array(
							'currency_code' => $currency,
							'value'         => $value,
						),
					),
				),
			),
			$overrides
		);
	}

	/**
	 * A COMPLETED PayPal order with a completed capture, as returned by the
	 * capture call the order processor performs.
	 *
	 * @param string $id The PayPal order id.
	 * @param string $custom_id The purchase unit custom id.
	 * @param string $value The amount value.
	 * @param string $currency The currency code.
	 * @return array
	 */
	public static function completedWithCapture(
		string $id,
		string $custom_id = '',
		string $value = '10.00',
		string $currency = 'USD'
	): array {
		return self::order(
			$id,
			'COMPLETED',
			$custom_id,
			$value,
			$currency,
			array(
				'purchase_units' => array(
					array(
						'payments' => array(
							'captures' => array(
								array(
									'id'                => 'TEST-CAPTURE-1',
									'status'            => 'COMPLETED',
									'final_capture'     => true,
									'amount'            => array(
										'currency_code' => $currency,
										'value'         => $value,
									),
									'seller_protection' => array( 'status' => 'ELIGIBLE' ),
								),
							),
						),
					),
				),
			)
		);
	}
}
