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
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
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
	 * @param ContainerInterface $container A services container instance.
	 */
	public function run( ContainerInterface $container ): void {
		add_action(
			'woocommerce_system_status_report',
			function () use ( $container ) {

				/* @var State $state The state. */
				$state = $container->get( 'onboarding.state' );

				/* @var Bearer $bearer The bearer. */
				$bearer = $container->get( 'api.bearer' );

				/* @var DccApplies $dcc_applies The ddc applies. */
				$dcc_applies = $container->get( 'api.helpers.dccapplies' );

				/* @var MessagesApply $messages_apply The messages apply. */
				$messages_apply = $container->get( 'button.helper.messages-apply' );

				/* @var Renderer $renderer The renderer. */
				$renderer = $container->get( 'status-report.renderer' );

				$items = array(
					array(
						'label'       => esc_html__( 'Onboarded', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether PayPal account is correctly configured or not.', 'woocommerce-paypal-payments' ),
						'value'       => $this->onboarded( $bearer, $state ),
					),
					array(
						'label'       => esc_html__( 'Shop country code', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Country / State value on Settings / General / Store Address.', 'woocommerce-paypal-payments' ),
						'value'       => wc_get_base_location()['country'],
					),
					array(
						'label'       => esc_html__( 'PayPal card processing available in country', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether PayPal card processing is available in country or not.', 'woocommerce-paypal-payments' ),
						'value'       => $dcc_applies->for_country_currency()
							? esc_html__( 'Yes', 'woocommerce-paypal-payments' )
							: esc_html__( 'No', 'woocommerce-paypal-payments' ),
					),
					array(
						'label'       => esc_html__( 'Pay Later messaging available in country', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether Pay Later is available in country or not.', 'woocommerce-paypal-payments' ),
						'value'       => $messages_apply->for_country()
							? esc_html__( 'Yes', 'woocommerce-paypal-payments' )
							: esc_html__( 'No', 'woocommerce-paypal-payments' ),
					),
					array(
						'label'       => esc_html__( 'Vault enabled', 'woocommerce-paypal-payments' ),
						'description' => esc_html__( 'Whether vaulting is enabled on PayPal account or not.', 'woocommerce-paypal-payments' ),
						'value'       => $this->vault_enabled( $bearer ),
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
	 * @return string
	 */
	private function onboarded( $bearer, $state ): string {
		try {
			$token = $bearer->bearer();
		} catch ( RuntimeException $exception ) {
			return esc_html__( 'No', 'woocommerce-paypal-payments' );
		}

		$current_state = $state->current_state();
		if ( $token->is_valid() && $current_state === $state::STATE_ONBOARDED ) {
			return esc_html__( 'Yes', 'woocommerce-paypal-payments' );
		}

		return esc_html__( 'No', 'woocommerce-paypal-payments' );
	}

	/**
	 * It returns whether vaulting is enabled or not.
	 *
	 * @param Bearer $bearer The bearer.
	 * @return string
	 */
	private function vault_enabled( $bearer ) {
		try {
			$token = $bearer->bearer();
			return $token->vaulting_available()
				? esc_html__( 'Yes', 'woocommerce-paypal-payments' )
				: esc_html__( 'No', 'woocommerce-paypal-payments' );
		} catch ( RuntimeException $exception ) {
			return esc_html__( 'No', 'woocommerce-paypal-payments' );
		}
	}
}
