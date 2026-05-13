<?php

/**
 * REST endpoint to handle the BCDC to ACDC migration.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
/**
 * REST controller for the BCDC to ACDC migration.
 *
 * Handles the one-time, irreversible upgrade from standard card button
 * processing to advanced credit and debit card processing.
 */
class MigrateToAcdcRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'migrate-to-acdc';
    protected PaymentSettings $payment_settings;
    public function __construct(PaymentSettings $payment_settings)
    {
        $this->payment_settings = $payment_settings;
    }
    public function register_routes(): void
    {
        /**
         * POST wc/v3/wc_paypal/migrate-to-acdc
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'handle_migration'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Handles the migration from BCDC to ACDC.
     *
     * Disables the standard card button gateway, enables advanced
     * credit and debit card processing, and clears the BCDC migration
     * override flag option. This operation is irreversible.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response
     */
    public function handle_migration(WP_REST_Request $request): WP_REST_Response
    {
        $this->payment_settings->toggle_method_state(CardButtonGateway::ID, \false);
        $this->payment_settings->toggle_method_state(CreditCardGateway::ID, \true);
        $this->payment_settings->save();
        delete_option(PaymentSettingsMigration::OPTION_NAME_BCDC_MIGRATION_OVERRIDE);
        return $this->return_success(array('success' => \true));
    }
}
