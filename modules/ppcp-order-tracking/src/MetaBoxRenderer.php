<?php
/**
 * The order tracking MetaBox renderer.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use WC_Order;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WP_Post;

/**
 * Class MetaBoxRenderer
 *
 * @psalm-type CarrierType = string
 * @psalm-type CarrierItemCode = string
 * @psalm-type CarrierItemName = string
 * @psalm-type Carrier = array{name: string, items: array<CarrierItemCode, CarrierItemName>}
 * @psalm-type Carriers = array<CarrierType, Carrier>
 */
class MetaBoxRenderer {

	public const NAME_PREFIX = 'ppcp-tracking';

	/**
	 * Allowed shipping statuses.
	 *
	 * @var string[]
	 */
	protected $allowed_statuses;

	/**
	 * Available shipping carriers.
	 *
	 * @var array
	 * @psalm-var Carriers
	 */
	protected $carriers;

	/**
	 * MetaBoxRenderer constructor.
	 *
	 * @param string[] $allowed_statuses Allowed shipping statuses.
	 * @param array    $carriers Available shipping carriers.
	 * @psalm-param Carriers $carriers
	 */
	public function __construct(
		array $allowed_statuses,
		array $carriers
	) {

		$this->allowed_statuses = $allowed_statuses;
		$this->carriers         = $carriers;
	}

	/**
	 * Renders the order tracking MetaBox.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render( WP_Post $post ): void {
		$wc_order = wc_get_order( $post->ID );
		if ( ! is_a( $wc_order, WC_Order::class ) ) {
			return;
		}

		$carriers        = (array) apply_filters( 'woocommerce_paypal_payments_tracking_carriers', $this->carriers, $wc_order->get_id() );
		$statuses        = (array) apply_filters( 'woocommerce_paypal_payments_tracking_statuses', $this->allowed_statuses, $wc_order->get_id() );
		$tracking_number = (string) apply_filters( 'woocommerce_paypal_payments_tracking_number', '', $wc_order->get_id() );
		$transaction_id  = $wc_order->get_transaction_id() ?: '';

		?>
		<p>
			<label for="<?php echo esc_attr( self::NAME_PREFIX ); ?>-transaction_id"><?php echo esc_html__( 'Transaction ID', 'woocommerce-paypal-payments' ); ?></label>
			<input type="text" disabled class="<?php echo esc_attr( self::NAME_PREFIX ); ?>-transaction_id" id="<?php echo esc_attr( self::NAME_PREFIX ); ?>-transaction_id" name="<?php echo esc_attr( self::NAME_PREFIX ); ?>[transaction_id]" value="<?php echo esc_html( $transaction_id ); ?>"/></p>
		<p>
			<label for="<?php echo esc_attr( self::NAME_PREFIX ); ?>-tracking_number"><?php echo esc_html__( 'Tracking Number', 'woocommerce-paypal-payments' ); ?></label>
			<input type="text" class="<?php echo esc_attr( self::NAME_PREFIX ); ?>-tracking_number" id="<?php echo esc_attr( self::NAME_PREFIX ); ?>-tracking_number" name="<?php echo esc_attr( self::NAME_PREFIX ); ?>[tracking_number]" value="<?php echo esc_html( $tracking_number ); ?>"/></p>
		<p>
			<label for="<?php echo esc_attr( self::NAME_PREFIX ); ?>-status"><?php echo esc_html__( 'Status', 'woocommerce-paypal-payments' ); ?></label>
			<select class="<?php echo esc_attr( self::NAME_PREFIX ); ?>-status" id="<?php echo esc_attr( self::NAME_PREFIX ); ?>-status" name="<?php echo esc_attr( self::NAME_PREFIX ); ?>[status]">
				<?php foreach ( $statuses as $key => $status ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $status ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="ppcp-tracking-carrier"><?php echo esc_html__( 'Carrier', 'woocommerce-paypal-payments' ); ?></label>
			<select class="ppcp-tracking-carrier" id="ppcp-tracking-carrier" name="ppcp-tracking[carrier]">
				<option value=""><?php echo esc_html__( 'Select Carrier', 'woocommerce-paypal-payments' ); ?></option>
				<?php
				foreach ( $carriers as $carrier ) :
					$country       = $carrier['name'] ?? '';
					$carrier_items = $carrier['items'] ?? array();
					?>
					<optgroup label="<?php echo esc_attr( $country ); ?>">
						<?php foreach ( $carrier_items as $carrier_code => $carrier_name ) : ?>
							<option value="<?php echo esc_attr( $carrier_code ); ?>"><?php echo esc_html( $carrier_name ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
		</p>
		<input type="hidden" class="ppcp-order_id" name="<?php echo esc_attr( self::NAME_PREFIX ); ?>[order_id]" value="<?php echo intval( $post->ID ); ?>"/>
		<p>
			<button type="button" class="button submit_tracking_info"><?php echo esc_html__( 'Add Tracking', 'woocommerce-paypal-payments' ); ?></button></p>
		<?php
	}
}
