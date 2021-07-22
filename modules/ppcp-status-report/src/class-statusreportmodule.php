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
				'label' => esc_html__( 'Onboarding state', 'woocommerce-paypal-payments' ),
				'value' => $this->onboarding_state( $state ),
			),
			array(
				'label' => esc_html__( 'Shop country code', 'woocommerce-paypal-payments' ),
				'value' => wc_get_base_location()['country'],
			),
			array(
				'label' => esc_html__( 'PayPal card processing', 'woocommerce-paypal-payments' ),
				'value' => $dcc_applies->for_country_currency()
					? esc_html__( 'Yes', 'woocommerce-paypal-payments' )
					: esc_html__( 'No', 'woocommerce-paypal-payments' ),
			),
			array(
				'label' => esc_html__( 'Pay Later messaging', 'woocommerce-paypal-payments' ),
				'value' => $messages_apply->for_country()
					? esc_html__( 'Yes', 'woocommerce-paypal-payments' )
					: esc_html__( 'No', 'woocommerce-paypal-payments' ),
			),
			array(
				'label' => esc_html__( 'Vault enabled', 'woocommerce-paypal-payments' ),
				'value' => $this->vault_enabled( $bearer ),
			),
		);

		add_action(
			'woocommerce_system_status_report',
			function () use ( $renderer, $items ) { ?>
				<?php
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
	 * It returns the current onboarding state.
	 *
	 * @param State $state The state.
	 * @return string
	 */
	private function onboarding_state( $state ): string {
		$current_state = $state->current_state();
		switch ( $current_state ) {
			case $state::STATE_START:
				return esc_html__( 'Start', 'woocommerce-paypal-payments' );
			case $state::STATE_PROGRESSIVE:
				return esc_html__( 'Progressive', 'woocommerce-paypal-payments' );
			case $state::STATE_ONBOARDED:
				return esc_html__( 'Onboarded', 'woocommerce-paypal-payments' );
			default:
				return esc_html__( 'Unknown', 'woocommerce-paypal-payments' );
		}
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
