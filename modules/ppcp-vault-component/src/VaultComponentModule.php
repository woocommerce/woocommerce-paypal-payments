<?php
/**
 * The Vault Component module.
 *
 * @package WooCommerce\PayPalCommerce\VaultComponent
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\VaultComponent;

use Psr\Log\LoggerInterface;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\UserIdToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class VaultComponentModule
 */
class VaultComponentModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): bool {
		$eligibility_check = $c->get( 'vault-component.eligibility.check' );
		if ( ! $eligibility_check() ) {
			return true;
		}

		add_action(
			'after_setup_theme',
			function () use ( $c ) {
				add_filter(
					'woocommerce_paypal_payments_localized_script_data',
					function ( array $localized_script_data ) use ( $c ): array {
						return $this->maybe_add_vault_component_data( $localized_script_data, $c );
					}
				);
			}
		);

		return true;
	}

	/**
	 * Adds vault component data to localized script data if the
	 * current customer is eligible (customer-level check).
	 *
	 * @param array              $localized_script_data The localized script data.
	 * @param ContainerInterface $c The container.
	 * @return array
	 */
	private function maybe_add_vault_component_data(
		array $localized_script_data,
		ContainerInterface $c
	): array {
		if ( ! is_user_logged_in() ) {
			return $localized_script_data;
		}

		$customer_id = get_current_user_id();
		$wc_tokens   = WC_Payment_Tokens::get_customer_tokens( $customer_id, PayPalGateway::ID );

		$paypal_tokens = array_filter(
			$wc_tokens,
			static function ( $token ) {
				return $token instanceof PaymentTokenPayPal;
			}
		);

		if ( empty( $paypal_tokens ) ) {
			return $localized_script_data;
		}

		$primary_token = reset( $paypal_tokens );

		$logger = $c->get( 'woocommerce.logger.woocommerce' );
		assert( $logger instanceof LoggerInterface );

		// Reuse the ID token from save-payment-methods if already generated.
		if ( ! empty( $localized_script_data['save_payment_methods']['id_token'] ) ) {
			$id_token = $localized_script_data['save_payment_methods']['id_token'];
		} else {
			try {
				$api = $c->get( 'api.user-id-token' );
				assert( $api instanceof UserIdToken );

				$target_customer_id = get_user_meta( $customer_id, '_ppcp_target_customer_id', true );
				if ( ! $target_customer_id ) {
					$target_customer_id = get_user_meta( $customer_id, 'ppcp_customer_id', true );
				}

				$id_token = $api->id_token( $target_customer_id );
			} catch ( RuntimeException $exception ) {
				$error = $exception->getMessage();
				if ( $exception instanceof PayPalApiException ) {
					$error = $exception->get_details( $error );
				}

				$logger->error( 'Vault Component: Failed to get user ID token. ' . $error );
				return $localized_script_data;
			}
		}

		$localized_script_data['vault_component'] = array(
			'is_eligible' => true,
			'token_id'    => $primary_token->get_token(),
			'id_token'    => $id_token,
		);

		return $localized_script_data;
	}
}
