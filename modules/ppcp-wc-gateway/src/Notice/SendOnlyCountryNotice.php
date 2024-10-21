<?php
/**
 * Creates an admin message that notifies user about send only country.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\Onboarding\State;

/**
 * Creates an admin message that notifies user about send only country.
 */
class SendOnlyCountryNotice {

	/**
	 * Notice text
	 *
	 * @var string
	 */
	protected string $message_text;

	/**
	 * Indicates if current country is on the send only list
	 *
	 * @var bool
	 */
	protected bool $is_send_only_country;
	/**
	 * Indicates if we're on the WooCommerce gateways list page.
	 *
	 * @var bool
	 */
	private bool $is_wc_gateways_list_page;

	/**
	 * Indicates if we're on a PPCP Settings page.
	 *
	 * @var bool
	 */
	private bool $is_ppcp_settings_page;

	/**
	 * Onboarding state
	 *
	 * @var int
	 */
	private int $onboarding_state;

	/**
	 * AdminNotice constructor.
	 *
	 * @param string $message_text The message text.
	 * @param bool   $is_send_only_country Determines if current WC country is a send only country.
	 * @param bool   $is_ppcp_settings_page Determines if current page is ppcp settings page.
	 * @param bool   $is_wc_gateways_list_page Determines if current page is ppcp gateway list page.
	 * @param int    $onboarding_state Determines current onboarding state.
	 */
	public function __construct(
		string $message_text,
		bool $is_send_only_country,
		bool $is_ppcp_settings_page,
		bool $is_wc_gateways_list_page,
		int $onboarding_state
	) {
		$this->message_text             = $message_text;
		$this->is_send_only_country     = $is_send_only_country;
		$this->is_ppcp_settings_page    = $is_ppcp_settings_page;
		$this->is_wc_gateways_list_page = $is_wc_gateways_list_page;
		$this->onboarding_state         = $onboarding_state;
	}

	/**
	 * Returns the message.
	 *
	 * @return Message|null
	 */
	public function message(): ?Message {

		if ( ! $this->is_send_only_country ||
			! $this->is_ppcp_page() ||
			$this->onboarding_state === State::STATE_START
		) {
			return null;
		}

		return new Message(
			$this->message_text,
			'warning',
			true,
			'ppcp-notice-wrapper'
		);
	}

	/**
	 * Checks if current page is ppcp page
	 *
	 * @return bool
	 */
	protected function is_ppcp_page():bool {
		return $this->is_ppcp_settings_page || $this->is_wc_gateways_list_page;
	}
}
