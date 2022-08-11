<?php
/**
 * The order tracking MetaBox renderer.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;

/**
 * Class MetaBoxRenderer
 */
class MetaBoxRenderer {

    public const NAME_PREFIX = 'ppcp-tracking';

    /**
     * @var OrderTrackingEndpoint
     */
    protected $orderTrackingEndpoint;

    /**
     * @var string[]
     */
    protected $allowedStatuses;

    /**
     * @var array
     */
    protected $carriers;

    public function __construct(
        OrderTrackingEndpoint $orderTrackingEndpoint,
        array $allowedStatuses,
        array $carriers
    ) {

        $this->orderTrackingEndpoint = $orderTrackingEndpoint;
        $this->allowedStatuses = $allowedStatuses;
        $this->carriers = $carriers;
    }

    /**
     * Renders the order tracking MetaBox.
     *
     */
    public function render( \WP_Post $post) {
        $wc_order = wc_get_order( $post->ID );
        $tracking_info = $this->orderTrackingEndpoint->get_tracking_information($post->ID);

        $tracking_is_not_added = empty($tracking_info);

        $transaction_id = $tracking_info['transaction_id'] ?? $wc_order->get_transaction_id() ?? '';
        $tracking_number = $tracking_info['tracking_number'] ?? '';
        $statusValue = $tracking_info['status'] ?? 'SHIPPED';
        $carrierValue = $tracking_info['carrier'] ?? '';

        $action = $tracking_is_not_added ? 'create' : 'update';
        ?>
        <p>
            <label for="<?= esc_attr(self::NAME_PREFIX);?>-transaction_id"><?= __('Transaction ID','woocommerce-paypal-payments');?></label>
            <input type="text" disabled class="<?= esc_attr(self::NAME_PREFIX);?>-transaction_id" id="<?= esc_attr(self::NAME_PREFIX);?>-transaction_id" name="<?= esc_attr(self::NAME_PREFIX);?>[transaction_id]" value="<?= esc_html($transaction_id);?>"/></p>
        <p>
            <label for="<?= esc_attr(self::NAME_PREFIX);?>-tracking_number"><?= __('Tracking Number','woocommerce-paypal-payments');?></label>
            <input type="text" class="<?= esc_attr(self::NAME_PREFIX);?>-tracking_number" id="<?= esc_attr(self::NAME_PREFIX);?>-tracking_number" name="<?= esc_attr(self::NAME_PREFIX);?>[tracking_number]" value="<?= esc_html($tracking_number);?>"/></p>
        <p>
            <label for="<?= esc_attr(self::NAME_PREFIX);?>-status"><?= __('Status','woocommerce-paypal-payments');?></label>
            <select class="<?= esc_attr(self::NAME_PREFIX);?>-status" id="<?= esc_attr(self::NAME_PREFIX);?>-status" name="<?= esc_attr(self::NAME_PREFIX);?>[status]">
                <?php foreach($this->allowedStatuses as $status):?>
                    <option value="<?= esc_attr($status);?>" <?php selected( $statusValue, $status ); ?>><?= esc_html($status);?></option>
                <?php endforeach;?>
            </select>
        </p>
        <p>
            <label for="ppcp-tracking-carrier"><?= __('Carrier','woocommerce-paypal-payments');?></label>
            <select class="ppcp-tracking-carrier" id="ppcp-tracking-carrier" name="ppcp-tracking[carrier]">
                <?php foreach($this->carriers as $carrier):
                    $country = $carrier['name'] ?? '';
                    $carriers = $carrier['items'] ?? '';
                    ?>
                    <option value=""><?= __('Select Carrier','woocommerce-paypal-payments');?></option>
                    <optgroup label="<?= esc_attr($country);?>">
                        <?php foreach($carriers as $carrier_code => $carrier_name):?>
                            <option value="<?= esc_attr($carrier_code);?>" <?php selected( $carrierValue, $carrier_code ); ?>><?= esc_html($carrier_name);?></option>
                        <?php endforeach;?>
                    </optgroup>
                <?php endforeach;?>
            </select>
        </p>
        <input type="hidden" class="ppcp-order_id" name="<?= esc_attr(self::NAME_PREFIX);?>[order_id]" value="<?= esc_html($post->ID);?>"/>
        <p>
            <button type="button" class="button submit_tracking_info" data-action="<?= esc_attr($action);?>"><?= esc_html(ucfirst($action));?></button></p>
        <?php
    }
}
