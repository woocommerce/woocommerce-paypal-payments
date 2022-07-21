<?php
/**
 * The Credit card gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Subscription\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Psr\Container\ContainerInterface;

/**
 * Class CreditCardGateway
 */
class CreditCardGateway extends \WC_Payment_Gateway_CC {

	use ProcessPaymentTrait, OrderMetaTrait, TransactionIdHandlingTrait, PaymentsStatusHandlingTrait, FreeTrialHandlerTrait,
		GatewaySettingsRendererTrait;

	const ID = 'ppcp-credit-card-gateway';

	/**
	 * The Settings Renderer.
	 *
	 * @var SettingsRenderer
	 */
	protected $settings_renderer;

	/**
	 * The processor for orders.
	 *
	 * @var OrderProcessor
	 */
	protected $order_processor;

	/**
	 * The processor for authorized payments.
	 *
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments_processor;

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	protected $config;

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

	/**
	 * The refund processor.
	 *
	 * @var RefundProcessor
	 */
	private $refund_processor;

	/**
	 * The state.
	 *
	 * @var State
	 */
	protected $state;

	/**
	 * Service to get transaction url for an order.
	 *
	 * @var TransactionUrlProvider
	 */
	protected $transaction_url_provider;

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
	 * The shipping_preference factory.
	 *
	 * @var ShippingPreferenceFactory
	 */
	private $shipping_preference_factory;

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
	 * The subscription helper.
	 *
	 * @var SubscriptionHelper
	 */
	protected $subscription_helper;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * The payments endpoint
	 *
	 * @var PaymentsEndpoint
	 */
	protected $payments_endpoint;

	/**
	 * CreditCardGateway constructor.
	 *
	 * @param SettingsRenderer            $settings_renderer The Settings Renderer.
	 * @param OrderProcessor              $order_processor The Order processor.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The Authorized Payments processor.
	 * @param ContainerInterface          $config The settings.
	 * @param string                      $module_url The URL to the module.
	 * @param SessionHandler              $session_handler The Session Handler.
	 * @param RefundProcessor             $refund_processor The refund processor.
	 * @param State                       $state The state.
	 * @param TransactionUrlProvider      $transaction_url_provider Service able to provide view transaction url base.
	 * @param PaymentTokenRepository      $payment_token_repository The payment token repository.
	 * @param PurchaseUnitFactory         $purchase_unit_factory The purchase unit factory.
	 * @param ShippingPreferenceFactory   $shipping_preference_factory The shipping_preference factory.
	 * @param PayerFactory                $payer_factory The payer factory.
	 * @param OrderEndpoint               $order_endpoint The order endpoint.
	 * @param SubscriptionHelper          $subscription_helper The subscription helper.
	 * @param LoggerInterface             $logger The logger.
	 * @param Environment                 $environment The environment.
	 * @param PaymentsEndpoint            $payments_endpoint The payments endpoint.
	 */
	public function __construct(
		SettingsRenderer $settings_renderer,
		OrderProcessor $order_processor,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		ContainerInterface $config,
		string $module_url,
		SessionHandler $session_handler,
		RefundProcessor $refund_processor,
		State $state,
		TransactionUrlProvider $transaction_url_provider,
		PaymentTokenRepository $payment_token_repository,
		PurchaseUnitFactory $purchase_unit_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		PayerFactory $payer_factory,
		OrderEndpoint $order_endpoint,
		SubscriptionHelper $subscription_helper,
		LoggerInterface $logger,
		Environment $environment,
		PaymentsEndpoint $payments_endpoint
	) {
		$this->id                            = self::ID;
		$this->settings_renderer             = $settings_renderer;
		$this->order_processor               = $order_processor;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->config                        = $config;
		$this->module_url                    = $module_url;
		$this->session_handler               = $session_handler;
		$this->refund_processor              = $refund_processor;
		$this->state                         = $state;
		$this->transaction_url_provider      = $transaction_url_provider;
		$this->payment_token_repository      = $payment_token_repository;
		$this->purchase_unit_factory         = $purchase_unit_factory;
		$this->shipping_preference_factory   = $shipping_preference_factory;
		$this->payer_factory                 = $payer_factory;
		$this->order_endpoint                = $order_endpoint;
		$this->subscription_helper           = $subscription_helper;
		$this->logger                        = $logger;
		$this->environment                   = $environment;
		$this->payments_endpoint             = $payments_endpoint;

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
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'ppcp' => array(
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
				$striped_dark = str_replace( '-dark', '', $type );
				return '<img
                 title="' . esc_attr( $title_options[ $striped_dark ] ) . '"
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
		return $this->is_enabled();
	}

	/**
	 * Process payment for a WooCommerce order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, WC_Order::class ) ) {
			return $this->handle_payment_failure(
				null,
				new GatewayGenericException( new Exception( 'WC order was not found.' ) )
			);
		}

		/**
		 * If customer has chosen a saved credit card payment.
		 */
		$saved_credit_card = filter_input( INPUT_POST, 'saved_credit_card', FILTER_SANITIZE_STRING );
		$change_payment    = filter_input( INPUT_POST, 'woocommerce_change_payment', FILTER_SANITIZE_STRING );
		if ( $saved_credit_card && ! isset( $change_payment ) ) {

			$user_id  = (int) $wc_order->get_customer_id();
			$customer = new \WC_Customer( $user_id );
			$tokens   = $this->payment_token_repository->all_for_user_id( (int) $customer->get_id() );

			$selected_token = null;
			foreach ( $tokens as $token ) {
				if ( $token->id() === $saved_credit_card ) {
					$selected_token = $token;
					break;
				}
			}

			if ( ! $selected_token ) {
				return $this->handle_payment_failure(
					$wc_order,
					new GatewayGenericException( new Exception( 'Saved card token not found.' ) )
				);
			}

			$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
			$payer         = $this->payer_factory->from_customer( $customer );

			$shipping_preference = $this->shipping_preference_factory->from_state(
				$purchase_unit,
				''
			);

			try {
				$order = $this->order_endpoint->create(
					array( $purchase_unit ),
					$shipping_preference,
					$payer,
					$selected_token
				);

				$this->add_paypal_meta( $wc_order, $order, $this->environment );

				if ( ! $order->status()->is( OrderStatus::COMPLETED ) ) {
					return $this->handle_payment_failure(
						$wc_order,
						new GatewayGenericException( new Exception( "Unexpected status for order {$order->id()} using a saved card: {$order->status()->name()}." ) )
					);
				}

				if ( ! in_array(
					$order->intent(),
					array( 'CAPTURE', 'AUTHORIZE' ),
					true
				) ) {
					return $this->handle_payment_failure(
						$wc_order,
						new GatewayGenericException( new Exception( "Could neither capture nor authorize order {$order->id()} using a saved card. Status: {$order->status()->name()}. Intent: {$order->intent()}." ) )
					);
				}

				if ( $order->intent() === 'AUTHORIZE' ) {
					$order = $this->order_endpoint->authorize( $order );

					$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false' );
				}

				$transaction_id = $this->get_paypal_order_transaction_id( $order );
				if ( $transaction_id ) {
					$this->update_transaction_id( $transaction_id, $wc_order );
				}

				$this->handle_new_order_status( $order, $wc_order );

				if ( $this->is_free_trial_order( $wc_order ) ) {
					$this->authorized_payments_processor->void_authorizations( $order );
					$wc_order->payment_complete();
				} elseif ( $this->config->has( 'intent' ) && strtoupper( (string) $this->config->get( 'intent' ) ) === 'CAPTURE' ) {
					$this->authorized_payments_processor->capture_authorized_payment( $wc_order );
				}

				return $this->handle_payment_success( $wc_order );
			} catch ( RuntimeException $error ) {
				return $this->handle_payment_failure( $wc_order, $error );
			}
		}

		/**
		 * If customer has chosen change Subscription payment.
		 */
		if ( $this->subscription_helper->has_subscription( $order_id ) && $this->subscription_helper->is_subscription_change_payment() ) {
			if ( $saved_credit_card ) {
				update_post_meta( $order_id, 'payment_token_id', $saved_credit_card );

				return $this->handle_payment_success( $wc_order );
			}
		}

		/**
		 * If the WC_Order is paid through the approved webhook.
		 */
		//phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['ppcp-resume-order'] ) && $wc_order->has_status( 'processing' ) ) {
			return $this->handle_payment_success( $wc_order );
		}
		//phpcs:enable WordPress.Security.NonceVerification.Recommended

		try {
			if ( ! $this->order_processor->process( $wc_order ) ) {
				return $this->handle_payment_failure(
					$wc_order,
					new Exception(
						$this->order_processor->last_error()
					)
				);
			}

			if ( $this->subscription_helper->has_subscription( $order_id ) ) {
				$this->schedule_saved_payment_check( $order_id, $wc_order->get_customer_id() );
			}

			return $this->handle_payment_success( $wc_order );
		} catch ( PayPalApiException $error ) {
			return $this->handle_payment_failure(
				$wc_order,
				new Exception(
					Messages::generic_payment_error_message() . ' ' . $error->getMessage(),
					$error->getCode(),
					$error
				)
			);
		} catch ( RuntimeException $error ) {
			return $this->handle_payment_failure( $wc_order, $error );
		}
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

	/**
	 * Initialize settings for WC.
	 *
	 * @return void
	 */
	public function init_settings() {
		parent::init_settings();

		// looks like in some cases WC uses this field instead of get_option.
		$this->enabled = $this->is_enabled() ? 'yes' : '';
	}

	/**
	 * Get the option value for WC.
	 *
	 * @param string $key The option key.
	 * @param mixed  $empty_value Value when empty.
	 * @return mixed
	 */
	public function get_option( $key, $empty_value = null ) {
		if ( 'enabled' === $key ) {
			return $this->is_enabled();
		}

		return parent::get_option( $key, $empty_value );
	}

	/**
	 * Handle update of WC settings.
	 *
	 * @param string $key The option key.
	 * @param string $value The option value.
	 * @return bool was anything saved?
	 */
	public function update_option( $key, $value = '' ) {
		$ret = parent::update_option( $key, $value );

		if ( 'enabled' === $key ) {

			$this->config->set( 'dcc_enabled', 'yes' === $value );
			$this->config->persist();

			return true;
		}

		return $ret;
	}

	/**
	 * Returns if the gateway is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		return $this->config->has( 'dcc_enabled' ) && $this->config->get( 'dcc_enabled' );
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
