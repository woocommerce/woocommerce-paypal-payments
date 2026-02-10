<?php

/**
 * Adds availability notice if applicable.
 *
 * @package WooCommerce\PayPalCommerce\Applepay\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Applepay\Helper;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayButton;
use WooCommerce\PayPalCommerce\Applepay\Assets\AppleProductStatus;
/**
 * Class AvailabilityNotice
 */
class AvailabilityNotice
{
    /**
     * The product status handler.
     *
     * @var AppleProductStatus
     */
    private $product_status;
    /**
     * Indicates if we're on the WooCommerce gateways list page.
     *
     * @var bool
     */
    private $is_wc_gateways_list_page;
    /**
     * Indicates if we're on a PPCP Settings page.
     *
     * @var bool
     */
    private $is_ppcp_settings_page;
    /**
     * Indicates if ApplePay is available to be enabled.
     *
     * @var bool
     */
    private $is_available;
    /**
     * Indicates if this server is supported for ApplePay.
     *
     * @var bool
     */
    private $is_server_supported;
    /**
     * Indicates if the merchant is validated for ApplePay.
     *
     * @var bool
     */
    private $is_merchant_validated;
    /**
     * The button.
     *
     * @var ApplePayButton
     */
    private $button;
    /**
     * Class ApmProductStatus constructor.
     *
     * @param AppleProductStatus $product_status The product status handler.
     * @param bool               $is_wc_gateways_list_page Indicates if we're on the WooCommerce gateways list page.
     * @param bool               $is_ppcp_settings_page Indicates if we're on a PPCP Settings page.
     * @param bool               $is_available Indicates if ApplePay is available to be enabled.
     * @param bool               $is_server_supported Indicates if this server is supported for ApplePay.
     * @param bool               $is_merchant_validated Indicates if the merchant is validated for ApplePay.
     * @param ApplePayButton     $button The button.
     */
    public function __construct(AppleProductStatus $product_status, bool $is_wc_gateways_list_page, bool $is_ppcp_settings_page, bool $is_available, bool $is_server_supported, bool $is_merchant_validated, ApplePayButton $button)
    {
        $this->product_status = $product_status;
        $this->is_wc_gateways_list_page = $is_wc_gateways_list_page;
        $this->is_ppcp_settings_page = $is_ppcp_settings_page;
        $this->is_available = $is_available;
        $this->is_server_supported = $is_server_supported;
        $this->is_merchant_validated = $is_merchant_validated;
        $this->button = $button;
    }
    /**
     * Adds availability notice if applicable.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->should_display()) {
            return;
        }
        // We need to check is active before checking failure requests, otherwise failure status won't be set.
        $is_active = $this->product_status->is_active();
        if ($this->product_status->has_request_failure()) {
            $this->add_seller_status_failure_notice();
        } elseif (!$is_active) {
            $this->add_not_available_notice();
        }
        if (!$this->is_available) {
            return;
        }
        if (!$this->is_server_supported) {
            $this->add_server_not_supported_notice();
        }
        $button_enabled = $this->button->is_enabled();
        // We do this check on $_POST because this is called before settings are saved.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['ppcp'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $post_data = wc_clean((array) wp_unslash($_POST['ppcp']));
            $button_enabled = wc_string_to_bool($post_data['applepay_button_enabled'] ?? \false);
        }
        if (!$button_enabled) {
            return;
        }
        if (!$this->is_merchant_validated) {
            $this->add_merchant_not_validated_notice();
        }
    }
    /**
     * Whether the message should display.
     *
     * @return bool
     */
    protected function should_display(): bool
    {
        if (!$this->product_status->is_onboarded()) {
            return \false;
        }
        if (!$this->is_wc_gateways_list_page && !$this->is_ppcp_settings_page) {
            return \false;
        }
        return \true;
    }
    /**
     * Adds seller status failure notice.
     *
     * @return void
     */
    private function add_seller_status_failure_notice(): void
    {
        add_filter(
            Repository::NOTICES_FILTER,
            /**
             * Adds seller status notice.
             *
             * @param array $notices The notices.
             * @return array
             *
             * @psalm-suppress MissingClosureParamType
             */
            static function ($notices): array {
                $message = sprintf(
                    // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
                    __('<p>Notice: We could not determine your PayPal seller status to list your available features. Disconnect and reconnect your PayPal account through our %1$sonboarding process%2$s to resolve this.</p><p>Don\'t worry if you cannot use the %1$sonboarding process%2$s; most functionalities available to your account should work.</p>', 'woocommerce-paypal-payments'),
                    '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#connect-paypal-account" target="_blank">',
                    '</a>'
                );
                // Name the key so it can be overridden in other modules.
                $notices['error_product_status'] = new Message($message, 'warning', \true, 'ppcp-notice-wrapper');
                return $notices;
            }
        );
    }
    /**
     * Adds not available notice.
     *
     * @return void
     */
    private function add_not_available_notice(): void
    {
        add_filter(
            Repository::NOTICES_FILTER,
            /**
             * Adds ApplePay not available notice.
             *
             * @param array $notices The notices.
             * @return array
             *
             * @psalm-suppress MissingClosureParamType
             */
            static function ($notices): array {
                $message = sprintf(__('Apple Pay is not available on your PayPal seller account.', 'woocommerce-paypal-payments'));
                $notices[] = new Message($message, 'warning', \true, 'ppcp-notice-wrapper');
                return $notices;
            }
        );
    }
    /**
     * Adds ApplePay server not supported notice.
     *
     * @return void
     */
    private function add_server_not_supported_notice(): void
    {
        add_filter(
            Repository::NOTICES_FILTER,
            /**
             * Adds ApplePay server not supported notice.
             *
             * @param array $notices The notices.
             * @return array
             *
             * @psalm-suppress MissingClosureParamType
             */
            static function ($notices): array {
                $message = sprintf(__('Apple Pay is not supported on this server. Please contact your hosting provider to enable it.', 'woocommerce-paypal-payments'));
                $notices[] = new Message($message, 'error', \true, 'ppcp-notice-wrapper');
                return $notices;
            }
        );
    }
    /**
     * Adds ApplePay merchant not validated notice.
     *
     * @return void
     */
    private function add_merchant_not_validated_notice(): void
    {
        add_filter(
            Repository::NOTICES_FILTER,
            /**
             * Adds ApplePay merchant not validated notice.
             *
             * @param array $notices The notices.
             * @return array
             *
             * @psalm-suppress MissingClosureParamType
             */
            static function ($notices): array {
                $message = sprintf(
                    // translators: %1$s and %2$s are the opening and closing of HTML <a> tag for the well-known file, %3$s and %4$s are the opening and closing of HTML <a> tag for the help document.
                    __('Apple Pay has not yet been validated. Use the Apple Pay button in your shop for Apple to validate your domain. If this message persists after using the button, please verify your website displays the correct %1$sdomain association file%2$s. More details about the Apple Pay setup can be found in the %3$sPayPal Payments documentation%4$s.', 'woocommerce-paypal-payments'),
                    '<a href="/.well-known/apple-developer-merchantid-domain-association" target="_blank">',
                    '</a>',
                    '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#apple-pay" target="_blank">',
                    '</a>'
                );
                $notices[] = new Message($message, 'error', \true, 'ppcp-notice-wrapper');
                return $notices;
            }
        );
    }
}
