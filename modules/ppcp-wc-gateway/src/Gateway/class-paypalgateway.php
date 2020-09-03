<?php
/**
 * The PayPal Payment Gateway
 *
 * @package Inpsyde\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Psr\Container\ContainerInterface;

/**
 * Class PayPalGateway
 */
class PayPalGateway extends \WC_Payment_Gateway {

	public const ID                = 'ppcp-gateway';
	public const CAPTURED_META_KEY = '_ppcp_paypal_captured';
	public const INTENT_META_KEY   = '_ppcp_paypal_intent';
	public const ORDER_ID_META_KEY = '_ppcp_paypal_order_id';

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
	 * PayPalGateway constructor.
	 *
	 * @param SettingsRenderer            $settings_renderer The Settings Renderer.
	 * @param OrderProcessor              $order_processor The Order Processor.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The Authorized Payments Processor.
	 * @param AuthorizeOrderActionNotice  $notice The Order Action Notice object.
	 * @param ContainerInterface          $config The settings.
	 * @param SessionHandler              $session_handler The Session Handler.
	 */
	public function __construct(
		SettingsRenderer $settings_renderer,
		OrderProcessor $order_processor,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		AuthorizeOrderActionNotice $notice,
		ContainerInterface $config,
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
	}

	/**
	 * Whether the Gateway needs to be setup.
	 *
	 * @return bool
	 */
	public function needs_setup(): bool {

		return true;
	}

	/**
	 * Initializes the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'paypal-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayPal Checkout', 'paypal-for-woocommerce' ),
				'default' => 'no',
			),
			'ppcp'    => array(
				'type' => 'ppcp',
			),
		);
		if ( $this->is_credit_card_tab() ) {
			unset( $this->form_fields['enabled'] );
		}
	}

	/**
	 * Process a payment for an Woocommerce order.
	 *
	 * @param int $order_id The Woocommerce order id.
	 *
	 * @return array|null
	 */
	public function process_payment( $order_id ): ?array {
		global $woocommerce;
		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, \WC_Order::class ) ) {
			return null;
		}

		/**
		 * If the WC_Order is payed through the approved webhook.
		 */
        //phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['ppcp-resume-order'] ) && $wc_order->has_status( 'processing' ) ) {
			$this->session_handler->destroy_session_data();
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $wc_order ),
			);
		}
        //phpcs:enable WordPress.Security.NonceVerification.Recommended

		try {
			if ( $this->order_processor->process( $wc_order, $woocommerce ) ) {
				$this->session_handler->destroy_session_data();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $wc_order ),
				);
			}
		} catch ( PayPalApiException $error ) {
			if ( $error->has_detail( 'INSTRUMENT_DECLINED' ) ) {
				$this->session_handler->increment_insufficient_funding_tries();
				$host = $this->config->has( 'sandbox_on' ) && $this->config->get( 'sandbox_on' ) ?
					'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
				$url  = $host . 'checkoutnow?token=' . $this->session_handler->order()->id();
				if ( $this->session_handler->insufficient_funding_tries() >= 3 ) {
					$this->session_handler->destroy_session_data();
					wc_add_notice(
						__( 'Please use a different payment method.', 'paypal-for-woocommerce' ),
						'error'
					);
					return null;
				}
				return array(
					'result'   => 'success',
					'redirect' => $url,
				);
			}

			$this->session_handler->destroy_session_data();
		}
		wc_add_notice(
			$this->order_processor->last_error(),
			'error'
		);

		return null;
	}

	/**
	 * Captures an authorized payment for an Woocommerce order.
	 *
	 * @param \WC_Order $wc_order The Woocommerce order.
	 *
	 * @return bool
	 */
	public function capture_authorized_payment( \WC_Order $wc_order ): bool {
		$is_processed = $this->authorized_payments->process( $wc_order );
		$this->render_authorization_message_for_status( $this->authorized_payments->last_status() );

		if ( $is_processed ) {
			$wc_order->add_order_note(
				__( 'Payment successfully captured.', 'paypal-for-woocommerce' )
			);

			$wc_order->set_status( 'processing' );
			$wc_order->update_meta_data( self::CAPTURED_META_KEY, 'true' );
			$wc_order->save();
			return true;
		}

		if ( $this->authorized_payments->last_status() === AuthorizedPaymentsProcessor::ALREADY_CAPTURED ) {
			if ( $wc_order->get_status() === 'on-hold' ) {
				$wc_order->add_order_note(
					__( 'Payment successfully captured.', 'paypal-for-woocommerce' )
				);
				$wc_order->set_status( 'processing' );
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
			AuthorizedPaymentsProcessor::SUCCESSFUL       => AuthorizeOrderActionNotice::SUCCESS,
			AuthorizedPaymentsProcessor::ALREADY_CAPTURED => AuthorizeOrderActionNotice::ALREADY_CAPTURED,
			AuthorizedPaymentsProcessor::INACCESSIBLE     => AuthorizeOrderActionNotice::NO_INFO,
			AuthorizedPaymentsProcessor::NOT_FOUND        => AuthorizeOrderActionNotice::NOT_FOUND,
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
			return __( 'PayPal Card Processing', 'paypal-for-woocommerce' );
		}
		if ( $this->is_paypal_tab() ) {
			return __( 'PayPal Checkout', 'paypal-for-woocommerce' );
		}
		return __( 'PayPal', 'paypal-for-woocommerce' );
	}

	/**
	 * Defines the method description. If we are on the credit card tab in the settings, we want to change this.
	 *
	 * @return string
	 */
	private function define_method_description(): string {
		if ( $this->is_credit_card_tab() ) {
			return __(
				'Accept debit and credit cards, and local payment methods with PayPal’s latest solution.',
				'paypal-for-woocommerce'
			);
		}

		return __(
			'Accept PayPal, PayPal Credit and alternative payment types with PayPal’s latest solution.',
			'paypal-for-woocommerce'
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
			&& isset( $_GET[ SectionsRenderer::KEY ] )
			&& CreditCardGateway::ID === sanitize_text_field( wp_unslash( $_GET[ SectionsRenderer::KEY ] ) );

	}

	/**
	 * Whether we are on the PayPal settings tab.
	 *
	 * @return bool
	 */
	private function is_paypal_tab() : bool {
		return ! $this->is_credit_card_tab()
			&& is_admin()
			&& isset( $_GET['section'] )
			&& self::ID === sanitize_text_field( wp_unslash( $_GET['section'] ) );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}
