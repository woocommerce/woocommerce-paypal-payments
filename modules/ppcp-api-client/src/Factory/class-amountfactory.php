<?php
/**
 * The Amount factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class AmountFactory
 */
class AmountFactory {


	/**
	 * The item factory.
	 *
	 * @var ItemFactory
	 */
	private $item_factory;

	/**
	 * AmountFactory constructor.
	 *
	 * @param ItemFactory $item_factory The Item factory.
	 */
	public function __construct( ItemFactory $item_factory ) {
		$this->item_factory = $item_factory;
	}

	/**
	 * Returns an Amount object based off a WooCommerce cart.
	 *
	 * @param \WC_Cart $cart The cart.
	 *
	 * @return Amount
	 */
	public function from_wc_cart( \WC_Cart $cart ): Amount {
		$currency = get_woocommerce_currency();
		$total    = new Money( (float) $cart->get_total( 'numeric' ), $currency );

		$total_fees_amount = 0;
		$fees              = WC()->session->get( 'ppcp_fees' );
		if ( $fees ) {
			foreach ( WC()->session->get( 'ppcp_fees' ) as $fee ) {
				$total_fees_amount += (float) $fee->amount;
			}
		}

		$item_total = $cart->get_cart_contents_total() + $cart->get_discount_total() + $total_fees_amount;
		$item_total = new Money( (float) $item_total, $currency );
		$shipping   = new Money(
			(float) $cart->get_shipping_total() + $cart->get_shipping_tax(),
			$currency
		);

		$taxes = new Money(
			(float) $cart->get_cart_contents_tax() + (float) $cart->get_discount_tax(),
			$currency
		);

		$discount = null;
		if ( $cart->get_discount_total() ) {
			$discount = new Money(
				(float) $cart->get_discount_total() + $cart->get_discount_tax(),
				$currency
			);
		}

		$breakdown = new AmountBreakdown(
			$item_total,
			$shipping,
			$taxes,
			null, // insurance?
			null, // handling?
			null, // shipping discounts?
			$discount
		);
		$amount    = new Amount(
			$total,
			$breakdown
		);
		return $amount;
	}

	/**
	 * Returns an Amount object based off a WooCommerce order.
	 *
	 * @param \WC_Order $order The order.
	 *
	 * @return Amount
	 */
	public function from_wc_order( \WC_Order $order ): Amount {
		$currency   = $order->get_currency();
		$items      = $this->item_factory->from_wc_order( $order );
		$total      = new Money( (float) $order->get_total(), $currency );
		$item_total = new Money(
			(float) array_reduce(
				$items,
				static function ( float $total, Item $item ): float {
					return $total + $item->quantity() * $item->unit_amount()->value();
				},
				0
			),
			$currency
		);
		$shipping   = new Money(
			(float) $order->get_shipping_total() + (float) $order->get_shipping_tax(),
			$currency
		);
		$taxes      = new Money(
			(float) array_reduce(
				$items,
				static function ( float $total, Item $item ): float {
					return $total + $item->quantity() * $item->tax()->value();
				},
				0
			),
			$currency
		);

		$discount = null;
		if ( (float) $order->get_total_discount( false ) ) {
			$discount = new Money(
				(float) $order->get_total_discount( false ),
				$currency
			);
		}

		$breakdown = new AmountBreakdown(
			$item_total,
			$shipping,
			$taxes,
			null, // insurance?
			null, // handling?
			null, // shipping discounts?
			$discount
		);
		$amount    = new Amount(
			$total,
			$breakdown
		);
		return $amount;
	}

	/**
	 * Returns an Amount object based off a PayPal Response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Amount
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $data ): Amount {
		if ( ! isset( $data->value ) || ! is_numeric( $data->value ) ) {
			throw new RuntimeException( __( 'No value given', 'woocommerce-paypal-payments' ) );
		}
		if ( ! isset( $data->currency_code ) ) {
			throw new RuntimeException(
				__( 'No currency given', 'woocommerce-paypal-payments' )
			);
		}

		$money     = new Money( (float) $data->value, $data->currency_code );
		$breakdown = ( isset( $data->breakdown ) ) ? $this->break_down( $data->breakdown ) : null;
		return new Amount( $money, $breakdown );
	}

	/**
	 * Returns a AmountBreakdown object based off a PayPal response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return AmountBreakdown
	 * @throws RuntimeException When JSON object is malformed.
	 */
	private function break_down( \stdClass $data ): AmountBreakdown {
		/**
		 * The order of the keys equals the necessary order of the constructor arguments.
		 */
		$ordered_constructor_keys = array(
			'item_total',
			'shipping',
			'tax_total',
			'handling',
			'insurance',
			'shipping_discount',
			'discount',
		);

		$money = array();
		foreach ( $ordered_constructor_keys as $key ) {
			if ( ! isset( $data->{$key} ) ) {
				$money[] = null;
				continue;
			}
			$item = $data->{$key};

			if ( ! isset( $item->value ) || ! is_numeric( $item->value ) ) {
				throw new RuntimeException(
					sprintf(
					// translators: %s is the current breakdown key.
						__( 'No value given for breakdown %s', 'woocommerce-paypal-payments' ),
						$key
					)
				);
			}
			if ( ! isset( $item->currency_code ) ) {
				throw new RuntimeException(
					sprintf(
					// translators: %s is the current breakdown key.
						__( 'No currency given for breakdown %s', 'woocommerce-paypal-payments' ),
						$key
					)
				);
			}
			$money[] = new Money( (float) $item->value, $item->currency_code );
		}

		return new AmountBreakdown( ...$money );
	}
}
