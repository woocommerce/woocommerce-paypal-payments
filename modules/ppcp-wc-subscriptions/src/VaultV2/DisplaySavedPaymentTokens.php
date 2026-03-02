<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2;

use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
class DisplaySavedPaymentTokens
{
    private SettingsProvider $settings_provider;
    private SubscriptionHelper $subscription_helper;
    public function __construct(SettingsProvider $settings_provider, SubscriptionHelper $subscription_helper)
    {
        $this->settings_provider = $settings_provider;
        $this->subscription_helper = $subscription_helper;
    }
    /**
     * Displays saved PayPal payments.
     *
     * @param string $id The payment gateway Id.
     * @param string $description The payment gateway description.
     * @return string
     */
    public function display_saved_paypal_payments(string $id, string $description): string
    {
        if ($this->settings_provider->save_paypal_and_venmo() && PayPalGateway::ID === $id && $this->subscription_helper->is_subscription_change_payment()) {
            $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), PayPalGateway::ID);
            $output = '<ul class="wc-saved-payment-methods">';
            foreach ($tokens as $token) {
                $output .= '<li>';
                $output .= sprintf('<input name="saved_paypal_payment" type="radio" value="%s" style="width:auto;" checked="checked">', $token->get_id());
                $output .= sprintf('<label for="saved_paypal_payment">%s / %s</label>', $token->get_type(), $token->get_meta('email') ?? '');
                $output .= '</li>';
            }
            $output .= '</ul>';
            return $output;
        }
        return $description;
    }
    /**
     * Displays saved credit cards.
     *
     * @param string $id The payment gateway Id.
     * @param array  $default_fields Default payment gateway fields.
     * @return array|mixed|string
     */
    public function display_saved_credit_cards(string $id, array $default_fields)
    {
        if ($this->settings_provider->save_card_details() && $this->subscription_helper->is_subscription_change_payment() && CreditCardGateway::ID === $id) {
            $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), CreditCardGateway::ID);
            $output = sprintf('<p class="form-row form-row-wide"><label>%1$s</label><select id="saved-credit-card" name="saved_credit_card">', esc_html__('Select a saved Credit Card payment', 'woocommerce-paypal-payments'));
            foreach ($tokens as $token) {
                if ($token instanceof WC_Payment_Token_CC) {
                    $output .= sprintf('<option value="%1$s">%2$s ...%3$s</option>', $token->get_id(), $token->get_card_type(), $token->get_last4());
                }
            }
            $output .= '</select></p>';
            $default_fields = array();
            $default_fields['saved-credit-card'] = $output;
            return $default_fields;
        }
        return $default_fields;
    }
}
