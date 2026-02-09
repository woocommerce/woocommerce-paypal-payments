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
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * Class SaveConfig.
 */
class SaveConfig
{
    const ENDPOINT = 'ppc-save-message-config';
    /**
     * The settings.
     *
     * @var Settings
     */
    protected $settings;
    /**
     * The request data.
     *
     * @var RequestData
     */
    protected $request_data;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * SaveConfig constructor.
     *
     * @param Settings        $settings The settings.
     * @param RequestData     $request_data The request data.
     * @param LoggerInterface $logger The logger.
     */
    public function __construct(Settings $settings, RequestData $request_data, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->request_data = $request_data;
        $this->logger = $logger;
    }
    /**
     * Returns the nonce.
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Handles the request.
     */
    public function handle_request(): bool
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Not admin.', 403);
            return \false;
        }
        try {
            $data = $this->request_data->read_request($this->nonce());
            $this->save_config($data['config']['config']);
            wp_send_json_success();
            return \true;
        } catch (Throwable $error) {
            $this->logger->error("SaveConfig execution failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}");
            wp_send_json_error();
            return \false;
        }
    }
    /**
     * Saves the config into the old settings.
     *
     * @param array $config The configurator config.
     */
    public function save_config(array $config): void
    {
        // TODO new-ux: We should convert this to a new AbstractDataModel class in the settings folder!
        $this->settings->set('pay_later_enable_styling_per_messaging_location', \true);
        $this->settings->set('pay_later_messaging_enabled', \true);
        $enabled_locations = array();
        foreach ($config as $placement => $data) {
            $this->save_config_for_location($data, $placement);
            if ($placement === 'custom_placement') {
                $data = $data[0] ?? array();
            }
            if ($data['status'] === 'enabled') {
                $enabled_locations[] = $placement;
            }
        }
        $this->settings->set('pay_later_messaging_locations', $enabled_locations);
        $this->settings->persist();
    }
    /**
     * Saves the config for a location into the old settings.
     *
     * @param array  $config The configurator config for a location.
     * @param string $location The location name in the old settings.
     */
    private function save_config_for_location(array $config, string $location): void
    {
        $this->set_value_if_present($config, 'layout', "pay_later_{$location}_message_layout");
        $this->set_value_if_present($config, 'color', "pay_later_{$location}_message_flex_color");
        $this->set_value_if_present($config, 'ratio', "pay_later_{$location}_message_flex_ratio");
        $this->set_value_if_present($config, 'logo-position', "pay_later_{$location}_message_position");
        $this->set_value_if_present($config, 'logo-type', "pay_later_{$location}_message_logo");
        $this->set_value_if_present($config, 'logo-color', "pay_later_{$location}_message_color");
        $this->set_value_if_present($config, 'text-size', "pay_later_{$location}_message_text_size");
        $this->set_value_if_present($config, 'text-color', "pay_later_{$location}_message_color");
    }
    /**
     * Sets the value in the settings if it is available in the config.
     *
     * @param array  $config The configurator config.
     * @param string $key The key in the config.
     * @param string $settings_key The key in the settings.
     */
    private function set_value_if_present(array $config, string $key, string $settings_key): void
    {
        if (isset($config[$key])) {
            $this->settings->set($settings_key, $config[$key]);
        }
    }
}
