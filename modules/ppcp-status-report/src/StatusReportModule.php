<?php
/**
 * The status report module.
 *
 * @package WooCommerce\PayPalCommerce\StatusReport
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StatusReport;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencySupport;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper;
use WooCommerce\PayPalCommerce\Onboarding\State;

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

				$currency_support = $c->get( 'api.helpers.currency-support' );
				assert( $currency_support instanceof CurrencySupport );

				/* @var DccApplies $dcc_applies The ddc applies. */
				$dcc_applies = $c->get( 'api.helpers.dccapplies' );

				/* @var MessagesApply $messages_apply The messages apply. */
				$messages_apply = $c->get( 'button.helper.messages-apply' );

				/* @var Renderer $renderer The renderer. */
				$renderer = $c->get( 'status-report.renderer' );

				$had_ppec_plugin = PPECHelper::is_plugin_configured();

				$items = array(
					array(
						'label'       => esc_html__( 'Onboarded', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether PayPal account is correctly configured or not.', 'woocommerce-paypal-payments' ),
						'value'       => $this->bool_to_text(
							$this->onboarded( $bearer, $state )
						),
					),
					array(
						'label'       => esc_html__( 'Shop country code', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Country / State value on Settings / General / Store Address.', 'woocommerce-paypal-payments' ),
						'value'       => wc_get_base_location()['country'],
					),
					array(
						'label'       => esc_html__( 'WooCommerce currency supported', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether PayPal supports the default store currency or not.', 'woocommerce-paypal-payments' ),
						'value'       => $this->bool_to_text(
							$currency_support->supports_wc_currency()
						),
					),
					array(
						'label'       => esc_html__( 'PayPal card processing available in country', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether PayPal card processing is available in country or not.', 'woocommerce-paypal-payments' ),
						'value'       => $this->bool_to_text(
							$dcc_applies->for_country_currency()
						),
					),
					array(
						'label'       => esc_html__( 'Pay Later messaging available in country', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether Pay Later is available in country or not.', 'woocommerce-paypal-payments' ),
						'value'       => $this->bool_to_text(
							$messages_apply->for_country()
						),
					),
					array(
						'label'       => esc_html__( 'Vault enabled', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether vaulting is enabled on PayPal account or not.', 'woocommerce-paypal-payments' ),
						'value'       => $this->bool_to_text(
							$this->vault_enabled( $bearer )
						),
					),
					array(
						'label'       => esc_html__( 'Logging enabled', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether logging of plugin events and errors is enabled.', 'woocommerce-paypal-payments' ),
						'value'       => $this->bool_to_text(
							$settings->has( 'logging_enabled' ) && $settings->get( 'logging_enabled' )
						),
					),
					array(
						'label'       => esc_html__( 'Used PayPal Checkout plugin', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether the PayPal Checkout Gateway plugin was configured previously or not', 'woocommerce-paypal-payments' ),
						'value'       => $this->bool_to_text(
							$had_ppec_plugin
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
	 * Converts the bool value to "Yes" or "No".
	 *
	 * @param bool $value The value.
	 * @return string
	 */
	private function bool_to_text( bool $value ): string {
		return $value
			? esc_html__( 'Yes', 'woocommerce-paypal-payments' )
			: esc_html__( 'No', 'woocommerce-paypal-payments' );
	}
}
