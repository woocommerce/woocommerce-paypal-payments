<?php
/**
 * The status report module.
 *
 * @package WooCommerce\PayPalCommerce\StatusReport
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StatusReport;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingAgreementsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage;

/**
 * Class StatusReportModule
 */
class StatusReportModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $c A services container instance.
	 */
	public function run( ContainerInterface $c ): void {
		add_action(
			'woocommerce_system_status_report',
			function () use ( $c ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof ContainerInterface );

				/* @var State $state The state. */
				$state = $c->get( 'onboarding.state' );

				/* @var Bearer $bearer The bearer. */
				$bearer = $c->get( 'api.bearer' );

				/* @var DccApplies $dcc_applies The ddc applies. */
				$dcc_applies = $c->get( 'api.helpers.dccapplies' );

				/* @var MessagesApply $messages_apply The messages apply. */
				$messages_apply = $c->get( 'button.helper.messages-apply' );

				$last_webhook_storage = $c->get( 'webhook.last-webhook-storage' );
				assert( $last_webhook_storage instanceof WebhookEventStorage );

				$billing_agreements_endpoint = $c->get( 'api.endpoint.billing-agreements' );
				assert( $billing_agreements_endpoint instanceof BillingAgreementsEndpoint );

				/* @var Renderer $renderer The renderer. */
				$renderer = $c->get( 'status-report.renderer' );

				$had_ppec_plugin = PPECHelper::is_plugin_configured();

				$is_tracking_available = $c->get( 'order-tracking.is-tracking-available' );

				$items = array(
					array(
						'label'          => esc_html__( 'Onboarded', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Onboarded',
						'description'    => esc_html__( 'Whether PayPal account is correctly configured or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$this->onboarded( $bearer, $state )
						),
					),
					array(
						'label'          => esc_html__( 'Shop country code', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Shop country code',
						'description'    => esc_html__( 'Country / State value on Settings / General / Store Address.', 'woocommerce-paypal-payments' ),
						'value'          => $c->get( 'api.shop.country' ),
					),
					array(
						'label'          => esc_html__( 'WooCommerce currency supported', 'woocommerce-paypal-payments' ),
						'exported_label' => 'WooCommerce currency supported',
						'description'    => esc_html__( 'Whether PayPal supports the default store currency or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$c->get( 'api.shop.is-currency-supported' )
						),
					),
					array(
						'label'          => esc_html__( 'Advanced Card Processing available in country', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Advanced Card Processing available in country',
						'description'    => esc_html__( 'Whether Advanced Card Processing is available in country or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$dcc_applies->for_country_currency()
						),
					),
					array(
						'label'          => esc_html__( 'Pay Later messaging available in country', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Pay Later messaging available in country',
						'description'    => esc_html__( 'Whether Pay Later is available in country or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$messages_apply->for_country()
						),
					),
					array(
						'label'          => esc_html__( 'Webhook status', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Webhook status',
						'description'    => esc_html__( 'Whether we received webhooks successfully.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html( ! $last_webhook_storage->is_empty() ),
					),
					array(
						'label'          => esc_html__( 'Vault enabled', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Vault enabled',
						'description'    => esc_html__( 'Whether vaulting is enabled on PayPal account or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$this->vault_enabled( $bearer )
						),
					),
					array(
						'label'          => esc_html__( 'Logging enabled', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Logging enabled',
						'description'    => esc_html__( 'Whether logging of plugin events and errors is enabled.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings->has( 'logging_enabled' ) && $settings->get( 'logging_enabled' )
						),
					),
					array(
						'label'          => esc_html__( 'Reference Transactions', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Reference Transactions',
						'description'    => esc_html__( 'Whether Reference Transactions are enabled for the connected account', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$this->reference_transaction_enabled( $billing_agreements_endpoint )
						),
					),
					array(
						'label'          => esc_html__( 'Used PayPal Checkout plugin', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Used PayPal Checkout plugin',
						'description'    => esc_html__( 'Whether the PayPal Checkout Gateway plugin was configured previously or not', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$had_ppec_plugin
						),
					),
					array(
						'label'          => esc_html__( 'Tracking enabled', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Tracking enabled',
						'description'    => esc_html__( 'Whether tracking is enabled on PayPal account or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html( $is_tracking_available ),
					),
				);

				echo wp_kses_post(
					$renderer->render(
						esc_html__( 'WooCommerce PayPal Payments', 'woocommerce-paypal-payments' ),
						$items
					)
				);
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getKey() {  }

	/**
	 * It returns the current onboarding status.
	 *
	 * @param Bearer $bearer The bearer.
	 * @param State  $state The state.
	 * @return bool
	 */
	private function onboarded( Bearer $bearer, State $state ): bool {
		try {
			$token = $bearer->bearer();
		} catch ( RuntimeException $exception ) {
			return false;
		}

		$current_state = $state->current_state();
		return $token->is_valid() && $current_state === $state::STATE_ONBOARDED;
	}

	/**
	 * It returns whether vaulting is enabled or not.
	 *
	 * @param Bearer $bearer The bearer.
	 * @return bool
	 */
	private function vault_enabled( Bearer $bearer ): bool {
		try {
			$token = $bearer->bearer();
			return $token->vaulting_available();
		} catch ( RuntimeException $exception ) {
			return false;
		}
	}

	/**
	 * Checks if reference transactions are enabled in account.
	 *
	 * @param BillingAgreementsEndpoint $billing_agreements_endpoint The endpoint.
	 */
	private function reference_transaction_enabled( BillingAgreementsEndpoint $billing_agreements_endpoint ): bool {
		try {
			return $billing_agreements_endpoint->reference_transaction_enabled();
		} catch ( RuntimeException $exception ) {
			return false;
		}
	}

	/**
	 * Converts the bool value to "yes" icon or dash.
	 *
	 * @param bool $value The value.
	 * @return string
	 */
	private function bool_to_html( bool $value ): string {
		return $value
			? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>'
			: '<mark class="no">&ndash;</mark>';
	}
}
