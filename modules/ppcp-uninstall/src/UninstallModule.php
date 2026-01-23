<?php

/**
 * The uninstall module.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Uninstall;

use Exception;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Uninstall\Assets\ClearDatabaseAssets;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * Class UninstallModule
 */
class UninstallModule implements ServiceModule, ExtendingModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * {@inheritDoc}
     */
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $container): bool
    {
        add_action('init', static function () use ($container) {
            $page_id = $container->get('wcgateway.current-ppcp-settings-page-id');
            if (Settings::CONNECTION_TAB_ID === $page_id) {
                $container->get('uninstall.clear-db-assets')->register();
                add_action('admin_enqueue_scripts', array($container->get('uninstall.clear-db-assets'), 'enqueue'));
            }
            $request_data = $container->get('button.request-data');
            $clear_db = $container->get('uninstall.clear-db');
            $clear_db_endpoint = $container->get('uninstall.clear-db-endpoint');
            $option_names = $container->get('uninstall.ppcp-all-option-names');
            $scheduled_action_names = $container->get('uninstall.ppcp-all-scheduled-action-names');
            $action_names = $container->get('uninstall.ppcp-all-action-names');
            self::handleClearDbAjaxRequest($request_data, $clear_db, $clear_db_endpoint, $option_names, $scheduled_action_names, $action_names);
        });
        return \true;
    }
    /**
     * Handles the AJAX request to clear the database.
     *
     * @param RequestData            $request_data The request data helper.
     * @param ClearDatabaseInterface $clear_db Can delete the options and clear scheduled actions from database.
     * @param string                 $nonce The nonce.
     * @param string[]               $option_names The list of option names.
     * @param string[]               $scheduled_action_names The list of scheduled action names.
     * @param string[]               $action_names The list of action names.
     */
    protected static function handleClearDbAjaxRequest(RequestData $request_data, \WooCommerce\PayPalCommerce\Uninstall\ClearDatabaseInterface $clear_db, string $nonce, array $option_names, array $scheduled_action_names, array $action_names): void
    {
        add_action("wc_ajax_{$nonce}", static function () use ($request_data, $clear_db, $nonce, $option_names, $scheduled_action_names, $action_names) {
            try {
                if (!current_user_can('manage_woocommerce')) {
                    wp_send_json_error('Not admin.', 403);
                    return \false;
                }
                // Validate nonce.
                $request_data->read_request($nonce);
                $clear_db->delete_options($option_names);
                $clear_db->clear_scheduled_actions($scheduled_action_names);
                $clear_db->clear_actions($action_names);
                update_option('woocommerce-ppcp-is-new-merchant', '1');
                wp_send_json_success();
                return \true;
            } catch (Exception $error) {
                wp_send_json_error($error->getMessage(), 403);
                return \false;
            }
        });
    }
}
