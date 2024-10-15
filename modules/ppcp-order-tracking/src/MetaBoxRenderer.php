<?php
/**
 * The order tracking MetaBox renderer.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;

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
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * MetaBoxRenderer constructor.
	 *
	 * @param string[]              $allowed_statuses Allowed shipping statuses.
	 * @param array                 $carriers Available shipping carriers.
	 * @psalm-param Carriers        $carriers
	 * @param OrderTrackingEndpoint $order_tracking_endpoint The order tracking endpoint.
	 * @param bool                  $should_use_new_api Whether new API should be used.
	 * @param LoggerInterface       $logger The logger.
	 */
	public function __construct(
		array $allowed_statuses,
		array $carriers,
		OrderTrackingEndpoint $order_tracking_endpoint,
		bool $should_use_new_api,
		LoggerInterface $logger
	) {

		$this->allowed_statuses        = $allowed_statuses;
		$this->carriers                = $carriers;
		$this->order_tracking_endpoint = $order_tracking_endpoint;
		$this->should_use_new_api      = $should_use_new_api;
		$this->logger                  = $logger;
	}

	/**
	 * Renders the order tracking MetaBox.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @param string   $capture_id The PayPal order capture ID.
	 */
	public function render( WC_Order $wc_order, string $capture_id ): void {
		$order_items      = $wc_order->get_items();
		$order_item_count = ! empty( $order_items ) ? count( $order_items ) : 0;

		try {
			/**
			 * The shipments
			 *
			 * @var ShipmentInterface[] $shipments
			 */
			$shipments = $this->order_tracking_endpoint->list_tracking_information( $wc_order->get_id() ) ?? array();
		} catch ( Exception $exception ) {
			$this->logger->log( 'warning', $exception->getMessage() );
			return;
		}
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
									<option value="<?php echo intval( $item->get_id() ); ?>"><?php echo esc_attr( wp_strip_all_tags( $item->get_name() ) ); ?></option>
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
