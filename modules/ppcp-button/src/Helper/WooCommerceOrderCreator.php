<?php
/**
 * Can create WC orders.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Exception;
use RuntimeException;
use WC_Cart;
use WC_Customer;
use WC_Data_Exception;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Product;
use WC_Subscription;
use WC_Subscriptions_Product;
use WC_Tax;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WP_Error;

/**
 * Class WooCommerceOrderCreator
 */
class WooCommerceOrderCreator {

	/**
	 * The funding source renderer.
	 *
	 * @var FundingSourceRenderer
	 */
	protected $funding_source_renderer;

	/**
	 * The Session handler.
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

	/**
	 * The subscription helper
	 *
	 * @var SubscriptionHelper
	 */
	protected $subscription_helper;

	/**
	 * WooCommerceOrderCreator constructor.
	 *
	 * @param FundingSourceRenderer $funding_source_renderer The funding source renderer.
	 * @param SessionHandler        $session_handler The session handler.
	 * @param SubscriptionHelper    $subscription_helper The subscription helper.
	 */
	public function __construct(
		FundingSourceRenderer $funding_source_renderer,
		SessionHandler $session_handler,
		SubscriptionHelper $subscription_helper
	) {
		$this->funding_source_renderer = $funding_source_renderer;
		$this->session_handler         = $session_handler;
		$this->subscription_helper     = $subscription_helper;
	}

	/**
	 * Creates WC order based on given PayPal order.
	 *
	 * @param Order   $order The PayPal order.
	 * @param WC_Cart $wc_cart The Cart.
	 * @return WC_Order The WC order.
	 * @throws RuntimeException If problem creating.
	 */
	public function create_from_paypal_order( Order $order, WC_Cart $wc_cart ): WC_Order {
		$wc_order = wc_create_order();

		if ( ! $wc_order instanceof WC_Order ) {
			throw new RuntimeException( 'Problem creating WC order.' );
		}

		try {
			$payer    = $order->payer();
			$shipping = $order->purchase_units()[0]->shipping();

			$this->configure_payment_source( $wc_order );
			$this->configure_customer( $wc_order );
			$this->configure_line_items( $wc_order, $wc_cart, $payer, $shipping );
			$this->configure_addresses( $wc_order, $payer, $shipping, $wc_cart );
			$this->configure_coupons( $wc_order, $wc_cart->get_applied_coupons() );

			$wc_order->calculate_totals();
			$wc_order->save();
		} catch ( Exception $exception ) {
			$wc_order->delete( true );
			throw new RuntimeException( 'Failed to create WooCommerce order: ' . $exception->getMessage() );
		}

		do_action( 'woocommerce_paypal_payments_shipping_callback_woocommerce_order_created', $wc_order, $wc_cart );

		return $wc_order;
	}

	/**
	 * Configures the line items.
	 *
	 * @param WC_Order      $wc_order The WC order.
	 * @param WC_Cart       $wc_cart The Cart.
	 * @param Payer|null    $payer The payer.
	 * @param Shipping|null $shipping The shipping.
	 * @return void
	 * @psalm-suppress InvalidScalarArgument
	 */
	protected function configure_line_items( WC_Order $wc_order, WC_Cart $wc_cart, ?Payer $payer, ?Shipping $shipping ): void {
		$cart_contents = $wc_cart->get_cart();

		foreach ( $cart_contents as $cart_item ) {
			$product_id           = $cart_item['product_id'] ?? 0;
			$variation_id         = $cart_item['variation_id'] ?? 0;
			$quantity             = $cart_item['quantity'] ?? 0;
			$variation_attributes = $cart_item['variation'];

			$item = new WC_Order_Item_Product();
			$item->set_product_id( $product_id );
			$item->set_quantity( $quantity );

			if ( $variation_id ) {
				$item->set_variation_id( $variation_id );
				$item->set_variation( $variation_attributes );
			}

			$product = wc_get_product( $variation_id ?: $product_id );
			if ( ! $product ) {
				return;
			}

			$subtotal = wc_get_price_excluding_tax( $product, array( 'qty' => $quantity ) );
			$subtotal = apply_filters( 'woocommerce_paypal_payments_shipping_callback_cart_line_item_total', $subtotal, $cart_item );

			$item->set_name( $product->get_name() );
			$item->set_subtotal( $subtotal );
			$item->set_total( $subtotal );

			$this->configure_taxes( $product, $item, $subtotal );

			$product_id = $product->get_id();

			if ( $this->is_subscription( $product_id ) ) {
				$subscription       = $this->create_subscription( $wc_order, $product_id );
				$sign_up_fee        = WC_Subscriptions_Product::get_sign_up_fee( $product );
				$subscription_total = (float) $subtotal + (float) $sign_up_fee;

				$item->set_subtotal( $subscription_total );
				$item->set_total( $subscription_total );

				$subscription->add_product( $product );
				$this->configure_addresses( $subscription, $payer, $shipping, $wc_cart );
				$this->configure_payment_source( $subscription );
				$this->configure_coupons( $subscription, $wc_cart->get_applied_coupons() );

				$dates = array(
					'trial_end'    => WC_Subscriptions_Product::get_trial_expiration_date( $product_id ),
					'next_payment' => WC_Subscriptions_Product::get_first_renewal_payment_date( $product_id ),
					'end'          => WC_Subscriptions_Product::get_expiration_date( $product_id ),
				);

				$subscription->update_dates( $dates );
				$subscription->calculate_totals();
				$subscription->payment_complete_for_order( $wc_order );
			}

			$wc_order->add_item( $item );
		}
	}

	/**
	 * Configures the shipping & billing addresses for WC order from given payer.
	 *
	 * @param WC_Order      $wc_order The WC order.
	 * @param Payer|null    $payer The payer.
	 * @param Shipping|null $shipping The shipping.
	 * @param WC_Cart       $wc_cart The Cart.
	 * @return void
	 * @throws WC_Data_Exception|RuntimeException When failing to configure shipping.
	 * @psalm-suppress RedundantConditionGivenDocblockType
	 */
	protected function configure_addresses( WC_Order $wc_order, ?Payer $payer, ?Shipping $shipping, WC_Cart $wc_cart ): void {
		$shipping_address = null;
		$billing_address  = null;
		$shipping_options = null;

		if ( $payer ) {
			$address    = $payer->address();
			$payer_name = $payer->name();

			$wc_email    = null;
			$wc_customer = WC()->customer;
			if ( $wc_customer instanceof WC_Customer ) {
				$wc_email = $wc_customer->get_email();
			}

			$email = $wc_email ?: $payer->email_address();

			$billing_address = array(
				'email'      => $email ?: '',
				'first_name' => $payer_name ? $payer_name->given_name() : '',
				'last_name'  => $payer_name ? $payer_name->surname() : '',
				'address_1'  => $address ? $address->address_line_1() : '',
				'address_2'  => $address ? $address->address_line_2() : '',
				'city'       => $address ? $address->admin_area_2() : '',
				'state'      => $address ? $address->admin_area_1() : '',
				'postcode'   => $address ? $address->postal_code() : '',
				'country'    => $address ? $address->country_code() : '',
			);
		}

		if ( $shipping ) {
			$address = $shipping->address();

			$shipping_address = array(
				'first_name' => $shipping->name(),
				'last_name'  => '',
				'address_1'  => $address->address_line_1(),
				'address_2'  => $address->address_line_2(),
				'city'       => $address->admin_area_2(),
				'state'      => $address->admin_area_1(),
				'postcode'   => $address->postal_code(),
				'country'    => $address->country_code(),
			);

			$shipping_options = $shipping->options()[0] ?? '';
		}

		if ( $wc_cart->needs_shipping() && empty( $shipping_options ) ) {
			throw new RuntimeException( 'No shipping method has been selected.' );
		}

		if ( $shipping_address ) {
			$wc_order->set_shipping_address( $shipping_address );
		}

		if ( $billing_address || $shipping_address ) {
			$wc_order->set_billing_address( $billing_address ?: $shipping_address );
		}

		if ( $shipping_options ) {
			$shipping = new WC_Order_Item_Shipping();
			$shipping->set_method_title( $shipping_options->label() );
			$shipping->set_method_id( $shipping_options->id() );
			$shipping->set_total( $shipping_options->amount()->value_str() );

			$items            = $wc_order->get_items();
			$items_in_package = array();
			foreach ( $items as $item ) {
				$items_in_package[] = $item->get_name() . ' &times; ' . (string) $item->get_quantity();
			}

			$shipping->add_meta_data( __( 'Items', 'woocommerce-paypal-payments' ), implode( ', ', $items_in_package ) );

			$wc_order->add_item( $shipping );
		}

		$wc_order->calculate_totals();
	}

	/**
	 * Configures the payment source.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @return void
	 */
	protected function configure_payment_source( WC_Order $wc_order ): void {
		$funding_source = $this->session_handler->funding_source();
		$wc_order->set_payment_method( PayPalGateway::ID );

		if ( $funding_source ) {
			$wc_order->set_payment_method_title( $this->funding_source_renderer->render_name( $funding_source ) );
		}
	}

	/**
	 * Configures the customer ID.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @return void
	 */
	protected function configure_customer( WC_Order $wc_order ): void {
		$current_user = wp_get_current_user();

		if ( $current_user->ID !== 0 ) {
			$wc_order->set_customer_id( $current_user->ID );
		}
	}

	/**
	 * Configures the applied coupons.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @param string[] $coupons The list of applied coupons.
	 * @return void
	 */
	protected function configure_coupons( WC_Order $wc_order, array $coupons ): void {
		foreach ( $coupons as $coupon_code ) {
			$wc_order->apply_coupon( $coupon_code );
		}
	}

	/**
	 * Configures the taxes.
	 *
	 * @param WC_Product            $product The Product.
	 * @param WC_Order_Item_Product $item The line item.
	 * @param float|string          $subtotal The subtotal.
	 * @return void
	 * @psalm-suppress InvalidScalarArgument
	 */
	protected function configure_taxes( WC_Product $product, WC_Order_Item_Product $item, $subtotal ): void {
		$tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
		$taxes     = WC_Tax::calc_tax( $subtotal, $tax_rates, true );

		$item->set_tax_class( $product->get_tax_class() );
		$item->set_total_tax( (float) array_sum( $taxes ) );
	}

	/**
	 * Checks if the product with given ID is WC subscription.
	 *
	 * @param int $product_id The product ID.
	 * @return bool true if the product is subscription, otherwise false.
	 */
	protected function is_subscription( int $product_id ): bool {
		if ( ! $this->subscription_helper->plugin_is_active() ) {
			return false;
		}

		return WC_Subscriptions_Product::is_subscription( $product_id );
	}

	/**
	 * Creates WC subscription from given order and product ID.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @param int      $product_id The product ID.
	 * @return WC_Subscription The subscription order
	 * @throws RuntimeException If problem creating.
	 */
	protected function create_subscription( WC_Order $wc_order, int $product_id ): WC_Subscription {
		$subscription = wcs_create_subscription(
			array(
				'order_id'         => $wc_order->get_id(),
				'status'           => 'pending',
				'billing_period'   => WC_Subscriptions_Product::get_period( $product_id ),
				'billing_interval' => WC_Subscriptions_Product::get_interval( $product_id ),
				'customer_id'      => $wc_order->get_customer_id(),
			)
		);

		if ( $subscription instanceof WP_Error ) {
			throw new RuntimeException( $subscription->get_error_message() );
		}

		return $subscription;
	}
}
