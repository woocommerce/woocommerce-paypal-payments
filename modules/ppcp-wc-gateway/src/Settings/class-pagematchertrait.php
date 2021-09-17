<?php
/**
 * PageMatcherTrait.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Woocommerce\PayPalCommerce\WcGateway\Helper\DccProductStatus;
use Woocommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhooksStatusPage;

/**
 * Class PageMatcherTrait.
 */
trait PageMatcherTrait {

	/**
	 * Checks whether the field config matches the current page (can be rendered here).
	 *
	 * @param array  $field_config The field config (from wcgateway.settings.fields).
	 * @param string $current_page_id ID of the current PPCP gateway settings page.
	 * @return bool
	 */
	protected function field_matches_page( array $field_config, string $current_page_id ): bool {
		$allowed_gateways = $field_config['gateway'];
		if ( ! is_array( $allowed_gateways ) ) {
			$allowed_gateways = array( $allowed_gateways );
		}

		$gateway_page_id_map = array(
			PayPalGateway::ID      => 'paypal',
			CreditCardGateway::ID  => 'dcc', // TODO: consider using just the gateway ID for PayPal and DCC too.
			WebhooksStatusPage::ID => WebhooksStatusPage::ID,
		);
		return array_key_exists( $current_page_id, $gateway_page_id_map )
			&& in_array( $gateway_page_id_map[ $current_page_id ], $allowed_gateways, true );
	}
}
