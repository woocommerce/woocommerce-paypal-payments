<?php
/**
 * The Credit card gateway.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Psr\Container\ContainerInterface;

/**
 * Class CreditCardGateway
 */
class CreditCardGateway extends PayPalGateway {

	public const ID = 'ppcp-credit-card-gateway';

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * CreditCardGateway constructor.
	 *
	 * @param SettingsRenderer            $settings_renderer The Settings Renderer.
	 * @param OrderProcessor              $order_processor The Order processor.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The Authorized Payments processor.
	 * @param AuthorizeOrderActionNotice  $notice The Notices.
	 * @param ContainerInterface          $config The settings.
	 * @param string                      $module_url The URL to the module.
	 * @param SessionHandler              $session_handler The Session Handler.
	 */
	public function __construct(
		SettingsRenderer $settings_renderer,
		OrderProcessor $order_processor,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		AuthorizeOrderActionNotice $notice,
		ContainerInterface $config,
		string $module_url,
		SessionHandler $session_handler
	) {

		$this->id                  = self::ID;
		$this->order_processor     = $order_processor;
		$this->authorized_payments = $authorized_payments_processor;
		$this->notice              = $notice;
		$this->settings_renderer   = $settings_renderer;
		$this->config              = $config;
		$this->session_handler     = $session_handler;
		if (
			defined( 'PPCP_FLAG_SUBSCRIPTION' )
			&& PPCP_FLAG_SUBSCRIPTION
			&& $this->config->has( 'vault_enabled' )
			&& $this->config->get( 'vault_enabled' )
		) {
			$this->supports = array(
				'products',
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions',
			);
		}

		$this->method_title       = __(
			'PayPal Credit Card Processing',
			'woocommerce-paypal-commerce-gateway'
		);
		$this->method_description = __(
			'Provide your customers with the option to pay with credit card.',
			'woocommerce-paypal-commerce-gateway'
		);
		$this->title              = $this->config->has( 'dcc_gateway_title' ) ?
			$this->config->get( 'dcc_gateway_title' ) : $this->method_title;
		$this->description        = $this->config->has( 'dcc_gateway_description' ) ?
			$this->config->get( 'dcc_gateway_description' ) : $this->method_description;

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		$this->module_url = $module_url;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-paypal-commerce-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Credit Card Payments', 'woocommerce-paypal-commerce-gateway' ),
				'default' => 'no',
			),
			'ppcp'    => array(
				'type' => 'ppcp',
			),
		);
	}

	/**
	 * Renders the settings.
	 *
	 * @return string
	 */
	public function generate_ppcp_html(): string {

		ob_start();
		$this->settings_renderer->render( true );
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * Returns the title of the gateway.
	 *
	 * @return string
	 */
	public function get_title() {

		if ( is_admin() ) {
			return parent::get_title();
		}
		$title = parent::get_title();
		$icons = $this->config->has( 'card_icons' ) ? (array) $this->config->get( 'card_icons' ) : array();
		if ( empty( $icons ) ) {
			return $title;
		}

		$title_options = $this->card_labels();
		$images        = array_map(
			function ( string $type ) use ( $title_options ): string {
				return '<img
                 title="' . esc_attr( $title_options[ $type ] ) . '"
                 src="' . esc_url( $this->module_url ) . '/assets/images/' . esc_attr( $type ) . '.svg"
                 class="ppcp-card-icon"
                > ';
			},
			$icons
		);
		return $title . implode( '', $images );
	}

	/**
	 * Returns an array of credit card names.
	 *
	 * @return array
	 */
	private function card_labels(): array {
		return array(
			'visa'       => _x(
				'Visa',
				'Name of credit card',
				'woocommerce-paypal-commerce-gateway'
			),
			'mastercard' => _x(
				'Mastercard',
				'Name of credit card',
				'woocommerce-paypal-commerce-gateway'
			),
			'amex'       => _x(
				'American Express',
				'Name of credit card',
				'woocommerce-paypal-commerce-gateway'
			),
			'discover'   => _x(
				'Discover',
				'Name of credit card',
				'woocommerce-paypal-commerce-gateway'
			),
			'jcb'        => _x(
				'JCB',
				'Name of credit card',
				'woocommerce-paypal-commerce-gateway'
			),
			'elo'        => _x(
				'Elo',
				'Name of credit card',
				'woocommerce-paypal-commerce-gateway'
			),
			'hiper'      => _x(
				'Hiper',
				'Name of credit card',
				'woocommerce-paypal-commerce-gateway'
			),
		);
	}
}
