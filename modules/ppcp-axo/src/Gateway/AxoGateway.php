<?php
/**
 * The AXO Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Gateway;

use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\GatewaySettingsRendererTrait;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;

/**
 * Class AXOGateway.
 */
class AxoGateway extends WC_Payment_Gateway {
	use OrderMetaTrait, GatewaySettingsRendererTrait;

	const ID = 'ppcp-axo-gateway';

	/**
	 * The Settings Renderer.
	 *
	 * @var SettingsRenderer
	 */
	protected $settings_renderer;

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	protected $ppcp_settings;

	/**
	 * The WcGateway module URL.
	 *
	 * @var string
	 */
	protected $wcgateway_module_url;

	/**
	 * The processor for orders.
	 *
	 * @var OrderProcessor
	 */
	protected $order_processor;

	/**
	 * The card icons.
	 *
	 * @var array
	 */
	protected $card_icons;

	/**
	 * The AXO card icons.
	 *
	 * @var array
	 */
	protected $card_icons_axo;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * The purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	protected $purchase_unit_factory;

	/**
	 * The shipping preference factory.
	 *
	 * @var ShippingPreferenceFactory
	 */
	protected $shipping_preference_factory;

	/**
	 * The transaction url provider.
	 *
	 * @var TransactionUrlProvider
	 */
	protected $transaction_url_provider;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * AXOGateway constructor.
	 *
	 * @param SettingsRenderer          $settings_renderer The settings renderer.
	 * @param ContainerInterface        $ppcp_settings The settings.
	 * @param string                    $wcgateway_module_url The WcGateway module URL.
	 * @param OrderProcessor            $order_processor The Order processor.
	 * @param array                     $card_icons The card icons.
	 * @param array                     $card_icons_axo The card icons.
	 * @param OrderEndpoint             $order_endpoint The order endpoint.
	 * @param PurchaseUnitFactory       $purchase_unit_factory The purchase unit factory.
	 * @param ShippingPreferenceFactory $shipping_preference_factory The shipping preference factory.
	 * @param TransactionUrlProvider    $transaction_url_provider The transaction url provider.
	 * @param Environment               $environment The environment.
	 * @param LoggerInterface           $logger The logger.
	 */
	public function __construct(
		SettingsRenderer $settings_renderer,
		ContainerInterface $ppcp_settings,
		string $wcgateway_module_url,
		OrderProcessor $order_processor,
		array $card_icons,
		array $card_icons_axo,
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		TransactionUrlProvider $transaction_url_provider,
		Environment $environment,
		LoggerInterface $logger
	) {
		$this->id = self::ID;

		$this->settings_renderer    = $settings_renderer;
		$this->ppcp_settings        = $ppcp_settings;
		$this->wcgateway_module_url = $wcgateway_module_url;
		$this->order_processor      = $order_processor;
		$this->card_icons           = $card_icons;
		$this->card_icons_axo       = $card_icons_axo;

		$this->method_title       = __( 'Fastlane Debit & Credit Cards', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Fastlane accelerates the checkout experience for guest shoppers and autofills their details so they can pay in seconds. When enabled, Fastlane is presented as the default payment method for guests.', 'woocommerce-paypal-payments' );

		$is_axo_enabled = $this->ppcp_settings->has( 'axo_enabled' ) && $this->ppcp_settings->get( 'axo_enabled' );
		$this->update_option( 'enabled', $is_axo_enabled ? 'yes' : 'no' );

		$this->title = $this->ppcp_settings->has( 'axo_gateway_title' )
			? $this->ppcp_settings->get( 'axo_gateway_title' )
			: $this->get_option( 'title', $this->method_title );

		$this->description = __( 'Enter your email address to continue.', 'woocommerce-paypal-payments' );

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		$this->order_endpoint              = $order_endpoint;
		$this->purchase_unit_factory       = $purchase_unit_factory;
		$this->shipping_preference_factory = $shipping_preference_factory;
		$this->logger                      = $logger;

		$this->transaction_url_provider = $transaction_url_provider;
		$this->environment              = $environment;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'AXO', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable AXO payment gateway.', 'woocommerce-paypal-payments' ),
			),
			'ppcp'    => array(
				'type' => 'ppcp',
			),
		);
	}

	/**
	 * Processes the order.
	 *
	 * @param int $order_id The WC order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$wc_order = wc_get_order( $order_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$fastlane_member = wc_clean( wp_unslash( $_POST['fastlane_member'] ?? '' ) );
		if ( $fastlane_member ) {
			$payment_method_title = __( 'Debit & Credit Cards (via Fastlane by PayPal)', 'woocommerce-paypal-payments' );
			$wc_order->set_payment_method_title( $payment_method_title );
			$wc_order->save();
		}

		$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = wc_clean( wp_unslash( $_POST['axo_nonce'] ?? '' ) );

		try {
			$shipping_preference = $this->shipping_preference_factory->from_state(
				$purchase_unit,
				'checkout'
			);

			$payment_source_properties                   = new \stdClass();
			$payment_source_properties->single_use_token = $nonce;

			$payment_source = new PaymentSource(
				'card',
				$payment_source_properties
			);

			$order = $this->order_endpoint->create(
				array( $purchase_unit ),
				$shipping_preference,
				null,
				null,
				'',
				ApplicationContext::USER_ACTION_CONTINUE,
				'',
				array(),
				$payment_source
			);

			$this->order_processor->process_captured_and_authorized( $wc_order, $order );

		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$this->logger->error( $error );
			wc_add_notice( $error, 'error' );

			$wc_order->update_status(
				'failed',
				$error
			);

			return array(
				'result'   => 'failure',
				'redirect' => wc_get_checkout_url(),
			);
		}

		WC()->cart->empty_cart();

		$result = array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $wc_order ),
		);

		return $result;
	}

	/**
	 * Returns the icons of the gateway.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon      = parent::get_icon();
		$icons     = $this->card_icons;
		$icons_src = esc_url( $this->wcgateway_module_url ) . 'assets/images/';

		if ( $this->card_icons_axo ) {
			$icons     = $this->card_icons_axo;
			$icons_src = esc_url( $this->wcgateway_module_url ) . 'assets/images/axo/';
		}

		if ( empty( $this->card_icons ) ) {
			return $icon;
		}

		$images = array();

		foreach ( $icons as $card ) {
			$images[] = '<img
				class="ppcp-card-icon"
				title="' . $card['title'] . '"
				src="' . $icons_src . $card['file'] . '"
			> ';
		}

		return implode( '', $images );
	}

	/**
	 * Return transaction url for this gateway and given order.
	 *
	 * @param WC_Order $order WC order to get transaction url by.
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ): string {
		$this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base( $order );

		return parent::get_transaction_url( $order );
	}

	/**
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title() {
		if ( is_admin() ) {
			// $theorder and other things for retrieving the order or post info are not available
			// in the constructor, so must do it here.
			global $theorder;
			if ( $theorder instanceof WC_Order ) {
				if ( $theorder->get_payment_method() === self::ID ) {
					$payment_method_title = $theorder->get_payment_method_title();
					if ( $payment_method_title ) {
						$this->title = $payment_method_title;
					}
				}
			}
		}

		return parent::get_title();
	}

	/**
	 * Returns the settings renderer.
	 *
	 * @return SettingsRenderer
	 */
	protected function settings_renderer(): SettingsRenderer {
		return $this->settings_renderer;
	}
}
