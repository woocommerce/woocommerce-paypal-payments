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
	 * The Money factory.
	 *
	 * @var MoneyFactory
	 */
	private $money_factory;

	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * AmountFactory constructor.
	 *
	 * @param ItemFactory  $item_factory The Item factory.
	 * @param MoneyFactory $money_factory The Money factory.
	 * @param string       $currency 3-letter currency code of the shop.
	 */
	public function __construct( ItemFactory $item_factory, MoneyFactory $money_factory, string $currency ) {
		$this->item_factory  = $item_factory;
		$this->money_factory = $money_factory;
		$this->currency      = $currency;
	}

	/**
	 * Returns an Amount object based off a WooCommerce cart.
	 *
	 * @param \WC_Cart $cart The cart.
	 *
	 * @return Amount
	 */
	public function from_wc_cart( \WC_Cart $cart ): Amount {
		$total = new Money( (float) $cart->get_total( 'numeric' ), $this->currency );

		$total_fees_amount = 0;
		$fees              = WC()->session->get( 'ppcp_fees' );
		if ( $fees ) {
			foreach ( WC()->session->get( 'ppcp_fees' ) as $fee ) {
				$total_fees_amount += (float) $fee->amount;
			}
		}

		$item_total = $cart->get_cart_contents_total() + $cart->get_discount_total() + $total_fees_amount;
		$item_total = new Money( (float) $item_total, $this->currency );
		$shipping   = new Money(
			(float) $cart->get_shipping_total() + $cart->get_shipping_tax(),
			$this->currency
		);

		$taxes = new Money(
			$cart->get_subtotal_tax(),
			$this->currency
		);

		$discount = null;
		if ( $cart->get_discount_total() ) {
			$discount = new Money(
				(float) $cart->get_discount_total() + $cart->get_discount_tax(),
				$this->currency
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
		$money     = $this->money_factory->from_paypal_response( $data );
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
