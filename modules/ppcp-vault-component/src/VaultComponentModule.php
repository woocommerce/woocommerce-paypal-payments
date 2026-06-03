<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\VaultComponent;

use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\VaultComponent\Authentication\VaultClientToken;
use WooCommerce\PayPalCommerce\VaultComponent\Endpoint\CreateVaultOrderEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class VaultComponentModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	public function run( ContainerInterface $c ): bool {
		$eligibility_check = $c->get( 'vault-component.eligibility.check' );
		if ( ! $eligibility_check() ) {
			return true;
		}

		add_filter(
			'woocommerce_paypal_payments_sdk_components_hook',
			static function ( array $components ): array {
				$components[] = 'saved-payment-methods';
				return $components;
			}
		);

		add_action(
			'wc_ajax_' . CreateVaultOrderEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'vault-component.endpoint.create-order' );
				assert( $endpoint instanceof CreateVaultOrderEndpoint );

				$endpoint->handle_request();
			}
		);

		$vault_injected = false;
		add_filter(
			'woocommerce_payment_gateway_get_saved_payment_method_option_html',
			static function ( string $html, WC_Payment_Token $token, $gateway ) use ( &$vault_injected ): string {
				if ( $vault_injected || PayPalGateway::ID !== $gateway->id || ! $token instanceof PaymentTokenPayPal ) {
					return $html;
				}
				$vault_injected = true;
				$html           = preg_replace( '/<label\b/', '<label style="display:none"', $html, 1 ) ?? $html;
				return str_replace( '</li>', '<div id="ppcp-vault-component"></div></li>', $html );
			},
			10,
			3
		);

		add_action(
			'woocommerce_paypal_payments_after_order_processor',
			function ( WC_Order $wc_order, Order $order ) {
				$this->maybe_update_token_fi_details( $order );
			},
			10,
			2
		);

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
		$vault_id      = (string) $primary_token->get_token();

		$localized_script_data['vault_component'] = array(
			'is_eligible'  => true,
			'token_id'     => $vault_id,
			'wc_token_id'  => (string) $primary_token->get_id(),
			'ajax'         => array(
				'create_order' => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreateVaultOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreateVaultOrderEndpoint::nonce() ),
				),
			),
		);

		try {
			$client_token = $c->get( 'vault-component.auth.client-token' );
			assert( $client_token instanceof VaultClientToken );

			$localized_script_data['vault_component']['sdk_client_token'] = $client_token->client_token( $vault_id );
		} catch ( RuntimeException $exception ) {
			$logger = $c->get( 'woocommerce.logger.woocommerce' );
			assert( $logger instanceof LoggerInterface );

			$message = $exception instanceof PayPalApiException
				? $exception->get_details( $exception->getMessage() )
				: $exception->getMessage();

			$logger->error( 'Failed to mint vault client_token: ' . $message );
		}

		return $localized_script_data;
	}

	/**
	 * After Path A capture, update the WC payment token with the new FI details
	 * from the PayPal order response.
	 */
	private function maybe_update_token_fi_details( Order $order ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$approved_order_id = wc_clean( wp_unslash( $_POST['paypal_order_id'] ?? '' ) );
		if ( ! $approved_order_id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token_db_id = wc_clean( wp_unslash( $_POST['wc-ppcp-gateway-payment-token'] ?? '' ) );
		if ( ! $token_db_id || 'new' === $token_db_id ) {
			return;
		}

		$token = WC_Payment_Tokens::get( (int) $token_db_id );
		if ( ! $token instanceof WC_Payment_Token ) {
			return;
		}

		$payment_source = $order->payment_source();
		if ( ! $payment_source ) {
			return;
		}

		$props      = $payment_source->properties();
		$card_brand = $props->brand ?? '';
		$card_last4 = $props->last_digits ?? '';

		if ( $card_brand ) {
			$token->update_meta_data( '_ppcp_card_brand', $card_brand );
		}
		if ( $card_last4 ) {
			$token->update_meta_data( '_ppcp_card_last4', $card_last4 );
		}

		if ( $card_brand || $card_last4 ) {
			$token->save();
		}
	}
}
