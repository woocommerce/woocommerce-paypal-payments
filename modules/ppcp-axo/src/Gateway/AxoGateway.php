<?php
/**
 * The AXO Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Gateway;

use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class AXOGateway.
 */
class AxoGateway extends WC_Payment_Gateway {

	const ID = 'ppcp-axo-gateway';

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
	 * The card icons.
	 *
	 * @var array
	 */
	protected $card_icons;

	/**
	 * AXOGateway constructor.
	 *
	 * @param ContainerInterface $ppcp_settings The settings.
	 * @param string             $wcgateway_module_url The WcGateway module URL.
	 * @param array              $card_icons The card icons.
	 */
	public function __construct(
		ContainerInterface $ppcp_settings,
		string $wcgateway_module_url,
		array $card_icons
	) {
		$this->id = self::ID;

		$this->ppcp_settings        = $ppcp_settings;
		$this->wcgateway_module_url = $wcgateway_module_url;
		$this->card_icons           = $card_icons;

		$this->method_title       = __( 'Fastlane Debit & Credit Cards', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Fastlane Debit & Credit Cards', 'woocommerce-paypal-payments' );

		$is_axo_enabled = $this->ppcp_settings->has( 'axo_enabled' ) && $this->ppcp_settings->get( 'axo_enabled' );
		$this->update_option( 'enabled', $is_axo_enabled ? 'yes' : 'no' );

		$this->title = $this->ppcp_settings->has( 'axo_gateway_title' )
			? $this->ppcp_settings->get( 'axo_gateway_title' )
			: $this->get_option( 'title', $this->method_title );

		$this->description = $this->get_option( 'description', '' );

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
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'AXO', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable AXO payment gateway.', 'woocommerce-paypal-payments' ),
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

		// TODO ...

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
		$icon = parent::get_icon();

		if ( empty( $this->card_icons ) ) {
			return $icon;
		}

		$images = array();
		foreach ( $this->card_icons as $card ) {
			$images[] = '<img
				class="ppcp-card-icon"
				title="' . $card['title'] . '"
				src="' . esc_url( $this->wcgateway_module_url ) . 'assets/images/' . $card['file'] . '"
			> ';
		}

		return '<div class="ppcp-axo-card-icons">' . implode( '', $images ) . '</div>';
	}

}
