<?php

/**
 * The blocks module.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WC_AJAX;
use WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
/**
 * Class PayPalPaymentMethod
 */
class PayPalPaymentMethod extends AbstractPaymentMethodType
{
    /**
     * The URL of this module.
     *
     * @var string
     */
    private $module_url;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * The smart button script loading handler.
     *
     * @var SmartButtonInterface|callable
     */
    private $smart_button;
    /**
     * The settings.
     *
     * @var Settings
     */
    private $plugin_settings;
    /**
     * The Settings status helper.
     *
     * @var SettingsStatus
     */
    protected $settings_status;
    /**
     * The WC gateway.
     *
     * @var PayPalGateway
     */
    private $gateway;
    /**
     * Whether the final review is enabled.
     *
     * @var bool
     */
    private $final_review_enabled;
    /**
     * The cancellation view.
     *
     * @var CancelView
     */
    private $cancellation_view;
    /**
     * The Session handler.
     *
     * @var SessionHandler
     */
    private $session_handler;
    /**
     * The Subscription Helper.
     *
     * @var SubscriptionHelper
     */
    private $subscription_helper;
    /**
     * Whether to create a non-express method with the standard "Place order" button.
     *
     * @var bool
     */
    protected $add_place_order_method;
    /**
     * Whether to use the standard "Place order" button instead of PayPal buttons.
     *
     * @var bool
     */
    protected $use_place_order;
    /**
     * The text for the standard "Place order" button.
     *
     * @var string
     */
    protected $place_order_button_text;
    /**
     * The text for additional "Place order" description.
     *
     * @var string
     */
    protected $place_order_button_description;
    /**
     * All existing funding sources for PayPal buttons.
     *
     * @var array
     */
    private $all_funding_sources;
    /**
     * Assets constructor.
     *
     * @param string                        $module_url The url of this module.
     * @param string                        $version    The assets version.
     * @param SmartButtonInterface|callable $smart_button The smart button script loading handler.
     * @param Settings                      $plugin_settings The settings.
     * @param SettingsStatus                $settings_status The Settings status helper.
     * @param PayPalGateway                 $gateway The WC gateway.
     * @param bool                          $final_review_enabled Whether the final review is enabled.
     * @param CancelView                    $cancellation_view The cancellation view.
     * @param SessionHandler                $session_handler The Session handler.
     * @param SubscriptionHelper            $subscription_helper The subscription helper.
     * @param bool                          $add_place_order_method Whether to create a non-express method with the standard "Place order" button.
     * @param bool                          $use_place_order Whether to use the standard "Place order" button instead of PayPal buttons.
     * @param string                        $place_order_button_text The text for the standard "Place order" button.
     * @param string                        $place_order_button_description The text for additional "Place order" description.
     * @param array                         $all_funding_sources All existing funding sources for PayPal buttons.
     */
    public function __construct(string $module_url, string $version, $smart_button, Settings $plugin_settings, SettingsStatus $settings_status, PayPalGateway $gateway, bool $final_review_enabled, CancelView $cancellation_view, SessionHandler $session_handler, SubscriptionHelper $subscription_helper, bool $add_place_order_method, bool $use_place_order, string $place_order_button_text, string $place_order_button_description, array $all_funding_sources)
    {
        $this->name = PayPalGateway::ID;
        $this->module_url = $module_url;
        $this->version = $version;
        $this->smart_button = $smart_button;
        $this->plugin_settings = $plugin_settings;
        $this->settings_status = $settings_status;
        $this->gateway = $gateway;
        $this->final_review_enabled = $final_review_enabled;
        $this->cancellation_view = $cancellation_view;
        $this->session_handler = $session_handler;
        $this->subscription_helper = $subscription_helper;
        $this->add_place_order_method = $add_place_order_method;
        $this->use_place_order = $use_place_order;
        $this->place_order_button_text = $place_order_button_text;
        $this->place_order_button_description = $place_order_button_description;
        $this->all_funding_sources = $all_funding_sources;
    }
    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
    }
    /**
     * {@inheritDoc}
     */
    public function is_active()
    {
        // Do not load when definitely not needed,
        // but we still need to check the locations later and handle in JS
        // because has_block cannot be called here (too early).
        return $this->plugin_settings->has('enabled') && $this->plugin_settings->get('enabled');
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script('ppcp-checkout-block', trailingslashit($this->module_url) . 'assets/js/checkout-block.js', array(), $this->version, \true);
        return array('ppcp-checkout-block');
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_data()
    {
        $script_data = $this->smart_button()->script_data();
        if (isset($script_data['continuation'])) {
            $url = add_query_arg(array(CancelController::NONCE => wp_create_nonce(CancelController::NONCE)), wc_get_checkout_url());
            $script_data['continuation']['cancel'] = array('html' => $this->cancellation_view->render_session_cancellation($url, $this->session_handler->funding_source()));
            $order = $this->session_handler->order();
            if ($order) {
                $script_data['continuation']['order'] = $order->to_array();
            }
        }
        $funding_sources = array();
        if (!$this->is_editing()) {
            $disabled_funding_sources = explode(',', $script_data['url_params']['disable-funding'] ?? '') ?: array();
            $funding_sources = array_values(array_diff(array_keys($this->all_funding_sources), $disabled_funding_sources));
        }
        $smart_buttons_enabled = !$this->use_place_order && $this->settings_status->is_smart_button_enabled_for_location($script_data['context'] ?? 'block-checkout');
        $place_order_enabled = ($this->use_place_order || $this->add_place_order_method) && !$this->subscription_helper->cart_contains_subscription();
        $cart = WC()->cart;
        return array('id' => $this->gateway->id, 'title' => $this->gateway->title, 'icon' => array(array('id' => 'paypal', 'alt' => 'PayPal', 'src' => $this->gateway->icon)), 'description' => $this->gateway->get_description(), 'smartButtonsEnabled' => $smart_buttons_enabled, 'placeOrderEnabled' => $place_order_enabled, 'fundingSource' => $this->session_handler->funding_source(), 'finalReviewEnabled' => $this->final_review_enabled, 'placeOrderButtonText' => $this->place_order_button_text, 'placeOrderButtonDescription' => $this->place_order_button_description, 'enabledFundingSources' => $funding_sources, 'ajax' => array('update_shipping' => array('endpoint' => WC_AJAX::get_endpoint(UpdateShippingEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(UpdateShippingEndpoint::nonce()))), 'scriptData' => $script_data, 'needShipping' => $cart && $cart->needs_shipping());
    }
    /**
     * Checks if it is the block editing mode.
     */
    private function is_editing(): bool
    {
        if (!function_exists('get_current_screen')) {
            return \false;
        }
        $screen = get_current_screen();
        return $screen && $screen->is_block_editor();
    }
    /**
     * The smart button.
     *
     * @return SmartButtonInterface
     */
    private function smart_button(): SmartButtonInterface
    {
        if ($this->smart_button instanceof SmartButtonInterface) {
            return $this->smart_button;
        }
        if (is_callable($this->smart_button)) {
            $this->smart_button = ($this->smart_button)();
        }
        return $this->smart_button;
    }
}
