<?php
/**
 * Adds availability notice if applicable.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay\Helper;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;

/**
 * Class AvailabilityNotice
 */
class AvailabilityNotice {

	/**
	 * The product status handler.
	 *
	 * @var ApmProductStatus
	 */
	private $product_status;

	/**
	 * Class ApmProductStatus constructor.

	 * @param ApmProductStatus $product_status The product status handler.
	 */
	public function __construct( ApmProductStatus $product_status ) {
		$this->product_status = $product_status;
	}

	/**
	 * Adds availability notice if applicable.
	 *
	 * @return void
	 */
	public function execute(): void {
		if ( ! $this->product_status->is_onboarded() ) {
			return;
		}

		if ( $this->product_status->has_request_failure() ) {
			$this->add_seller_status_failure_notice();
		} else {
			$this->add_not_available_notice();
		}
	}

	/**
	 * Adds seller status failure notice.
	 *
	 * @return void
	 */
	private function add_seller_status_failure_notice(): void {
		add_filter(
			Repository::NOTICES_FILTER,
			/**
			 * Adds seller status notice.
			 *
			 * @param array $notices The notices.
			 * @return array
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $notices ): array {

				$message = sprintf(
					__(
						'<p>There was an error getting your PayPal seller status. Some features may be disabled.</p><p>Certify that you connected to your account via our onboarding process.</p>',
						'woocommerce-paypal-payments'
					)
				);

				// Name the key so it can be overridden in other modules.
				$notices['error_product_status'] = new Message( $message, 'error', true, 'ppcp-notice-wrapper' );
				return $notices;
			}
		);
	}

	/**
	 * Adds not available notice.
	 *
	 * @return void
	 */
	private function add_not_available_notice(): void {
		add_filter(
			Repository::NOTICES_FILTER,
			/**
			 * Adds GooglePay not available notice.
			 *
			 * @param array $notices The notices.
			 * @return array
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $notices ): array {

				$message = sprintf(
					__(
						'Google Pay is not available on your PayPal seller account.',
						'woocommerce-paypal-payments'
					)
				);

				$notices[] = new Message( $message, 'warning', true, 'ppcp-notice-wrapper' );
				return $notices;
			}
		);
	}

}
