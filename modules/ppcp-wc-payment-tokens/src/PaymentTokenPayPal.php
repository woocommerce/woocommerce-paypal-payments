<?php

/**
 * WooCommerce Payment token for PayPal.
 *
 * @package WooCommerce\PayPalCommerce\WcPaymentTokens
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcPaymentTokens;

use WC_Payment_Token;
/**
 * Class PaymentTokenPayPal
 */
class PaymentTokenPayPal extends WC_Payment_Token
{
    /**
     * Token Type String.
     *
     * @var string
     */
    protected $type = 'PayPal';
    /**
     * Extra data.
     *
     * @var string[]
     */
    protected $extra_data = array('email' => '');
    /**
     * Get PayPal account email.
     *
     * @return string PayPal account email.
     */
    public function get_email()
    {
        return $this->get_meta('email');
    }
    /**
     * Set PayPal account email.
     *
     * @param string $email PayPal account email.
     */
    public function set_email($email): void
    {
        $this->add_meta_data('email', $email, \true);
    }
    /**
     * Returns a display name for the token.
     *
     * @param string $deprecated Deprecated parameter.
     * @return string
     */
    public function get_display_name($deprecated = ''): string
    {
        $email = $this->get_email();
        return $email ? 'PayPal / ' . $email : 'PayPal';
    }
}
