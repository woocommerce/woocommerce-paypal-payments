<?php
/**
 * The order tracking MetaBox renderer.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use Exception;
use WC_Order;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_Post;

use function WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order;

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

	use TransactionIdHandlingTrait;

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
	 * The order tracking endpoint.
	 *
	 * @var OrderTrackingEndpoint
	 */
	protected $order_tracking_endpoint;

	/**
	 * Whether new API should be used.
	 *
	 * @var bool
	 */
	protected $should_use_new_api;

	/**
	 * MetaBoxRenderer constructor.
	 *
	 * @param string[]              $allowed_statuses Allowed shipping statuses.
	 * @param array                 $carriers Available shipping carriers.
	 * @psalm-param Carriers        $carriers
	 * @param OrderTrackingEndpoint $order_tracking_endpoint The order tracking endpoint.
	 * @param bool                  $should_use_new_api Whether new API should be used.
	 */
	public function __construct(
		array $allowed_statuses,
		array $carriers,
		OrderTrackingEndpoint $order_tracking_endpoint,
		bool $should_use_new_api
	) {

		$this->allowed_statuses        = $allowed_statuses;
		$this->carriers                = $carriers;
		$this->order_tracking_endpoint = $order_tracking_endpoint;
		$this->should_use_new_api      = $should_use_new_api;
	}

	/**
	 * Renders the order tracking MetaBox.
	 *
	 * @param mixed $post_or_order_object Either WP_Post or WC_Order when COT is data source.
	 */
	public function render( $post_or_order_object ): void {
		$wc_order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		if ( ! $wc_order instanceof WC_Order ) {
			return;
		}

		try {
			$paypal_order = ppcp_get_paypal_order( $wc_order );
			$capture_id   = $this->get_paypal_order_transaction_id( $paypal_order );
		} catch ( Exception $exception ) {
			return;
		}

		$order_items      = $wc_order->get_items();
		$order_item_count = ! empty( $order_items ) ? count( $order_items ) : 0;

		/**
		 * The shipments
		 *
		 * @var ShipmentInterface[] $shipments
		 */
		$shipments = $this->order_tracking_endpoint->list_tracking_information( $wc_order->get_id() ) ?? array();
		?>
		<div class="ppcp-tracking-columns-wrapper">
			<div class="ppcp-tracking-column">
				<h3><?php echo esc_html__( 'Share Package Tracking Data with PayPal', 'woocommerce-paypal-payments' ); ?></h3>
				<p>
					<label for="ppcp-tracking-capture_id"><?php echo esc_html__( 'Capture ID', 'woocommerce-paypal-payments' ); ?></label>
					<input type="text" disabled class="ppcp-tracking-capture_id disabled" id="ppcp-tracking-capture_id" name="ppcp-tracking[capture_id]" value="<?php echo esc_attr( $capture_id ); ?>" />
				</p>
				<?php if ( $order_item_count > 1 && $this->should_use_new_api ) : ?>
					<p>
						<label for="include-all-items"><?php echo esc_html__( 'Include All Products', 'woocommerce-paypal-payments' ); ?></label>
						<input type="checkbox" id="include-all-items" checked>
						<div id="items-select-container">
							<label for="ppcp-tracking-items"><?php echo esc_html__( 'Select items for this shipment', 'woocommerce-paypal-payments' ); ?></label>
							<select multiple class="wc-enhanced-select ppcp-tracking-items" id="ppcp-tracking-items" name="ppcp-tracking[items]">
								<?php foreach ( $order_items as $item ) : ?>
									<option value="<?php echo intval( $item->get_id() ); ?>"><?php echo esc_html( $item->get_name() ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</p>
				<?php endif; ?>
				<p>
					<label for="ppcp-tracking-tracking_number"><?php echo esc_html__( 'Tracking Number*', 'woocommerce-paypal-payments' ); ?></label>
					<input type="text" class="ppcp-tracking-tracking_number" id="ppcp-tracking-tracking_number" name="ppcp-tracking[tracking_number]" maxlength="64" />
				</p>
				<p>
					<label for="ppcp-tracking-status"><?php echo esc_html__( 'Status', 'woocommerce-paypal-payments' ); ?></label>
					<select class="wc-enhanced-select ppcp-tracking-status" id="ppcp-tracking-status" name="ppcp-tracking[status]">
						<?php foreach ( $this->allowed_statuses as $status_key => $status ) : ?>
							<option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="ppcp-tracking-carrier"><?php echo esc_html__( 'Carrier', 'woocommerce-paypal-payments' ); ?></label>
					<select class="wc-enhanced-select ppcp-tracking-carrier" id="ppcp-tracking-carrier" name="ppcp-tracking[carrier]">
						<?php
						foreach ( $this->carriers as $carrier ) :
							if ( empty( $carrier ) ) {
								continue;
							}
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
				<p class="hidden">
					<label for="ppcp-tracking-carrier_name_other"><?php echo esc_html__( 'Carrier Name*', 'woocommerce-paypal-payments' ); ?></label>
					<input type="text" class="ppcp-tracking-carrier_name_other" id="ppcp-tracking-carrier_name_other" name="ppcp-tracking[carrier_name_other]" />
				</p>
				<input type="hidden" class="ppcp-tracking-order_id" name="ppcp-tracking[order_id]" value="<?php echo (int) $wc_order->get_id(); ?>"/>
				<p><button type="button" class="button submit_tracking_info"><?php echo esc_html__( 'Add Package Tracking', 'woocommerce-paypal-payments' ); ?></button></p>
			</div>
			<div class="ppcp-tracking-column shipments">
				<h3><?php echo esc_html__( 'PayPal Package Tracking Status', 'woocommerce-paypal-payments' ); ?></h3>
				<?php
				foreach ( $shipments as $shipment ) {
					$shipment->render( $this->allowed_statuses );
				}
				?>
				<?php if ( empty( $shipments ) ) : ?>
					<p class="ppcp-tracking-no-shipments">
						<?php echo esc_html__( 'Package Tracking data has not been shared with PayPal on this order.', 'woocommerce-paypal-payments' ); ?><br>
						<?php echo esc_html__( 'Add tracking details on this order to qualify for PayPal Seller Protection, faster holds release and automated dispute resolution.', 'woocommerce-paypal-payments' ); ?>
						<p class="ppcp-tracking-link-to-docs">
							<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#package-tracking" target="_blank">
								<?php echo esc_html__( 'Discover full benefits of PayPal Package Tracking here.', 'woocommerce-paypal-payments' ); ?>
							</a>
						</p>
					</p>
				<?php endif; ?>
			</div>
			<div class="blockUI blockOverlay ppcp-tracking-loader"></div>
		</div>
		<?php
	}
}
