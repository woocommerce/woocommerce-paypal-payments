<?php

/**
 * Fraudnet entity.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\FraudNet;

/**
 * Class FraudNet
 */
class FraudNet
{
    /**
     * The source website ID.
     *
     * @var string
     */
    protected $source_website_id;
    /**
     * FraudNet constructor.
     *
     * @param string $source_website_id The source website ID.
     */
    public function __construct(string $source_website_id)
    {
        $this->source_website_id = $source_website_id;
    }
    /**
     * Returns the Fraudnet session ID.
     *
     * @return string
     */
    public function session_id(): string
    {
        if (WC()->session === null) {
            return '';
        }
        $fraudnet_session_id = WC()->session->get('ppcp_fraudnet_session_id');
        if (is_string($fraudnet_session_id) && $fraudnet_session_id !== '') {
            return $fraudnet_session_id;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['pay_for_order']) && $_GET['pay_for_order'] === 'true') {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $pui_pay_for_order_session_id = wc_clean(wp_unslash($_POST['pui_pay_for_order_session_id'] ?? ''));
            if (is_string($pui_pay_for_order_session_id) && $pui_pay_for_order_session_id !== '') {
                return $pui_pay_for_order_session_id;
            }
        }
        $session_id = bin2hex(random_bytes(16));
        WC()->session->set('ppcp_fraudnet_session_id', $session_id);
        return $session_id;
    }
    /**
     * Returns the source website id.
     *
     * @return string
     */
    public function source_website_id(): string
    {
        return $this->source_website_id;
    }
}
