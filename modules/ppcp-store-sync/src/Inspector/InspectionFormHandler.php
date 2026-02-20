<?php

/**
 * PayPal Agentic Commerce Registration Toggle Handler
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Inspector
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Inspector;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationService;
/**
 * Class ToggleHandler
 *
 * Handles manual registration state toggling for inspection purposes.
 */
class InspectionFormHandler
{
    private RegistrationService $registration_service;
    private LoggerInterface $logger;
    public function __construct(RegistrationService $registration_service, LoggerInterface $logger)
    {
        $this->registration_service = $registration_service;
        $this->logger = $logger;
    }
    public function init(): void
    {
        add_action('admin_post_ppcp_agentic_toggle_registration', fn() => $this->handle_toggle());
    }
    /**
     * Handle the registration toggle form submission.
     */
    private function handle_toggle(): void
    {
        $this->logger->info('Inspector: Registration toggle form submitted');
        $toggle_action = $this->verify_request_and_get_action();
        $this->logger->info("Inspector: Toggle action requested: {$toggle_action}");
        $notice = 'error';
        if ($toggle_action === 'register') {
            $notice = $this->handle_registration();
        } elseif ($toggle_action === 'unregister') {
            $notice = $this->handle_unregistration();
        } else {
            $notice = $this->handle_invalid_action($toggle_action);
        }
        $this->redirect_to_status_page($notice);
    }
    /**
     * Verify nonce and user capability, then return the toggle action.
     *
     * @return string The toggle action ('register', 'unregister', or empty string).
     */
    private function verify_request_and_get_action(): string
    {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (empty($_POST['ppcp_agentic_nonce'])) {
            return '';
        }
        // Verify nonce and get toggle action in same method.
        if (!is_string($_POST['ppcp_agentic_nonce']) || !wp_verify_nonce(wp_unslash($_POST['ppcp_agentic_nonce']), 'ppcp_agentic_toggle_nonce')) {
            $this->logger->error('Inspector: Nonce verification failed');
            wp_die(esc_html__('Security check failed.', 'woocommerce-paypal-payments'));
        }
        // Verify toggle action is a string.
        if (empty($_POST['toggle_action']) || !is_string($_POST['toggle_action'])) {
            return '';
        }
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // Check user capability.
        if (!current_user_can('manage_woocommerce')) {
            $this->logger->error('Inspector: User lacks manage_woocommerce capability');
            wp_die(esc_html__('You do not have permission to perform this action.', 'woocommerce-paypal-payments'));
        }
        return sanitize_text_field(wp_unslash($_POST['toggle_action']));
    }
    /**
     * Handle registration attempt.
     *
     * @return string Notice type ('registered' or 'error').
     */
    private function handle_registration(): string
    {
        $this->logger->info('Inspector: Attempting to register with RegistrationService');
        $result = $this->registration_service->register();
        if (is_wp_error($result)) {
            $this->logger->error('Inspector: Registration failed', array('error_code' => $result->get_error_code(), 'error_message' => $result->get_error_message()));
            return 'error';
        }
        $this->logger->info('Inspector: Registration successful', array('result' => $result));
        return 'registered';
    }
    /**
     * Handle unregistration attempt.
     *
     * @return string Notice type ('unregistered' or 'error').
     */
    private function handle_unregistration(): string
    {
        $this->logger->info('Inspector: Attempting to unregister with RegistrationService');
        $result = $this->registration_service->deregister();
        if (is_wp_error($result)) {
            $this->logger->error('Inspector: Unregistration failed', array('error_code' => $result->get_error_code(), 'error_message' => $result->get_error_message()));
            return 'error';
        }
        $this->logger->info('Inspector: Unregistration successful', array('result' => $result));
        return 'unregistered';
    }
    /**
     * Handle invalid toggle action.
     *
     * @param string $toggle_action The invalid action.
     * @return string Notice type ('error').
     */
    private function handle_invalid_action(string $toggle_action): string
    {
        $this->logger->warning("Inspector: Invalid toggle action: {$toggle_action}");
        return 'error';
    }
    /**
     * Redirect back to the status page with a notice.
     *
     * @param string $notice Notice type to display.
     */
    private function redirect_to_status_page(string $notice): void
    {
        $redirect_url = add_query_arg(array('page' => 'wc-status', 'tab' => 'paypal-agentic', 'ppcp_agentic_notice' => $notice), admin_url('admin.php'));
        $this->logger->info("Inspector: Redirecting to status page with notice: {$notice}");
        wp_safe_redirect($redirect_url);
        exit;
    }
}
