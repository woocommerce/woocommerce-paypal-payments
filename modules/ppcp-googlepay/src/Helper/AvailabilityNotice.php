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
	 * Indicates if we're on the WooCommerce gateways list page.
	 *
	 * @var bool
	 */
	private $is_wc_gateways_list_page;

	/**
	 * Indicates if we're on a PPCP Settings page.
	 *
	 * @var bool
	 */
	private $is_ppcp_settings_page;

	/**
	 * Class ApmProductStatus constructor.

	 * @param ApmProductStatus $product_status The product status handler.
	 * @param bool             $is_wc_gateways_list_page Indicates if we're on the WooCommerce gateways list page.
	 * @param bool             $is_ppcp_settings_page Indicates if we're on a PPCP Settings page.
	 */
	public function __construct(
		ApmProductStatus $product_status,
		bool $is_wc_gateways_list_page,
		bool $is_ppcp_settings_page
	) {
		$this->product_status           = $product_status;
		$this->is_wc_gateways_list_page = $is_wc_gateways_list_page;
		$this->is_ppcp_settings_page    = $is_ppcp_settings_page;
	}

	/**
	 * Adds availability notice if applicable.
	 *
	 * @return void
	 */
	public function execute(): void {
		if ( ! $this->should_display() ) {
			return;
		}

		// We need to check is active before checking failure requests, otherwise failure status won't be set.
		$is_active = $this->product_status->is_active();

		if ( $this->product_status->has_request_failure() ) {
			$this->add_seller_status_failure_notice();
		} elseif ( ! $is_active ) {
			$this->add_not_available_notice();
		}
	}

	/**
	 * Whether the message should display.
	 *
	 * @return bool
	 */
	protected function should_display(): bool {
		if ( ! $this->product_status->is_onboarded() ) {
			return false;
		}
		if ( ! $this->is_wc_gateways_list_page && ! $this->is_ppcp_settings_page ) {
			return false;
		}
		return true;
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
					// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__(
						'<p>Notice: We could not determine your PayPal seller status to list your available features. Disconnect and reconnect your PayPal account through our %1$sonboarding process%2$s to resolve this.</p><p>Don\'t worry if you cannot use the %1$sonboarding process%2$s; most functionalities available to your account should work.</p>',
						'woocommerce-paypal-payments'
					),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#connect-paypal-account" target="_blank">',
					'</a>'
				);

				// Name the key so it can be overridden in other modules.
				$notices['error_product_status'] = new Message( $message, 'warning', true, 'ppcp-notice-wrapper' );
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
