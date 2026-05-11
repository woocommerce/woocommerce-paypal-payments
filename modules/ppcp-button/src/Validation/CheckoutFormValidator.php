<?php

/**
 * Executes WC checkout validation.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Validation;

use WC_Checkout;
use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WP_Error;
/**
 * Class FormValidator
 */
class CheckoutFormValidator extends WC_Checkout
{
    /**
     * Validates the form data.
     *
     * @param array $data The form data.
     * @return void
     * @throws ValidationException When validation fails.
     */
    public function validate(array $data)
    {
        $errors = new WP_Error();
        // Some plugins check their fields using $_POST or $_REQUEST,
        // also WC terms checkbox https://github.com/woocommerce/woocommerce/issues/35328 .
        foreach ($data as $key => $value) {
            $_POST[$key] = $value;
            $_REQUEST[$key] = $value;
        }
        // Looks like without this WC()->shipping->get_packages() is empty which is used by some plugins.
        WC()->cart->calculate_shipping();
        // Some plugins/filters check is_checkout().
        $is_checkout = function () {
            return \true;
        };
        add_filter('woocommerce_is_checkout', $is_checkout);
        try {
            // And we must call get_posted_data because it handles the shipping address.
            $data = $this->get_posted_data();
            // It throws some notices when checking fields etc., also from other plugins via hooks.
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            @$this->validate_checkout($data, $errors);
        } finally {
            remove_filter('woocommerce_is_checkout', $is_checkout);
        }
        if (apply_filters('woocommerce_paypal_payments_early_wc_checkout_account_creation_validation_enabled', \true) && !is_user_logged_in() && ($this->is_registration_required() || !empty($data['createaccount']))) {
            $username = !empty($data['account_username']) ? $data['account_username'] : '';
            $email = $data['billing_email'] ?? '';
            if (email_exists($email)) {
                $errors->add('registration-error-email-exists', apply_filters(
                    'woocommerce_registration_error_email_exists',
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                    __('An account is already registered with your email address. <a href="#" class="showlogin">Please log in.</a>', 'woocommerce'),
                    $email
                ));
            }
            if ($username) {
                // Empty username is already checked in validate_checkout, and it can be generated.
                $username = sanitize_user($username);
                if (empty($username) || !validate_username($username)) {
                    $errors->add(
                        'registration-error-invalid-username',
                        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                        __('Please enter a valid account username.', 'woocommerce')
                    );
                }
                if (username_exists($username)) {
                    $errors->add(
                        'registration-error-username-exists',
                        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                        __('An account is already registered with that username. Please choose another.', 'woocommerce')
                    );
                }
            }
        }
        // Some plugins call wc_add_notice directly.
        // We should retrieve such notices, and also clear them to avoid duplicates later.
        // TODO: Normally WC converts the messages from validate_checkout into notices,
        // maybe we should do the same for consistency, but it requires lots of changes in the way we handle/output errors.
        $messages = array_merge($errors->get_error_messages(), array_map(function (array $notice): string {
            return $notice['notice'];
        }, wc_get_notices('error')));
        if (wc_notice_count('error') > 0) {
            wc_clear_notices();
        }
        if ($messages) {
            throw new ValidationException($messages);
        }
    }
}
