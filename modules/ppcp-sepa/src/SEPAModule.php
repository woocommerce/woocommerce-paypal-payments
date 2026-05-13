<?php
/**
 * The SEPA module.
 *
 * @package WooCommerce\PayPalCommerce\SEPA
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SEPA;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class LocalAlternativePaymentMethodsModule
 */
class SEPAModule implements ServiceModule, ExtendingModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services() : array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function extensions() : array {
		return require __DIR__ . '/../extensions.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): bool {
		add_filter(
			'woocommerce_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $methods ) use ( $c ): array {
				if ( ! is_array( $methods ) ) {
					return $methods;
				}

				$sepa_gateway = $c->get( 'sepa.wc-gateway' );
				assert( $sepa_gateway instanceof WC_Payment_Gateway );

				$methods[] = $sepa_gateway;

				return $methods;
			}
		);

		add_filter(
			'woocommerce_gateway_description',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $description, $id ): string {
				if ( ! is_string( $description ) || ! is_string( $id ) || $id !== SEPAGateway::ID ) {
					return $description;
				}

				ob_start();

				woocommerce_form_field(
					'ppcp_sepa_iban',
					array(
						'type'     => 'text',
						'label'    => esc_html__( 'IBAN', 'woocommerce-paypal-payments' ),
						'class'    => array( 'form-row-wide' ),
						'required' => true,
						'clear'    => true,
					)
				);

				$description .= ob_get_clean() ?: '';

				return $description;
			},
			10,
			2
		);

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c ): void {
				$sepa_payment_method = $c->get( 'sepa.payment-method' );
				assert( $sepa_payment_method instanceof SEPAPaymentMethod );

				$payment_method_registry->register( $sepa_payment_method );
			}
		);

		return true;
	}
}
