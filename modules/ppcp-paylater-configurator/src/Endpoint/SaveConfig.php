<?php

/**
 * The endpoint for saving the Pay Later messaging config from the configurator.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
/**
 * Class SaveConfig.
 */
class SaveConfig
{
    const ENDPOINT = 'ppc-save-message-config';
    protected PayLaterMessagingSettings $settings;
    protected RequestData $request_data;
    private LoggerInterface $logger;
    public function __construct(PayLaterMessagingSettings $settings, RequestData $request_data, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->request_data = $request_data;
        $this->logger = $logger;
    }
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Handles the request.
     */
    public function handle_request(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Not admin.', 403);
        }
        try {
            $data = $this->request_data->read_request($this->nonce());
            $this->save_config($data['config']['config']);
            wp_send_json_success();
        } catch (NonceValidationException $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        } catch (Throwable $error) {
            $this->logger->error("SaveConfig execution failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}");
            wp_send_json_error();
        }
    }
    public function save_config(array $config): void
    {
        $this->settings->set_styling_per_location(\true);
        $this->settings->set_messaging_enabled(\true);
        $enabled_locations = array();
        foreach ($config as $placement => $data) {
            if ($placement === 'custom_placement') {
                $data = $data[0] ?? array();
            }
            $this->settings->set_location_from_config($placement, $data);
            if (($data['status'] ?? '') === 'enabled') {
                $enabled_locations[] = $placement;
            }
        }
        $this->settings->set_messaging_locations($enabled_locations);
        $this->settings->save();
    }
}
