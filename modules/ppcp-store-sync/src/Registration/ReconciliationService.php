<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Registration;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Setting\AgenticSettingsDataModel;
/**
 * Aligns the store's registration with the current settings: registers when the
 * feature is enabled but the store is not registered, and deregisters when it is
 * no longer enabled. Reconciliation is idempotent, so calling it when the desired
 * and actual states already match does nothing.
 */
class ReconciliationService
{
    private AgenticSettingsDataModel $settings;
    private \WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationService $registration;
    private LoggerInterface $logger;
    public function __construct(AgenticSettingsDataModel $settings, \WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationService $registration, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->registration = $registration;
        $this->logger = $logger;
    }
    /**
     * Aligns the registration state with the current settings.
     *
     * This is convergent: a failed registration disables the feature, so a
     * subsequent run sees the desired and actual states already matching and does
     * nothing.
     */
    public function reconcile(): void
    {
        $desired = $this->settings->should_initialize_features();
        $actual = $this->registration->is_registered();
        // State has not changed, do nothing.
        if ($desired === $actual) {
            return;
        }
        // Integration was disabled, clean up and unregister.
        if (!$desired) {
            $this->registration->deregister();
            return;
        }
        // Integration is enabled, let's check the auto-register flag before acting.
        if ($this->should_auto_register()) {
            $this->register();
        }
    }
    /**
     * Registers the store, disabling the feature when registration fails.
     *
     * A failure is never retried automatically: re-enabling the toggle is the
     * merchant's explicit retry mechanism.
     */
    private function register(): void
    {
        $result = $this->registration->register();
        if (!is_wp_error($result)) {
            return;
        }
        $this->settings->set_active(\false);
        $this->settings->save();
        $this->logger->warning('Store sync auto-disabled after registration failure', array('error' => $result->get_error_message()));
    }
    /**
     * Whether auto-registration is enabled for this site.
     *
     * Disable for testing or troubleshooting by adding the following constant to
     * wp-config.php:
     *
     *   define( 'PPCP_AGENTIC_AUTO_REGISTER', false );
     */
    private function should_auto_register(): bool
    {
        return !defined('PPCP_AGENTIC_AUTO_REGISTER') || PPCP_AGENTIC_AUTO_REGISTER;
    }
}
