<?php

/**
 * The Shipment.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Shipment
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking\Shipment;

use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ItemTrait;
use WooCommerce\PayPalCommerce\OrderTracking\OrderTrackingModule;
/**
 * Class Shipment
 */
class Shipment implements \WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface
{
    use ItemTrait;
    /**
     * The WC order ID.
     *
     * @var int
     */
    private $wc_order_id;
    /**
     * The capture ID.
     *
     * @var string
     */
    protected $capture_id;
    /**
     * The tracking number.
     *
     * @var string
     */
    protected $tracking_number;
    /**
     * The shipment status.
     *
     * @var string
     */
    protected $status;
    /**
     * The shipment carrier.
     *
     * @var string
     */
    protected $carrier;
    /**
     * The shipment carrier name for "OTHER".
     *
     * @var string
     */
    protected $carrier_name_other;
    /**
     * The list of shipment line item IDs.
     *
     * @var int[]
     */
    protected $line_items;
    /**
     * Shipment constructor.
     *
     * @param int    $wc_order_id The WC order ID.
     * @param string $capture_id The capture ID.
     * @param string $tracking_number The tracking number.
     * @param string $status The shipment status.
     * @param string $carrier The shipment carrier.
     * @param string $carrier_name_other The shipment carrier name for "OTHER".
     * @param int[]  $line_items The list of shipment line item IDs.
     */
    public function __construct(int $wc_order_id, string $capture_id, string $tracking_number, string $status, string $carrier, string $carrier_name_other, array $line_items)
    {
        $this->tracking_number = $tracking_number;
        $this->status = $status;
        $this->carrier = $carrier;
        $this->carrier_name_other = $carrier_name_other;
        $this->line_items = $line_items;
        $this->capture_id = $capture_id;
        $this->wc_order_id = $wc_order_id;
    }
    /**
     * {@inheritDoc}
     */
    public function capture_id(): string
    {
        return $this->capture_id;
    }
    /**
     * {@inheritDoc}
     */
    public function tracking_number(): string
    {
        return $this->tracking_number;
    }
    /**
     * {@inheritDoc}
     */
    public function status(): string
    {
        return $this->status;
    }
    /**
     * {@inheritDoc}
     */
    public function carrier(): string
    {
        return $this->carrier;
    }
    /**
     * {@inheritDoc}
     */
    public function carrier_name_other(): string
    {
        return $this->carrier_name_other;
    }
    /**
     * {@inheritDoc}
     */
    public function line_items(): array
    {
        $wc_order = wc_get_order($this->wc_order_id);
        if (!$wc_order instanceof WC_Order) {
            return array();
        }
        $wc_order_items = $wc_order->get_items();
        $tracking_meta = $wc_order->get_meta(OrderTrackingModule::PPCP_TRACKING_INFO_META_NAME);
        $saved_line_items = $tracking_meta[$this->tracking_number()] ?? array();
        $line_items = $this->line_items ?: $saved_line_items;
        $tracking_items = array();
        foreach ($wc_order_items as $item) {
            assert($item instanceof WC_Order_Item_Product);
            if (!empty($line_items) && !in_array($item->get_id(), $line_items, \true)) {
                continue;
            }
            $product = $item->get_product();
            if (!is_a($product, WC_Product::class)) {
                continue;
            }
            $currency = $wc_order->get_currency();
            $quantity = (int) $item->get_quantity();
            $price_without_tax = (float) $wc_order->get_item_subtotal($item, \false);
            $price_without_tax_rounded = round($price_without_tax, 2);
            $image = wp_get_attachment_image_src((int) $product->get_image_id(), 'full');
            $ppcp_order_item = new Item($this->prepare_item_string($item->get_name()), new Money($price_without_tax_rounded, $currency), $quantity, $this->prepare_item_string($product->get_description()), null, $this->prepare_sku($product->get_sku()), $product->is_virtual() ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS, $product->get_permalink(), $image[0] ?? '');
            $tracking_items[$item->get_id()] = $ppcp_order_item->to_array();
        }
        return $tracking_items;
    }
    /**
     * {@inheritDoc}
     */
    public function render(array $allowed_statuses): void
    {
        $carrier = $this->carrier();
        $tracking_number = $this->tracking_number();
        $carrier_name_other = $this->carrier_name_other();
        ?>
		<div class="ppcp-shipment closed">
			<div class="ppcp-shipment-header">
				<h4><?php 
        echo esc_html__('Shipment: ', 'woocommerce-paypal-payments');
        echo esc_html($tracking_number);
        ?></h4>
				<button type="button" class="shipment-toggle-indicator" aria-expanded="false">
					<span class="toggle-indicator" aria-hidden="true"></span>
				</button>
			</div>
			<div class="ppcp-shipment-info hidden">
				<p><strong><?php 
        echo esc_html__('Tracking Number:', 'woocommerce-paypal-payments');
        ?></strong> <span><?php 
        echo esc_html($tracking_number);
        ?></span></p>
				<p><strong><?php 
        echo esc_html__('Carrier:', 'woocommerce-paypal-payments');
        ?></strong> <span><?php 
        echo esc_html($carrier);
        ?></span></p>
				<?php 
        if ($carrier === 'OTHER') {
            ?>
					<p><strong><?php 
            echo esc_html__('Carrier Name:', 'woocommerce-paypal-payments');
            ?></strong> <span><?php 
            echo esc_html($carrier_name_other);
            ?></span></p>
				<?php 
        }
        ?>
				<?php 
        $this->render_shipment_line_item_info();
        ?>
				<label for="ppcp-shipment-status"><?php 
        echo esc_html__('Status', 'woocommerce-paypal-payments');
        ?></label>
				<select class="wc-enhanced-select ppcp-shipment-status" id="ppcp-shipment-status" name="ppcp-shipment-status">
					<?php 
        foreach ($allowed_statuses as $status_key => $status) {
            ?>
						<option value="<?php 
            echo esc_attr($status_key);
            ?>" <?php 
            selected($status_key, $this->status());
            ?>><?php 
            echo esc_html($status);
            ?></option>
					<?php 
        }
        ?>
				</select>
				<input type="hidden" class="ppcp-shipment-tacking_number" name="ppcp-shipment-tacking_number" value="<?php 
        echo esc_html($tracking_number);
        ?>"/>
				<input type="hidden" class="ppcp-shipment-carrier" name="ppcp-shipment-carrier" value="<?php 
        echo esc_html($carrier);
        ?>"/>
				<input type="hidden" class="ppcp-shipment-carrier-other" name="ppcp-shipment-carrier-other" value="<?php 
        echo esc_html($carrier_name_other);
        ?>"/>
				<button type="button" class="button button-disabled update_shipment"><?php 
        echo esc_html__('Update Status', 'woocommerce-paypal-payments');
        ?></button>
			</div>
		</div>
		<?php 
    }
    /**
     * {@inheritDoc}
     */
    public function to_array(): array
    {
        $shipment = array('capture_id' => $this->capture_id(), 'tracking_number' => $this->tracking_number(), 'status' => $this->status(), 'carrier' => $this->carrier(), 'items' => array_values($this->line_items()));
        if (!empty($this->carrier_name_other())) {
            $shipment['carrier_name_other'] = $this->carrier_name_other();
        }
        return $shipment;
    }
    /**
     * Renders the shipment line items info.
     *
     * @return void
     */
    protected function render_shipment_line_item_info(): void
    {
        $line_items = $this->line_items();
        if (empty($line_items)) {
            return;
        }
        $format = '<p><strong>%1$s</strong> <span>%2$s</span></p>';
        $order_items_info = array();
        foreach ($this->line_items() as $shipment_line_item) {
            $sku = $shipment_line_item['sku'] ?? '';
            $name = $shipment_line_item['name'] ?? '';
            $sku_markup = sprintf('#<span class="wc-order-item-sku">%1$s</span>', esc_html($sku));
            $order_items_info_markup = sprintf('<strong>%1$s</strong>%2$s', esc_html($name), $sku ? $sku_markup : '');
            $order_items_info[] = $order_items_info_markup;
        }
        printf(
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $format,
            esc_html__('Shipped Products:', 'woocommerce-paypal-payments'),
            wp_kses_post(implode(', ', $order_items_info))
        );
    }
}
