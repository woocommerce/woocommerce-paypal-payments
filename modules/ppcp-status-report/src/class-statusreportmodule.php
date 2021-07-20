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
	 * Setup the compatibility module.
	 *
	 * @return ServiceProviderInterface
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * Run the compatibility module.
	 *
	 * @param ContainerInterface|null $container The Container.
	 */
	public function run( ContainerInterface $container ): void {
		/* @var State $state The state */
		$state = $container->get( 'onboarding.state' );

		/* @var Bearer $bearer The bearer */
		$bearer = $container->get( 'api.bearer' );

		/* @var DccApplies $dcc_applies The ddc applies */
		$dcc_applies = $container->get( 'api.helpers.dccapplies' );

		/* @var MessagesApply $messages_apply The messages apply */
		$messages_apply = $container->get( 'button.helper.messages-apply' );

		add_action(
			'woocommerce_system_status_report',
			function() use ( $state, $bearer, $dcc_applies, $messages_apply ) { ?>
			<table class="wc_status_table widefat" id="status">
				<thead>
					<tr>
						<th colspan="3" data-export-label="WooCommerce PayPal Payments">
							<h2><?php esc_html_e( 'WooCommerce PayPal Payments', 'woocommerce-paypal-payments' ); ?></h2>
						</th>
					</tr>
				</thead>

				<tbody>
					<tr>
						<td data-export-label="Onboarding state"><?php esc_html_e( 'Onboarding state', 'woocommerce-paypal-payments' ); ?></td>
						<td>
						<?php
							$current_state = $state->current_state();
						switch ( $current_state ) {
							case $state::STATE_START:
								echo esc_html__( 'Start', 'woocommerce-paypal-payments' );
								break;
							case $state::STATE_PROGRESSIVE:
								echo esc_html__( 'Progressive', 'woocommerce-paypal-payments' );
								break;
							case $state::STATE_ONBOARDED:
								echo esc_html__( 'Onboarded', 'woocommerce-paypal-payments' );
								break;
							default:
								echo esc_html__( 'Unknown', 'woocommerce-paypal-payments' );
						}
						?>
							</td>
					</tr>
					<tr>
						<td data-export-label="Shop country code"><?php esc_html_e( 'Shop country code', 'woocommerce-paypal-payments' ); ?></td>
						<td>
						<?php
							$region = wc_get_base_location();
							echo esc_attr( $region['country'] );
						?>
							</td>
					</tr>
					<tr>
						<td data-export-label="PayPal card processing"><?php esc_html_e( 'PayPal card processing', 'woocommerce-paypal-payments' ); ?></td>
						<td>
						<?php
							echo esc_attr( $dcc_applies->for_country_currency() ? esc_html__( 'Yes', 'woocommerce-paypal-payments' ) : esc_html__( 'No', 'woocommerce-paypal-payments' ) );
						?>
						</td>
					</tr>
					<tr>
						<td data-export-label="Messaging apply"><?php esc_html_e( 'Messaging apply', 'woocommerce-paypal-payments' ); ?></td>
						<td><?php echo esc_attr( $messages_apply->for_country() ? esc_html__( 'Yes', 'woocommerce-paypal-payments' ) : esc_html__( 'No', 'woocommerce-paypal-payments' ) ); ?>
						</td>
					</tr>
					<tr>
						<td data-export-label="Vault enabled"><?php esc_html_e( 'Vault enabled', 'woocommerce-paypal-payments' ); ?></td>
						<td>
						<?php
						try {
							$token = $bearer->bearer();
							echo esc_attr( $token->vaulting_available() ? esc_html__( 'Yes', 'woocommerce-paypal-payments' ) : esc_html__( 'No', 'woocommerce-paypal-payments' ) );
						} catch ( RuntimeException $exception ) {
							echo esc_html__( 'No', 'woocommerce-paypal-payments' );
						}
						?>
						</td>
					</tr>
				</tbody>
			</table>
				<?php
			}
		);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
