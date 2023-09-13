<?php
/**
 * The status report module.
 *
 * @package WooCommerce\PayPalCommerce\StatusReport
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StatusReport;

use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
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

				$subscriptions_mode_settings = $c->get( 'wcgateway.settings.fields.subscriptions_mode' ) ?: array();

				/* @var State $state The state. */
				$state = $c->get( 'onboarding.state' );

				/* @var Bearer $bearer The bearer. */
				$bearer = $c->get( 'api.bearer' );

				/* @var DccApplies $dcc_applies The ddc applies. */
				$dcc_applies = $c->get( 'api.helpers.dccapplies' );

				/* @var MessagesApply $messages_apply The messages apply. */
				$messages_apply = $c->get( 'button.helper.messages-apply' );

				/* @var SubscriptionHelper $subscription_helper The subscription helper class. */
				$subscription_helper = $c->get( 'subscription.helper' );

				$last_webhook_storage = $c->get( 'webhook.last-webhook-storage' );
				assert( $last_webhook_storage instanceof WebhookEventStorage );

				$billing_agreements_endpoint = $c->get( 'api.endpoint.billing-agreements' );
				assert( $billing_agreements_endpoint instanceof BillingAgreementsEndpoint );

				/* @var Renderer $renderer The renderer. */
				$renderer = $c->get( 'status-report.renderer' );

				$had_ppec_plugin = PPECHelper::is_plugin_configured();

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
						'label'          => esc_html__( 'PayPal Vault enabled', 'woocommerce-paypal-payments' ),
						'exported_label' => 'PayPal Vault enabled',
						'description'    => esc_html__( 'Whether vaulting option is enabled on Standard Payments settings or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' )
						),
					),
					array(
						'label'          => esc_html__( 'ACDC Vault enabled', 'woocommerce-paypal-payments' ),
						'exported_label' => 'ACDC Vault enabled',
						'description'    => esc_html__( 'Whether vaulting option is enabled on Advanced Card Processing settings or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings->has( 'vault_enabled_dcc' ) && $settings->get( 'vault_enabled_dcc' )
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
						'label'          => esc_html__( 'Subscriptions Mode', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Subscriptions Mode',
						'description'    => esc_html__( 'Whether subscriptions are active and their mode.', 'woocommerce-paypal-payments' ),
						'value'          => $this->subscriptions_mode_text(
							$subscription_helper->plugin_is_active(),
							$settings->has( 'subscriptions_mode' ) ? (string) $settings->get( 'subscriptions_mode' ) : '',
							$subscriptions_mode_settings
						),
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
	 * Returns the text associated with the subscriptions mode status.
	 *
	 * @param bool   $is_plugin_active     Indicates if the WooCommerce Subscriptions plugin is active.
	 * @param string $subscriptions_mode   The subscriptions mode stored in settings.
	 * @param array  $field_settings       The subscriptions mode field settings.
	 * @return string
	 */
	private function subscriptions_mode_text( bool $is_plugin_active, string $subscriptions_mode, array $field_settings ): string {
		if ( ! $is_plugin_active || ! $field_settings || $subscriptions_mode === 'disable_paypal_subscriptions' ) {
			return 'Disabled';
		}

		if ( ! $subscriptions_mode ) {
			$subscriptions_mode = $field_settings['default'] ?? '';
		}

		// Return the options value or if it's missing from options the settings value.
		return $field_settings['options'][ $subscriptions_mode ] ?? $subscriptions_mode;
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
