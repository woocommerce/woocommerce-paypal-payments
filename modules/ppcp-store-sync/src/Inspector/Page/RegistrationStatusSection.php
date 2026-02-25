<?php

/**
 * Registration Status Section
 *
 * Handles the display of PayPal Agentic Commerce registration status.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Inspector\Page
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Inspector\Page;

use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\StoreSync\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationEligibility;
use WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationService;
use WooCommerce\PayPalCommerce\StoreSync\Auth\SandboxAuthService;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\CreateCartEndpoint;
/**
 * Class RegistrationStatusSection
 *
 * Renders the registration status information and controls.
 */
class RegistrationStatusSection
{
    use \WooCommerce\PayPalCommerce\StoreSync\Inspector\Page\StatusTableRenderer;
    private RegistrationService $registration_service;
    private RegistrationEligibility $eligibility_check;
    private AuthServiceProvider $auth_provider;
    private GeneralSettings $general_settings;
    public function __construct(RegistrationService $registration_service, RegistrationEligibility $eligibility_check, AuthServiceProvider $auth_provider, GeneralSettings $general_settings)
    {
        $this->registration_service = $registration_service;
        $this->eligibility_check = $eligibility_check;
        $this->auth_provider = $auth_provider;
        $this->general_settings = $general_settings;
    }
    /**
     * Render the registration status section.
     */
    public function render(): void
    {
        $use_auto_register = $this->use_auto_register();
        $is_eligible = $this->eligibility_check->is_eligible();
        $is_registered = $this->registration_service->is_registered();
        $auth_service = $this->auth_provider->auth_service();
        $auth_service_class = get_class($auth_service);
        ?>
		<div class="wrap">
			<h2><?php 
        esc_html_e('Store Sync Status', 'woocommerce-paypal-payments');
        ?></h2>

			<?php 
        $this->render_notices();
        ?>

			<table class="wc_status_table widefat">
				<thead>
				<tr>
					<th colspan="3">
						<?php 
        esc_html_e('Registration Status', 'woocommerce-paypal-payments');
        ?>
					</th>
				</tr>
				</thead>
				<tbody>
				<?php 
        $status_rows = array(array('label' => __('Eligible', 'woocommerce-paypal-payments'), 'value' => $this->render_boolean_badge($is_eligible, __('Eligible', 'woocommerce-paypal-payments'), __('Not eligible', 'woocommerce-paypal-payments')), 'help' => __('Whether this store can use agentic commerce features', 'woocommerce-paypal-payments')), array('label' => __('Auth Service', 'woocommerce-paypal-payments'), 'value' => sprintf('<code>%s</code>', $auth_service_class), 'help' => __('Which implementation verifies the JWK token?', 'woocommerce-paypal-payments')));
        if (SandboxAuthService::class === $auth_service_class) {
            $status_rows[] = array('label' => '', 'value' => $this->render_note(sprintf(
                // translators: The placeholder contains a code snippet for defining a constant.
                __('To test real authentication: Add %s to wp-config.php', 'woocommerce-paypal-payments'),
                '<code>define( "PPCP_AGENTIC_FULL_AUTH", true );</code>'
            )));
        } elseif (defined('PPCP_AGENTIC_FULL_AUTH')) {
            $status_rows[] = array('label' => '', 'value' => $this->render_note(sprintf(
                // translators: The placeholder contains a code snippet for defining a constant.
                __('To use sandbox authentication: Remove %s from wp-config.php', 'woocommerce-paypal-payments'),
                '<code>define( "PPCP_AGENTIC_FULL_AUTH", true );</code>'
            )));
        }
        $status_rows[] = array('label' => __('Status', 'woocommerce-paypal-payments'), 'value' => $this->render_boolean_badge($is_registered, __('Registered', 'woocommerce-paypal-payments'), __('Not registered', 'woocommerce-paypal-payments')), 'help' => __('Is the store registered with the joinhoney service?', 'woocommerce-paypal-payments'));
        if ($use_auto_register) {
            $status_rows[] = array('label' => '', 'value' => $this->render_note(sprintf(
                // translators: The placeholder contains a code snippet for defining a constant.
                __('To disable auto-registration: Add %s to wp-config.php', 'woocommerce-paypal-payments'),
                '<code>define( "PPCP_AGENTIC_AUTO_REGISTER", false );</code>'
            )));
        } else {
            $status_rows[] = array('label' => '', 'value' => function () use ($is_registered): void {
                $this->render_toggle_form($is_registered);
            });
        }
        foreach ($status_rows as $row) {
            $this->render_row($row['label'], $row['value'], $row['help'] ?? '');
        }
        $this->render_registration_data();
        ?>
				</tbody>
			</table>
		</div>
		<?php 
    }
    private function render_registration_data(): void
    {
        $metadata = $this->registration_service->get_registration_data();
        // Meta-data value is "null" when not registered.
        if (!$metadata) {
            return;
        }
        $wc_config = $this->general_settings->get_woo_settings();
        $onboarded_merchant = $this->general_settings->get_merchant_id();
        $rest_endpoint_url = CreateCartEndpoint::endpoint_url();
        $store_identifier = $metadata['wooSydeCommerceId'] ?? '?';
        $merchant_id = $metadata['paypalMerchantId'] ?? '?';
        $store_country = $metadata['country'] ?? '?';
        $store_currency = $metadata['currency'] ?? '?';
        $shipping_countries = (array) ($metadata['shippingCountries'] ?? array());
        $registration_rows = array(array('label' => __('Store URL', 'woocommerce-paypal-payments'), 'value' => $store_identifier, 'help' => __('This store is identified using that URL. It should not change!', 'woocommerce-paypal-payments')), array('label' => __('Agentic Endpoint URL', 'woocommerce-paypal-payments'), 'value' => $rest_endpoint_url), array('label' => __('Merchant ID', 'woocommerce-paypal-payments'), 'value' => $this->render_with_validation($merchant_id, $onboarded_merchant)), array('label' => __('Store Country', 'woocommerce-paypal-payments'), 'value' => $this->render_with_validation($store_country, $wc_config['country'])), array('label' => __('Store Currency', 'woocommerce-paypal-payments'), 'value' => $this->render_with_validation($store_currency, $wc_config['currency'])), array('label' => __('Shipping Countries', 'woocommerce-paypal-payments'), 'value' => implode(', ', $shipping_countries)));
        foreach ($registration_rows as $row) {
            $this->render_row($row['label'], $row['value'], $row['help'] ?? '');
        }
    }
    /**
     * Render the toggle form for registration/unregistration.
     *
     * @param bool $is_registered Whether the merchant is registered.
     */
    private function render_toggle_form(bool $is_registered): void
    {
        if ($is_registered) {
            $action = 'unregister';
            $button_text = __('Unregister', 'woocommerce-paypal-payments');
        } else {
            $action = 'register';
            $button_text = __('Register', 'woocommerce-paypal-payments');
        }
        ?>
		<form method="post" action="<?php 
        echo esc_url(admin_url('admin-post.php'));
        ?>">
			<?php 
        wp_nonce_field('ppcp_agentic_toggle_nonce', 'ppcp_agentic_nonce');
        ?>
			<input type="hidden" name="action" value="ppcp_agentic_toggle_registration" />
			<input type="hidden" name="toggle_action" value="<?php 
        echo esc_attr($action);
        ?>" />
			<button type="submit" class="button button-secondary">
				<?php 
        echo esc_html($button_text);
        ?>
			</button>
		</form>
		<?php 
    }
    /**
     * Render admin notices based on URL parameters.
     */
    private function render_notices(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['ppcp_agentic_notice']) || !is_string($_GET['ppcp_agentic_notice'])) {
            return;
        }
        $notice_type = sanitize_text_field(wp_unslash($_GET['ppcp_agentic_notice']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $messages = array('registered' => __('Successfully registered for PayPal\'s Store Sync.', 'woocommerce-paypal-payments'), 'unregistered' => __('Successfully unregistered from PayPal\'s Store Sync.', 'woocommerce-paypal-payments'), 'error' => __('Failed to update registration status. Please try again.', 'woocommerce-paypal-payments'));
        if (!isset($messages[$notice_type])) {
            return;
        }
        $class = $notice_type === 'error' ? 'error' : 'updated';
        ?>
		<div class="<?php 
        echo esc_attr($class);
        ?> notice is-dismissible">
			<p><?php 
        echo esc_html($messages[$notice_type]);
        ?></p>
		</div>
		<?php 
    }
    /**
     * Mirrors the logic of StoreSyncModule::should_auto_register().
     */
    private function use_auto_register(): bool
    {
        return !defined('PPCP_AGENTIC_AUTO_REGISTER') || PPCP_AGENTIC_AUTO_REGISTER;
    }
}
