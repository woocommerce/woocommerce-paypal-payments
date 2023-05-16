<?php
/**
 * The blocks module.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

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

/**
 * Class PayPalPaymentMethod
 */
class PayPalPaymentMethod extends AbstractPaymentMethodType {
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
	 * @var SmartButtonInterface
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
	 * Assets constructor.
	 *
	 * @param string               $module_url The url of this module.
	 * @param string               $version    The assets version.
	 * @param SmartButtonInterface $smart_button The smart button script loading handler.
	 * @param Settings             $plugin_settings The settings.
	 * @param SettingsStatus       $settings_status The Settings status helper.
	 * @param PayPalGateway        $gateway The WC gateway.
	 * @param bool                 $final_review_enabled Whether the final review is enabled.
	 * @param CancelView           $cancellation_view The cancellation view.
	 * @param SessionHandler       $session_handler The Session handler.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SmartButtonInterface $smart_button,
		Settings $plugin_settings,
		SettingsStatus $settings_status,
		PayPalGateway $gateway,
		bool $final_review_enabled,
		CancelView $cancellation_view,
		SessionHandler $session_handler
	) {
		$this->name                 = PayPalGateway::ID;
		$this->module_url           = $module_url;
		$this->version              = $version;
		$this->smart_button         = $smart_button;
		$this->plugin_settings      = $plugin_settings;
		$this->settings_status      = $settings_status;
		$this->gateway              = $gateway;
		$this->final_review_enabled = $final_review_enabled;
		$this->cancellation_view    = $cancellation_view;
		$this->session_handler      = $session_handler;
	}

	/**
	 * {@inheritDoc}
	 */
	public function initialize() {  }

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		// Do not load when definitely not needed,
		// but we still need to check the locations later and handle in JS
		// because has_block cannot be called here (too early).
		return $this->plugin_settings->has( 'enabled' ) && $this->plugin_settings->get( 'enabled' )
			&& ( $this->settings_status->is_smart_button_enabled_for_location( 'checkout-block-express' ) ||
				$this->settings_status->is_smart_button_enabled_for_location( 'cart-block' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'ppcp-checkout-block',
			trailingslashit( $this->module_url ) . 'assets/js/checkout-block.js',
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-checkout-block' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data() {
		$script_data = $this->smart_button->script_data();

		if ( isset( $script_data['continuation'] ) ) {
			$url = add_query_arg( array( CancelController::NONCE => wp_create_nonce( CancelController::NONCE ) ), wc_get_checkout_url() );

			$script_data['continuation']['cancel'] = array(
				'html' => $this->cancellation_view->render_session_cancellation( $url, $this->session_handler->funding_source() ),
			);
		}

		return array(
			'id'                 => $this->gateway->id,
			'title'              => $this->gateway->title,
			'description'        => $this->gateway->description,
			'enabled'            => $this->settings_status->is_smart_button_enabled_for_location( $script_data['context'] ),
			'fundingSource'      => $this->session_handler->funding_source(),
			'finalReviewEnabled' => $this->final_review_enabled,
			'ajax'               => array(
				'update_shipping' => array(
					'endpoint' => WC_AJAX::get_endpoint( UpdateShippingEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( UpdateShippingEndpoint::nonce() ),
				),
			),
			'scriptData'         => $script_data,
		);
	}
}
