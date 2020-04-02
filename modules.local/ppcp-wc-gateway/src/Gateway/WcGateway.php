<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;


use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\Session\SessionHandler;

class WcGateway extends \WC_Payment_Gateway
{

    const ID = 'ppcp-gateway';

    private $isSandbox = true;
    private $sessionHandler;
    private $endpoint;

    public function __construct(SessionHandler $sessionHandler, OrderEndpoint $endpoint)
    {
        $this->sessionHandler = $sessionHandler;
        $this->endpoint = $endpoint;
        $this->id = self::ID;
        $this->method_title = __('PayPal Payments', 'woocommerce-paypal-gateway');
        $this->method_description = __('Provide your customers with the PayPal payment system', 'woocommerce-paypal-gateway');
        $this->init_form_fields();
        $this->init_settings();

        $this->isSandbox = $this->get_option( 'sandbox_on', 'yes' ) === 'yes';

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woocommerce-paypal-gateway' ),
                'type' => 'checkbox',
                'label' => __( 'Enable PayPal Payments', 'woocommerce-paypal-gateway' ),
                'default' => 'yes'
            ),
            'sandbox_on' => array(
                'title' => __( 'Enable Sandbox', 'woocommerce-paypal-gateway' ),
                'type' => 'checkbox',
                'label' => __( 'For testing your integration, you can enable the sandbox.', 'woocommerce-paypal-gateway' ),
                'default' => 'yes'
            ),
        ];
    }

    function process_payment( $order_id ) : ?array
    {
        global $woocommerce;
        $wcOrder = new \WC_Order( $order_id );

        //ToDo: We need to fetch the order from paypal again to get it with the new status.
        $order = $this->sessionHandler->order();
        $errorMessage = null;
        if (! $order || ! $order->isApproved()) {
            $errorMessage = 'not approve yet';

        }
        $errorMessage = null;
        if ($errorMessage) {
            wc_add_notice( __('Payment error:', 'woocommerce-paypal-gateway') . $errorMessage, 'error' );
            return null;
        }

        /**
         * ToDo: If shipping or something else changes, we need to patch the order!
         * We should also handle the shipping address and update if needed
         * @see https://developer.paypal.com/docs/api/orders/v2/#orders_patch
         **/
        $order = $this->endpoint->capture($order);

        $wcOrder->update_status('on-hold', __( 'Awaiting payment.', 'woocommerce-paypal-gateway' ));
        if ($order->isCompleted()) {
            $wcOrder->update_status('processing', __( 'Payment received.', 'woocommerce-paypal-gateway' ));
        }
        $woocommerce->cart->empty_cart();

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $wcOrder )
        );
    }
}