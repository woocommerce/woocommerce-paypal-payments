<?php

/**
 * WooCommerce Payment token for Venmo.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vaulting;

use WC_Payment_Token;
/**
 * Class PaymentTokenVenmo
 */
class PaymentTokenVenmo extends WC_Payment_Token
{
    /**
     * Token Type String.
     *
     * @var string
     */
    protected $type = 'Venmo';
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
    public function set_email($email)
    {
        $this->add_meta_data('email', $email, \true);
    }
}
