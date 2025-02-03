<?php
/**
 * REST endpoint to manage the things to do items.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Response;
use WP_REST_Server;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;

/**
 * REST controller for the "Things To Do" items in the Overview tab.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model. It's responsible for checking eligibility and
 * providing configuration data for things to do next.
 */
class TodosRestEndpoint extends RestEndpoint {
	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'todos';

	/**
	 * The todos model instance.
	 *
	 * @var TodosModel
	 */
	protected TodosModel $todos;

	/**
	 * Constructor.
	 *
	 * @param TodosModel $todos The todos model instance.
	 */
	public function __construct( TodosModel $todos ) {
		$this->todos = $todos;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_todos' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Returns the full list of todo definitions with their eligibility conditions.
	 *
	 * @return array The array of todo definitions.
	 */
	protected function get_todo_definitions(): array {
		return array(
			'enable_fastlane'                => array(
				'title'       => __( 'Enable Fastlane', 'woocommerce-paypal-payments' ),
				'description' => __( 'Accelerate your guest checkout with Fastlane by PayPal.', 'woocommerce-paypal-payments' ),
				'isEligible'  => fn() => true,
				'action'      => array(
					'type'    => 'tab',
					'tab'     => 'payment_methods',
					'section' => 'ppcp-card-payments-card',
				),
			),
			'enable_credit_debit_cards'      => array(
				'title'       => __( 'Enable Credit and Debit Cards on your checkout', 'woocommerce-paypal-payments' ),
				'description' => __( 'Credit and Debit Cards is now available for Blocks checkout pages.', 'woocommerce-paypal-payments' ),
				'isEligible'  => fn() => true,
				'action'      => array(
					'type'    => 'tab',
					'tab'     => 'payment_methods',
					'section' => 'ppcp-card-payments-card',
				),
			),
			'enable_pay_later_messaging'     => array(
				'title'       => __( 'Enable Pay Later messaging', 'woocommerce-paypal-payments' ),
				'description' => __( 'Show Pay Later messaging to boost conversion rate and increase cart size.', 'woocommerce-paypal-payments' ),
				'isEligible'  => fn() => true,
				'action'      => array(
					'type'    => 'tab',
					'tab'     => 'overview',
					'section' => 'pay_later_messaging',
				),
			),
			'configure_paypal_subscription'  => array(
				'title'       => __( 'Configure a PayPal Subscription', 'woocommerce-paypal-payments' ),
				'description' => __( 'Connect a subscriptions-type product from WooCommerce with PayPal.', 'woocommerce-paypal-payments' ),
				'isEligible'  => fn() => true,
				'action'      => array(
					'type' => 'external',
					'url'  => admin_url( 'edit.php?post_type=product&product_type=subscription' ),
				),
			),
			'register_domain_apple_pay'      => array(
				'title'       => __( 'Register Domain for Apple Pay', 'woocommerce-paypal-payments' ),
				'description' => __( 'To enable Apple Pay, you must register your domain with PayPal.', 'woocommerce-paypal-payments' ),
				'isEligible'  => fn() => true,
				'action'      => array(
					'type'    => 'tab',
					'tab'     => 'overview',
					'section' => 'apple_pay',
				),
			),
			'add_digital_wallets_to_account' => array(
				'title'       => __( 'Add digital wallets to your account', 'woocommerce-paypal-payments' ),
				'description' => __( 'Add the ability to accept Apple Pay & Google Pay to your PayPal account.', 'woocommerce-paypal-payments' ),
				'isEligible'  => fn() => true,
				'action'      => array(
					'type' => 'external',
					'url'  => 'https://www.paypal.com/businessmanage/account/settings',
				),
			),
			'enable_apple_pay'               => array(
				'title'       => __( 'Enable Apple Pay', 'woocommerce-paypal-payments' ),
				'description' => __( 'Allow your buyers to check out via Apple Pay.', 'woocommerce-paypal-payments' ),
				'isEligible'  => fn() => true,
				'action'      => array(
					'type'    => 'tab',
					'tab'     => 'overview',
					'section' => 'apple_pay',
				),
			),
			'enable_google_pay'              => array(
				'title'       => __( 'Enable Google Pay', 'woocommerce-paypal-payments' ),
				'description' => __( 'Allow your buyers to check out via Google Pay.', 'woocommerce-paypal-payments' ),
				'isEligible'  => fn() => true,
				'action'      => array(
					'type'    => 'tab',
					'tab'     => 'overview',
					'section' => 'google_pay',
				),
			),
		);
	}

	/**
	 * Returns all eligible todo items.
	 *
	 * @return WP_REST_Response The response containing eligible todo items.
	 */
	public function get_todos(): WP_REST_Response {
		$completion_states = $this->todos->get();
		$todos             = array();

		foreach ( $this->get_todo_definitions() as $id => $todo ) {
			if ( $todo['isEligible']() ) {
				$todos[] = array_merge(
					array(
						'id'          => $id,
						'isCompleted' => $completion_states[ $id ] ?? false,
					),
					array_diff_key( $todo, array( 'isEligible' => true ) )
				);
			}
		}

		return $this->return_success( $todos );
	}
}
