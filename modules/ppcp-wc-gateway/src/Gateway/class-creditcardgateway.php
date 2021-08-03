<?php
/**
 * The Credit card gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Subscription\Repository\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Psr\Container\ContainerInterface;

/**
 * Class CreditCardGateway
 */
class CreditCardGateway extends \WC_Payment_Gateway_CC {

	use ProcessPaymentTrait;

	const ID = 'ppcp-credit-card-gateway';

	/**
	 * Service to get transaction url for an order.
	 *
	 * @var TransactionUrlProvider
	 */
	protected $transaction_url_provider;

	/**
	 * The subscription helper.
	 *
	 * @var SubscriptionHelper
	 */
	protected $subscription_helper;

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The refund processor.
	 *
	 * @var RefundProcessor
	 */
	private $refund_processor;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	private $payment_token_repository;

	/**
	 * The purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * The payer factory.
	 *
	 * @var PayerFactory
	 */
	private $payer_factory;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

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
	 * @param RefundProcessor             $refund_processor The refund processor.
	 * @param State                       $state The state.
	 * @param TransactionUrlProvider      $transaction_url_provider Service able to provide view transaction url base.
	 * @param PaymentTokenRepository      $payment_token_repository The payment token repository.
	 * @param PurchaseUnitFactory         $purchase_unit_factory The purchase unit factory.
	 * @param PayerFactory                $payer_factory The payer factory.
	 * @param  OrderEndpoint               $order_endpoint The order endpoint.
	 * @param SubscriptionHelper          $subscription_helper The subscription helper.
	 */
	public function __construct(
		SettingsRenderer $settings_renderer,
		OrderProcessor $order_processor,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		AuthorizeOrderActionNotice $notice,
		ContainerInterface $config,
		string $module_url,
		SessionHandler $session_handler,
		RefundProcessor $refund_processor,
		State $state,
		TransactionUrlProvider $transaction_url_provider,
		PaymentTokenRepository $payment_token_repository,
		PurchaseUnitFactory $purchase_unit_factory,
		PayerFactory $payer_factory,
		OrderEndpoint $order_endpoint,
		SubscriptionHelper $subscription_helper
	) {

		$this->id                  = self::ID;
		$this->order_processor     = $order_processor;
		$this->authorized_payments = $authorized_payments_processor;
		$this->notice              = $notice;
		$this->settings_renderer   = $settings_renderer;
		$this->config              = $config;
		$this->session_handler     = $session_handler;
		$this->refund_processor    = $refund_processor;

		if ( $state->current_state() === State::STATE_ONBOARDED ) {
			$this->supports = array( 'refunds' );
		}
		if (
			defined( 'PPCP_FLAG_SUBSCRIPTION' )
			&& PPCP_FLAG_SUBSCRIPTION
			&& $this->gateways_enabled()
			&& $this->vault_setting_enabled()
		) {
			$this->supports = array(
				'refunds',
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
			'PayPal Card Processing',
			'woocommerce-paypal-payments'
		);
		$this->method_description = __(
			'Accept debit and credit cards, and local payment methods with PayPal’s latest solution.',
			'woocommerce-paypal-payments'
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

		$this->module_url               = $module_url;
		$this->payment_token_repository = $payment_token_repository;
		$this->purchase_unit_factory    = $purchase_unit_factory;
		$this->payer_factory            = $payer_factory;
		$this->order_endpoint           = $order_endpoint;
		$this->transaction_url_provider = $transaction_url_provider;
		$this->subscription_helper      = $subscription_helper;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Credit Card Payments', 'woocommerce-paypal-payments' ),
				'default' => 'no',
			),
			'ppcp'    => array(
				'type' => 'ppcp',
			),
		);
	}

	/**
	 * Render the credit card fields.
	 */
	public function form() {
		add_action( 'gettext', array( $this, 'replace_credit_card_cvv_label' ), 10, 3 );
		parent::form();
		remove_action( 'gettext', 'replace_credit_card_cvv_label' );
	}

	/**
	 * Replace WooCommerce credit card field label.
	 *
	 * @param string $translation Translated text.
	 * @param string $text Original text to translate.
	 * @param string $domain Text domain.
	 *
	 * @return string Translated field.
	 */
	public function replace_credit_card_cvv_label( string $translation, string $text, string $domain ): string {
		if ( 'woocommerce' !== $domain || 'Card code' !== $text ) {
			return $translation;
		}

		return __( 'CVV', 'woocommerce-paypal-payments' );
	}

	/**
	 * Returns the icons of the gateway.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon = parent::get_icon();

		$icons = $this->config->has( 'card_icons' ) ? (array) $this->config->get( 'card_icons' ) : array();
		if ( empty( $icons ) ) {
			return $icon;
		}

		$title_options = $this->card_labels();
		$images        = array_map(
			function ( string $type ) use ( $title_options ): string {
				return '<img
                 title="' . esc_attr( $title_options[ $type ] ) . '"
                 src="' . esc_url( $this->module_url ) . 'assets/images/' . esc_attr( $type ) . '.svg"
                 class="ppcp-card-icon"
                > ';
			},
			$icons
		);

		return implode( '', $images );
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
				'woocommerce-paypal-payments'
			),
			'mastercard' => _x(
				'Mastercard',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'amex'       => _x(
				'American Express',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'discover'   => _x(
				'Discover',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'jcb'        => _x(
				'JCB',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'elo'        => _x(
				'Elo',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'hiper'      => _x(
				'Hiper',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
		);
	}

	/**
	 * Whether the gateway is available or not.
	 *
	 * @return bool
	 */
	public function is_available() : bool {
		return $this->config->has( 'dcc_enabled' ) && $this->config->get( 'dcc_enabled' );
	}


	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, \WC_Order::class ) ) {
			return false;
		}
		return $this->refund_processor->process( $order, (float) $amount, (string) $reason );
	}

	/**
	 * Set the class property then call parent function.
	 *
	 * @param \WC_Order $order WC Order to get transaction url for.
	 *
	 * @inheritDoc
	 */
	public function get_transaction_url( $order ): string {
		$this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base( $order );

		return parent::get_transaction_url( $order );
	}
}
