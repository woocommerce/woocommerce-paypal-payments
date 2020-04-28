<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Admin;

use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;

class PaymentStatusOrderDetail
{
    public function render(int $wcOrderId)
    {
        $wcOrder = new \WC_Order($wcOrderId);
        $intent = $wcOrder->get_meta(WcGateway::INTENT_META_KEY);
        $captured = $wcOrder->get_meta(WcGateway::CAPTURED_META_KEY);

        if (strcasecmp($intent, 'AUTHORIZE') !== 0) {
            return;
        }

        if (!empty($captured) && wc_string_to_bool($captured)) {
            return;
        }

        printf(
            // @phpcs:ignore Inpsyde.CodeQuality.LineLength.TooLong
            '<li class="wide"><p><mark class="order-status status-on-hold"><span>%1$s</span></mark></p><p>%2$s</p></li>',
            esc_html__(
                'Not captured',
                'woocommerce-paypal-gateway'
            ),
            esc_html__(
                'To capture the payment select capture action from the list below.',
                'woocommerce-paypal-gateway'
            ),
        );
    }
}
