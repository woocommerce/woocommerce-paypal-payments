<?php
/**
 * The PayPal Payment Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhooksStatusPage;

/**
 * Class PayPalGateway
 */
class PayPalGateway extends \WC_Payment_Gateway {

	use ProcessPaymentTrait;

	const ID                          = 'ppcp-gateway';
	const CAPTURED_META_KEY           = '_ppcp_paypal_captured';
	const INTENT_META_KEY             = '_ppcp_paypal_intent';
	const ORDER_ID_META_KEY           = '_ppcp_paypal_order_id';
	const ORDER_PAYMENT_MODE_META_KEY = '_ppcp_paypal_payment_mode';

	/**
	 * The Settings Renderer.
	 *
	 * @var SettingsRenderer
	 */
	protected $settings_renderer;

	/**
	 * The processor for authorized payments.
	 *
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments;

	/**
	 * The Authorized Order Action Notice.
	 *
	 * @var AuthorizeOrderActionNotice
	 */
	protected $notice;

	/**
	 * The processor for orders.
	 *
	 * @var OrderProcessor
	 */
	protected $order_processor;

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	protected $config;

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

	/**
	 * Service able to provide transaction url for an order.
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
	 * The Refund Processor.
	 *
	 * @var RefundProcessor
	 */
	private $refund_processor;

	/**
	 * Whether the plugin is in onboarded state.
	 *
	 * @var bool
	 */
	private $onboarded;

	/**
	 * ID of the current PPCP gateway settings page, or empty if it is not such page.
	 *
	 * @var string
	 */
	protected $page_id;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * PayPalGateway constructor.
	 *
	 * @param SettingsRenderer            $settings_renderer The Settings Renderer.
	 * @param OrderProcessor              $order_processor The Order Processor.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The Authorized Payments Processor.
	 * @param AuthorizeOrderActionNotice  $notice The Order Action Notice object.
	 * @param ContainerInterface          $config The settings.
	 * @param SessionHandler              $session_handler The Session Handler.
	 * @param RefundProcessor             $refund_processor The Refund Processor.
	 * @param State                       $state The state.
	 * @param TransactionUrlProvider      $transaction_url_provider Service providing transaction view URL based on order.
	 * @param SubscriptionHelper          $subscription_helper The subscription helper.
	 * @param string                      $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 * @param Environment                 $environment The environment.
	 */
	public function __construct(
		SettingsRenderer $settings_renderer,
		OrderProcessor $order_processor,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		AuthorizeOrderActionNotice $notice,
		ContainerInterface $config,
		SessionHandler $session_handler,
		RefundProcessor $refund_processor,
		State $state,
		TransactionUrlProvider $transaction_url_provider,
		SubscriptionHelper $subscription_helper,
		string $page_id,
		Environment $environment
	) {

		$this->id                       = self::ID;
		$this->order_processor          = $order_processor;
		$this->authorized_payments      = $authorized_payments_processor;
		$this->notice                   = $notice;
		$this->settings_renderer        = $settings_renderer;
		$this->config                   = $config;
		$this->session_handler          = $session_handler;
		$this->refund_processor         = $refund_processor;
		$this->transaction_url_provider = $transaction_url_provider;
		$this->page_id                  = $page_id;
		$this->environment              = $environment;
		$this->onboarded                = $state->current_state() === State::STATE_ONBOARDED;

		if ( $this->onboarded ) {
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

		$this->method_title       = $this->define_method_title();
		$this->method_description = $this->define_method_description();
		$this->title              = $this->config->has( 'title' ) ?
			$this->config->get( 'title' ) : $this->method_title;
		$this->description        = $this->config->has( 'description' ) ?
			$this->config->get( 'description' ) : $this->method_description;

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
		$this->subscription_helper = $subscription_helper;
	}

	/**
	 * Whether the Gateway needs to be setup.
	 *
	 * @return bool
	 */
	public function needs_setup(): bool {

		return ! $this->onboarded;
	}

	/**
	 * Initializes the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'desc_tip'    => true,
				'description' => __( 'In order to use PayPal or PayPal Card Processing, you need to enable the Gateway.', 'woocommerce-paypal-payments' ),
				'label'       => __( 'Enable the PayPal Gateway', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
			),
			'ppcp'    => array(
				'type' => 'ppcp',
			),
		);

		$should_show_enabled_checkbox = $this->is_paypal_tab() && ( $this->config->has( 'merchant_email' ) && $this->config->get( 'merchant_email' ) );
		if ( ! $should_show_enabled_checkbox ) {
			unset( $this->form_fields['enabled'] );
		}
	}

	/**
	 * Captures an authorized payment for an WooCommerce order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @return bool
	 */
	public function capture_authorized_payment( \WC_Order $wc_order ): bool {
		$result_status = $this->authorized_payments->process( $wc_order );
		$this->render_authorization_message_for_status( $result_status );

		if ( AuthorizedPaymentsProcessor::ALREADY_CAPTURED === $result_status ) {
			if ( $wc_order->get_status() === 'on-hold' ) {
				$wc_order->add_order_note(
					__( 'Payment successfully captured.', 'woocommerce-paypal-payments' )
				);
			}

			$wc_order->update_meta_data( self::CAPTURED_META_KEY, 'true' );
			$wc_order->save();
			$wc_order->payment_complete();
			return true;
		}

		$captures = $this->authorized_payments->captures();
		if ( empty( $captures ) ) {
			return false;
		}

		$capture = end( $captures );

		$this->handle_capture_status( $capture, $wc_order );

		if ( AuthorizedPaymentsProcessor::SUCCESSFUL === $result_status ) {
			if ( $capture->status()->is( CaptureStatus::COMPLETED ) ) {
				$wc_order->add_order_note(
					__( 'Payment successfully captured.', 'woocommerce-paypal-payments' )
				);
			}
			$wc_order->update_meta_data( self::CAPTURED_META_KEY, 'true' );
			$wc_order->save();
			return true;
		}

		return false;
	}

	/**
	 * Displays the notice for a status.
	 *
	 * @param string $status The status.
	 */
	private function render_authorization_message_for_status( string $status ) {

		$message_mapping = array(
			AuthorizedPaymentsProcessor::SUCCESSFUL        => AuthorizeOrderActionNotice::SUCCESS,
			AuthorizedPaymentsProcessor::ALREADY_CAPTURED  => AuthorizeOrderActionNotice::ALREADY_CAPTURED,
			AuthorizedPaymentsProcessor::INACCESSIBLE      => AuthorizeOrderActionNotice::NO_INFO,
			AuthorizedPaymentsProcessor::NOT_FOUND         => AuthorizeOrderActionNotice::NOT_FOUND,
			AuthorizedPaymentsProcessor::BAD_AUTHORIZATION => AuthorizeOrderActionNotice::BAD_AUTHORIZATION,
		);
		$display_message = ( isset( $message_mapping[ $status ] ) ) ?
			$message_mapping[ $status ]
			: AuthorizeOrderActionNotice::FAILED;
		$this->notice->display_message( $display_message );
	}

	/**
	 * Renders the settings.
	 *
	 * @return string
	 */
	public function generate_ppcp_html(): string {

		ob_start();
		$this->settings_renderer->render( false );
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * Defines the method title. If we are on the credit card tab in the settings, we want to change this.
	 *
	 * @return string
	 */
	private function define_method_title(): string {
		if ( $this->is_credit_card_tab() ) {
			return __( 'PayPal Card Processing', 'woocommerce-paypal-payments' );
		}
		if ( $this->is_webhooks_tab() ) {
			return __( 'Webhooks Status', 'woocommerce-paypal-payments' );
		}
		if ( $this->is_paypal_tab() ) {
			return __( 'PayPal Checkout', 'woocommerce-paypal-payments' );
		}
		return __( 'PayPal', 'woocommerce-paypal-payments' );
	}

	/**
	 * Defines the method description. If we are on the credit card tab in the settings, we want to change this.
	 *
	 * @return string
	 */
	private function define_method_description(): string {
		if ( $this->is_credit_card_tab() ) {
			return __(
				'Accept debit and credit cards, and local payment methods.',
				'woocommerce-paypal-payments'
			);
		}
		if ( $this->is_webhooks_tab() ) {
			return __(
				'Status of the webhooks subscription.',
				'woocommerce-paypal-payments'
			);
		}

		return __(
			'Accept PayPal, Pay Later and alternative payment types.',
			'woocommerce-paypal-payments'
		);
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended

	/**
	 * Determines, whether the current session is on the credit card tab in the admin settings.
	 *
	 * @return bool
	 */
	private function is_credit_card_tab() : bool {
		return is_admin()
			&& CreditCardGateway::ID === $this->page_id;

	}

	/**
	 * Whether we are on the Webhooks Status tab.
	 *
	 * @return bool
	 */
	private function is_webhooks_tab() : bool {
		return is_admin()
			&& WebhooksStatusPage::ID === $this->page_id;
	}

	/**
	 * Whether we are on the PayPal settings tab.
	 *
	 * @return bool
	 */
	private function is_paypal_tab() : bool {
		return ! $this->is_credit_card_tab()
			&& is_admin()
			&& self::ID === $this->page_id;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

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
	 * Return transaction url for this gateway and given order.
	 *
	 * @param \WC_Order $order WC order to get transaction url by.
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ): string {
		$this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base( $order );

		return parent::get_transaction_url( $order );
	}

	/**
	 * Updates WooCommerce gateway option.
	 *
	 * @param string $key The option key.
	 * @param string $value The option value.
	 * @return bool was anything saved?
	 */
	public function update_option( $key, $value = '' ) {
		$ret = parent::update_option( $key, $value );

		if ( 'enabled' === $key ) {
			$this->config->set( 'enabled', 'yes' === $value );
			$this->config->persist();

			return true;
		}

		return $ret;
	}

	/**
	 * Returns the environment.
	 *
	 * @return Environment
	 */
	protected function environment(): Environment {
		return $this->environment;
	}
}
