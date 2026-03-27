<?php

/**
 * Service able to provide transaction url base (URL with the placeholder instead of an actual transaction id)
 * based on the given WC Order.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

/**
 * Class TransactionUrlProvider
 */
class TransactionUrlProvider
{
    /**
     * Transaction URL base used for sandbox payments.
     *
     * @var string
     */
    protected $transaction_url_base_sandbox;
    /**
     * Transaction URL base used for live payments.
     *
     * @var string
     */
    protected $transaction_url_base_live;
    /**
     * TransactionUrlProvider constructor.
     *
     * @param string $transaction_url_base_sandbox URL for sandbox orders.
     * @param string $transaction_url_base_live URL for live orders.
     */
    public function __construct(string $transaction_url_base_sandbox, string $transaction_url_base_live)
    {
        $this->transaction_url_base_sandbox = $transaction_url_base_sandbox;
        $this->transaction_url_base_live = $transaction_url_base_live;
    }
    /**
     * Return transaction url base
     *
     * @param \WC_Order $order WC order to get payment type from.
     *
     * @return string
     */
    public function get_transaction_url_base(\WC_Order $order): string
    {
        $order_payment_mode = $order->get_meta(\WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway::ORDER_PAYMENT_MODE_META_KEY, \true);
        return 'sandbox' === $order_payment_mode ? $this->transaction_url_base_sandbox : $this->transaction_url_base_live;
    }
}
