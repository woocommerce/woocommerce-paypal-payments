<?php

/**
 * The agentic commerce module.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Ingestion\IngestionManager;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\AgenticRestEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Setting\AgenticSettingsModule;
use WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationService;
use WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationEligibility;
use WooCommerce\PayPalCommerce\StoreSync\Setting\AgenticSettingsDataModel;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CartValidationProcessor;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface;
/**
 * Entry point that integrates agentic commerce logic with the plugin's DI system.
 * This module handles the initialization and execution of the agentic commerce functionality.
 */
class StoreSyncModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * A list of all REST services that this module needs to register on init.
     */
    private const REST_ENDPOINT_SERVICES = array('agentic.rest.create_cart', 'agentic.rest.get_cart', 'agentic.rest.replace_cart', 'agentic.rest.checkout');
    /**
     * A list of default cart validation services that verify business rules.
     *
     * Validators are processed in the order they are listed here.
     */
    private const CART_VALIDATION_SERVICES = array('agentic.validator.product', 'agentic.validator.price', 'agentic.validator.inventory', 'agentic.validator.shipping', 'agentic.validator.currency', 'agentic.validator.coupon');
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * Runs the module initialization.
     *
     * @param ContainerInterface $container The dependency injection container.
     * @return bool True if the module was initialized successfully.
     */
    public function run(ContainerInterface $container): bool
    {
        $agentic_settings = $container->get('agentic.settings.model');
        assert($agentic_settings instanceof AgenticSettingsDataModel);
        $registration_handler = $container->get('agentic.registration.handler');
        assert($registration_handler instanceof RegistrationService);
        $eligibility_check = $container->get('agentic.registration.eligibility');
        assert($eligibility_check instanceof RegistrationEligibility);
        $ingestion_manager = $container->get('agentic.ingestion-manager');
        assert($ingestion_manager instanceof IngestionManager);
        // Settings extension always available (merchants need to see the toggle).
        $settings_module = $container->get('agentic.settings.module');
        assert($settings_module instanceof AgenticSettingsModule);
        $settings_module->init();
        // Always register cleanup logic.
        $this->add_cleanup_actions($registration_handler, $ingestion_manager);
        // Sync eligibility cache on init (when WC is available).
        $this->sync_eligibility_cache($agentic_settings, $eligibility_check);
        // Early exit if features should not be initialized.
        if (!$agentic_settings->should_initialize_features()) {
            $this->ensure_deregistered($registration_handler);
            return \true;
        }
        // Feature is active and merchant is eligible: Initialize everything.
        if ($this->should_auto_register()) {
            $this->ensure_registered($registration_handler);
        }
        // Add filter for agentic commerce application context.
        add_filter('ppcp_create_order_request_body_data', static function (array $data, string $payment_method): array {
            if ($payment_method === 'agentic-commerce') {
                $data['application_context'] = array('brand_name' => get_bloginfo('name'), 'locale' => 'en-US', 'landing_page' => 'NO_PREFERENCE', 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW', 'return_url' => home_url('/paypal-return'), 'cancel_url' => home_url('/paypal-cancel'));
            }
            return $data;
        }, 10, 2);
        // Public REST endpoints.
        add_action('rest_api_init', static function () use ($container): void {
            foreach (self::REST_ENDPOINT_SERVICES as $service_id) {
                $endpoint = $container->get($service_id);
                assert($endpoint instanceof AgenticRestEndpoint);
                $endpoint->register_routes();
            }
        });
        add_action('woocommerce_paypal_payments_store_sync_validators', static function (CartValidationProcessor $processor) use ($container) {
            foreach (self::CART_VALIDATION_SERVICES as $service_id) {
                $validator = $container->get($service_id);
                assert($validator instanceof ValidatorInterface);
                $processor->register_validator($validator);
            }
        });
        // Product ingestion.
        add_action('init', static fn() => $ingestion_manager->init());
        // Handle PayPal return/cancel URLs for approval flow.
        // In agentic commerce, these are typically not used by AI agents.
        add_action(
            'template_redirect',
            /**
             * TODO: Temporary test code. This code will be removed soon.
             *
             * @psalm-suppress MixedArgument, MixedArrayAccess, MixedAssignment, PossiblyInvalidArgument
             */
            static function (): void {
                $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
                // Handle PayPal return (approval completed).
                if (strpos($request_uri, '/paypal-return') !== \false) {
                    // phpcs:disable WordPress.Security.NonceVerification.Recommended
                    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
                    $payer_id = isset($_GET['PayerID']) ? sanitize_text_field(wp_unslash($_GET['PayerID'])) : '';
                    // phpcs:enable WordPress.Security.NonceVerification.Recommended
                    wp_die(sprintf('<h1>Payment Approved</h1>
							<p>Your PayPal payment has been approved.</p>
							<p><strong>Order ID:</strong> %s</p>
							<p><strong>Payer ID:</strong> %s</p>
							<p>This transaction is being handled by the AI agent. You can close this window.</p>', esc_html($token), esc_html($payer_id)), 'Payment Approved', array('response' => 200));
                }
                // Handle PayPal cancel.
                if (strpos($request_uri, '/paypal-cancel') !== \false) {
                    // phpcs:disable WordPress.Security.NonceVerification.Recommended
                    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
                    // phpcs:enable WordPress.Security.NonceVerification.Recommended
                    wp_die(sprintf('<h1>Payment Cancelled</h1>
							<p>The PayPal payment was cancelled.</p>
							<p><strong>Order ID:</strong> %s</p>', esc_html($token)), 'Payment Cancelled', array('response' => 200));
                }
            }
        );
        return \true;
    }
    /**
     * Intentionally a separate method to make global cleanup logic stand out.
     */
    private function add_cleanup_actions(RegistrationService $registration_service, IngestionManager $ingestion_manager): void
    {
        // Handle plugin cleanup and remove scheduled task.
        add_action('woocommerce_paypal_payments_store_sync_deregistered', static fn() => $ingestion_manager->clear_recurring_schedule());
        // Disconnect merchant via settings UI (change merchant ID).
        add_action('woocommerce_paypal_payments_merchant_disconnected', static fn() => $registration_service->deregister());
        // Plugin is deactivated.
        add_action('woocommerce_paypal_payments_gateway_deactivate', static fn() => $registration_service->deregister());
        // Plugin is uninstalled.
        add_action('woocommerce_paypal_payments_uninstall', static fn() => $registration_service->deregister());
    }
    private function sync_eligibility_cache(AgenticSettingsDataModel $settings, RegistrationEligibility $eligibility_check): void
    {
        add_action('init', static function () use ($settings, $eligibility_check) {
            if ($settings->is_eligible() === $eligibility_check->is_eligible()) {
                return;
            }
            $settings->set_eligible($eligibility_check->is_eligible());
            $settings->save();
        });
    }
    private function ensure_registered(RegistrationService $registration_service): void
    {
        if ($registration_service->is_registered()) {
            return;
        }
        add_action('init', static fn() => $registration_service->register());
    }
    private function ensure_deregistered(RegistrationService $registration_service): void
    {
        if (!$registration_service->is_registered()) {
            return;
        }
        add_action('init', static fn() => $registration_service->deregister());
    }
    /**
     * Whether the auto-registration is enabled for this site.
     *
     * By default, the plugin automatically registers when the merchant is eligible and the feature
     * is enabled. For testing or troubleshooting, this behavior can be disabled by adding the
     * following constant to wp-config.php:
     *
     *   define( 'PPCP_AGENTIC_AUTO_REGISTER', false );
     */
    private function should_auto_register(): bool
    {
        return !defined('PPCP_AGENTIC_AUTO_REGISTER') || PPCP_AGENTIC_AUTO_REGISTER;
    }
}
