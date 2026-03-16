<?php

/**
 * General plugin settings class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

use Automattic\WooCommerce\Admin\Features\PaymentGatewaySuggestions\DefaultPaymentGateways;
use RuntimeException;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
use WooCommerce\PayPalCommerce\Settings\Enum\InstallationPathEnum;
/**
 * Class GeneralSettings
 *
 * This class serves as a container for managing the common settings that
 * are used and managed in various areas of the settings UI
 *
 * Those settings mainly describe connection details and are initially collected
 * in the onboarding wizard, and also appear in the settings screen.
 */
class GeneralSettings extends \WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel
{
    /**
     * Option key where profile details are stored.
     *
     * @var string
     */
    protected const OPTION_KEY = 'woocommerce-ppcp-data-common';
    /**
     * List of customization flags, provided by the server (read-only).
     *
     * @var array
     */
    protected array $woo_settings = array();
    /**
     * Contexts in which the installation path can be reset.
     */
    private const ALLOWED_RESET_REASONS = array('plugin_uninstall');
    /**
     * Constructor.
     *
     * @param string $country              WooCommerce store country.
     * @param string $currency             WooCommerce store currency.
     * @param bool   $is_send_only_country Whether the store's country is classified as a send-only
     *                                     country.
     *
     * @throws RuntimeException When forgetting to define the OPTION_KEY in this class.
     */
    public function __construct(string $country, string $currency, bool $is_send_only_country)
    {
        parent::__construct();
        $this->woo_settings['country'] = $country;
        $this->woo_settings['currency'] = $currency;
        $this->data['is_send_only_country'] = $is_send_only_country;
        $this->data['merchant_connected'] = $this->is_merchant_connected();
    }
    /**
     * Get default values for the model.
     *
     * @return array
     */
    protected function get_defaults(): array
    {
        return array(
            'use_sandbox' => \false,
            // UI state, not a connection detail.
            'use_manual_connection' => \false,
            // UI state, not a connection detail.
            'is_send_only_country' => \false,
            // Read-only flag.
            // Details about connected merchant account.
            'merchant_connected' => \false,
            'sandbox_merchant' => \false,
            'merchant_id' => '',
            'merchant_email' => '',
            'merchant_country' => '',
            'client_id' => '',
            'client_secret' => '',
            'seller_type' => 'unknown',
            // Branded experience installation path.
            'wc_installation_path' => '',
        );
    }
    // -----
    /**
     * Gets the 'use sandbox' setting.
     *
     * @return bool
     */
    public function get_sandbox(): bool
    {
        return (bool) $this->data['use_sandbox'];
    }
    /**
     * Sets the 'use sandbox' setting.
     *
     * @param bool $use_sandbox Whether to use sandbox mode.
     */
    public function set_sandbox(bool $use_sandbox): void
    {
        $this->data['use_sandbox'] = $use_sandbox;
    }
    /**
     * Gets the 'use manual connection' setting.
     *
     * @return bool
     */
    public function get_manual_connection(): bool
    {
        return (bool) $this->data['use_manual_connection'];
    }
    /**
     * Sets the 'use manual connection' setting.
     *
     * @param bool $use_manual_connection Whether to use manual connection.
     */
    public function set_manual_connection(bool $use_manual_connection): void
    {
        $this->data['use_manual_connection'] = $use_manual_connection;
    }
    /**
     * Returns the list of read-only customization flags.
     *
     * @return array
     */
    public function get_woo_settings(): array
    {
        $settings = $this->woo_settings;
        $settings['own_brand_only'] = $this->own_brand_only();
        return $settings;
    }
    /**
     * Setter to update details of the connected merchant account.
     *
     * @param MerchantConnectionDTO $connection Connection details.
     *
     * @return void
     */
    public function set_merchant_data(MerchantConnectionDTO $connection): void
    {
        $this->data['sandbox_merchant'] = $connection->is_sandbox;
        $this->data['merchant_id'] = sanitize_text_field($connection->merchant_id);
        $this->data['merchant_email'] = sanitize_email($connection->merchant_email);
        $this->data['merchant_country'] = sanitize_text_field($connection->merchant_country);
        $this->data['client_id'] = sanitize_text_field($connection->client_id);
        $this->data['client_secret'] = sanitize_text_field($connection->client_secret);
        $this->data['seller_type'] = sanitize_text_field($connection->seller_type);
        $this->data['merchant_connected'] = $this->is_merchant_connected();
    }
    /**
     * Returns the full merchant connection DTO for the current connection.
     *
     * @return MerchantConnectionDTO All connection details.
     */
    public function get_merchant_data(): MerchantConnectionDTO
    {
        return new MerchantConnectionDTO($this->is_sandbox_merchant(), $this->data['client_id'], $this->data['client_secret'], $this->data['merchant_id'], $this->data['merchant_email'], $this->data['merchant_country'], $this->data['seller_type']);
    }
    /**
     * Reset all connection details to the initial, disconnected state.
     *
     * @return void
     */
    public function reset_merchant_data(): void
    {
        $defaults = $this->get_defaults();
        $this->data['sandbox_merchant'] = $defaults['sandbox_merchant'];
        $this->data['merchant_id'] = $defaults['merchant_id'];
        $this->data['merchant_email'] = $defaults['merchant_email'];
        $this->data['merchant_country'] = $defaults['merchant_country'];
        $this->data['client_id'] = $defaults['client_id'];
        $this->data['client_secret'] = $defaults['client_secret'];
        $this->data['seller_type'] = $defaults['seller_type'];
        $this->data['merchant_connected'] = \false;
    }
    /**
     * Whether the currently connected merchant is a sandbox account.
     *
     * @return bool
     */
    public function is_sandbox_merchant(): bool
    {
        return $this->data['sandbox_merchant'];
    }
    /**
     * Whether the merchant successfully logged into their PayPal account.
     *
     * @return bool
     */
    public function is_merchant_connected(): bool
    {
        return $this->data['merchant_email'] && $this->data['merchant_id'] && $this->data['client_id'] && $this->data['client_secret'];
    }
    /**
     * Whether the merchant uses a business account.
     *
     * Note: It's possible that the seller type is unknown, and both methods,
     * `is_casual_seller()` and `is_business_seller()` return false.
     *
     * @return bool
     */
    public function is_business_seller(): bool
    {
        return SellerTypeEnum::BUSINESS === $this->data['seller_type'];
    }
    /**
     * Whether the merchant is a casual seller using a personal account.
     *
     * Note: It's possible that the seller type is unknown, and both methods,
     * `is_casual_seller()` and `is_business_seller()` return false.
     *
     * @return bool
     */
    public function is_casual_seller(): bool
    {
        return SellerTypeEnum::PERSONAL === $this->data['seller_type'];
    }
    /**
     * Gets the currently connected merchant ID.
     *
     * @return string
     */
    public function get_merchant_id(): string
    {
        return $this->data['merchant_id'];
    }
    /**
     * Gets the currently connected merchant's email.
     *
     * @return string
     */
    public function get_merchant_email(): string
    {
        return $this->data['merchant_email'];
    }
    /**
     * Gets the currently connected merchant's country.
     *
     * @return string
     */
    public function get_merchant_country(): string
    {
        // When we don't know the merchant's real country, we assume it's the Woo store-country.
        if (empty($this->data['merchant_country'])) {
            return $this->woo_settings['country'];
        }
        return $this->data['merchant_country'];
    }
    /**
     * Sets the installation path. This function will only set the installation
     * path a single time and ignore subsequent calls.
     *
     * Short: The installation path cannot be updated once it's defined.
     *
     * @param string $installation_path The installation path.
     *
     * @return void
     */
    public function set_installation_path(string $installation_path): void
    {
        // The installation path can be set only once.
        if (InstallationPathEnum::is_valid($this->data['wc_installation_path'] ?? '')) {
            return;
        }
        // Ignore invalid installation paths.
        if (!$installation_path || !InstallationPathEnum::is_valid($installation_path)) {
            return;
        }
        $this->data['wc_installation_path'] = $installation_path;
    }
    /**
     * Retrieves the installation path. Used for the branded experience.
     *
     * @return string
     */
    public function get_installation_path(): string
    {
        return $this->data['wc_installation_path'] ?? InstallationPathEnum::DIRECT;
    }
    /**
     * Resets the installation path to empty string. This method should only be called
     * during specific circumstances like plugin uninstallation.
     *
     * @param string $reason The reason for resetting the path, must be an allowed value.
     * @return bool Whether the reset was successful.
     */
    public function reset_installation_path(string $reason): bool
    {
        if (!in_array($reason, self::ALLOWED_RESET_REASONS, \true)) {
            return \false;
        }
        $this->data['wc_installation_path'] = '';
        return \true;
    }
    /**
     * Whether the plugin is in the branded-experience mode and shows/enables only
     * payment methods that are PayPal's own brand.
     *
     * @return bool
     */
    public function own_brand_only(): bool
    {
        /**
         * If the current store is not eligible for WooPayments, we have to also show the other payment methods.
         */
        if (!in_array($this->woo_settings['country'], DefaultPaymentGateways::get_wcpay_countries(), \true)) {
            return \false;
        }
        // Temporary dev/test mode.
        $simulate_cookie = sanitize_key(wp_unslash($_COOKIE['simulate-branded-only'] ?? ''));
        if ($simulate_cookie === 'true') {
            return \true;
        } elseif ($simulate_cookie === 'false') {
            return \false;
        }
        $brand_only_paths = array(InstallationPathEnum::CORE_PROFILER, InstallationPathEnum::PAYMENT_SETTINGS);
        return in_array($this->get_installation_path(), $brand_only_paths, \true);
    }
}
