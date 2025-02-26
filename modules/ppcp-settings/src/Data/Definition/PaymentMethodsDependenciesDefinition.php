<?php
/**
 * Payment Methods Dependencies Definition
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data\Definition
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data\Definition;

use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;

/**
 * Class PaymentMethodsDependenciesDefinition
 *
 * Defines dependency relationships between payment methods.
 */
class PaymentMethodsDependenciesDefinition {

	/**
	 * Get all payment method dependencies
	 *
	 * Maps dependent method ID => array of parent method IDs.
	 * A dependent method is disabled if ANY of its required parents is disabled.
	 *
	 * @return array The dependency relationships between payment methods
	 */
	public function get_dependencies(): array {
		$dependencies = array(
			CardButtonGateway::ID     => array( PayPalGateway::ID ),
			CreditCardGateway::ID     => array( PayPalGateway::ID ),
			AxoGateway::ID            => array( PayPalGateway::ID, CreditCardGateway::ID ),
			ApplePayGateway::ID       => array( PayPalGateway::ID, CreditCardGateway::ID ),
			GooglePayGateway::ID      => array( PayPalGateway::ID, CreditCardGateway::ID ),
			BancontactGateway::ID     => array( PayPalGateway::ID ),
			BlikGateway::ID           => array( PayPalGateway::ID ),
			EPSGateway::ID            => array( PayPalGateway::ID ),
			IDealGateway::ID          => array( PayPalGateway::ID ),
			MultibancoGateway::ID     => array( PayPalGateway::ID ),
			MyBankGateway::ID         => array( PayPalGateway::ID ),
			P24Gateway::ID            => array( PayPalGateway::ID ),
			TrustlyGateway::ID        => array( PayPalGateway::ID ),
			PayUponInvoiceGateway::ID => array( PayPalGateway::ID ),
			OXXO::ID                  => array( PayPalGateway::ID ),
			'venmo'                   => array( PayPalGateway::ID ),
			'pay-later'               => array( PayPalGateway::ID ),
		);

		return apply_filters(
			'woocommerce_paypal_payments_payment_method_dependencies',
			$dependencies
		);
	}

	/**
	 * Create a mapping from parent methods to their dependent methods
	 *
	 * @return array Parent-to-child dependency map
	 */
	public function get_dependents_map(): array {
		$result       = array();
		$dependencies = $this->get_dependencies();

		foreach ( $dependencies as $child_id => $parent_ids ) {
			foreach ( $parent_ids as $parent_id ) {
				if ( ! isset( $result[ $parent_id ] ) ) {
					$result[ $parent_id ] = array();
				}
				$result[ $parent_id ][] = $child_id;
			}
		}

		return $result;
	}

	/**
	 * Get all parent methods that a method depends on
	 *
	 * @param string $method_id Method ID to check.
	 * @return array Array of parent method IDs
	 */
	public function get_parent_methods( string $method_id ): array {
		return $this->get_dependencies()[ $method_id ] ?? array();
	}

	/**
	 * Get methods that depend on a parent method
	 *
	 * @param string $parent_id Parent method ID.
	 * @return array Array of dependent method IDs
	 */
	public function get_dependent_methods( string $parent_id ): array {
		return $this->get_dependents_map()[ $parent_id ] ?? array();
	}
}
