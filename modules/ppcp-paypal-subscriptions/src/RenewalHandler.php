<?php
/**
 * Subscriptions renewal handler.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use Psr\Log\LoggerInterface;
use WC_Data_Exception;
use WC_Order;
use WC_Subscription;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;

/**
 * Class RenewalHandler
 */
class RenewalHandler {

	use TransactionIdHandlingTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * RenewalHandler constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Process subscription renewal.
	 *
	 * @param WC_Subscription[] $subscriptions WC Subscriptions.
	 * @param string            $transaction_id PayPal transaction ID.
	 * @return void
	 * @throws WC_Data_Exception If something goes wrong while setting payment method.
	 */
	public function process( array $subscriptions, string $transaction_id ): void {
		foreach ( $subscriptions as $subscription ) {
			$is_renewal = $subscription->get_meta( '_ppcp_is_subscription_renewal' ) ?? '';
			if ( $is_renewal ) {
				$renewal_order = wcs_create_renewal_order( $subscription );
				if ( is_a( $renewal_order, WC_Order::class ) ) {
					$renewal_order->set_payment_method( $subscription->get_payment_method() );
					$renewal_order->payment_complete();
					$this->update_transaction_id( $transaction_id, $renewal_order, $this->logger );
					break;
				}
			}

			$parent_order = wc_get_order( $subscription->get_parent() );
			if ( is_a( $parent_order, WC_Order::class ) ) {
				$subscription->update_meta_data( '_ppcp_is_subscription_renewal', 'true' );
				$subscription->save_meta_data();
				$this->update_transaction_id( $transaction_id, $parent_order, $this->logger );
			}
		}
	}
}
