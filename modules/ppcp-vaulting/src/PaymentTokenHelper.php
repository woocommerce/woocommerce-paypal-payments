<?php

/**
 * Payment Tokens helper methods.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vaulting;

use WC_Payment_Token;
/**
 * Class PaymentTokenHelper
 */
class PaymentTokenHelper
{
    /**
     * Checks if given token exist as WC Payment Token.
     *
     * @param WC_Payment_Token[] $wc_tokens WC Payment Tokens.
     * @param string             $token_id Payment Token ID.
     * @param ?string            $class_name Class name of the token.
     * @return bool
     */
    public function token_exist(array $wc_tokens, string $token_id, ?string $class_name = null): bool
    {
        foreach ($wc_tokens as $wc_token) {
            if ($wc_token->get_token() === $token_id) {
                if (null !== $class_name) {
                    if ($wc_token instanceof $class_name) {
                        return \true;
                    }
                } else {
                    return \true;
                }
            }
        }
        return \false;
    }
    /**
     * Checks if given token exist as WC Payment Token.
     *
     * @param array  $wc_tokens WC Payment Tokens.
     * @param string $class_name Class name of the token.
     * @return null|WC_Payment_Token
     */
    public function first_token_of_type(array $wc_tokens, string $class_name)
    {
        foreach ($wc_tokens as $wc_token) {
            if ($wc_token instanceof $class_name) {
                return $wc_token;
            }
        }
        return null;
    }
}
