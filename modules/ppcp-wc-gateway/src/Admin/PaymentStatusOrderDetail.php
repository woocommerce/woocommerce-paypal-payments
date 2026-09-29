<?php

/**
 * Renders the not captured information.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Admin
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Admin;

use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class PaymentStatusOrderDetail
 */
class PaymentStatusOrderDetail
{
    /**
     * The capture info column.
     *
     * @var OrderTablePaymentStatusColumn
     */
    private $column;
    /**
     * PaymentStatusOrderDetail constructor.
     *
     * @param OrderTablePaymentStatusColumn $column The capture info column.
     */
    public function __construct(\WooCommerce\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn $column)
    {
        $this->column = $column;
    }
    /**
     * Renders the not captured information.
     *
     * @param int $wc_order_id The WooCommerce order id.
     */
    public function render(int $wc_order_id)
    {
        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order instanceof WC_Order) {
            return;
        }
        if (!$this->column->should_render_for_order($wc_order) || $this->column->is_captured($wc_order)) {
            return;
        }
        printf(
            // @phpcs:ignore Inpsyde.CodeQuality.LineLength.TooLong
            '<li class="wide"><p><mark class="order-status status-on-hold"><span>%1$s</span></mark></p><p>%2$s</p></li>',
            esc_html__('Not captured', 'woocommerce-paypal-payments'),
            esc_html__('To capture the payment select capture action from the list below.', 'woocommerce-paypal-payments')
        );
    }
}
