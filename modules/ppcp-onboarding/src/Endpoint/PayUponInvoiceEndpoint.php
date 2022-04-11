<?php
/**
 * Handles the onboard with Pay Upon Invoice setting.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Endpoint;

use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

class PayUponInvoiceEndpoint implements EndpointInterface {

	/**
	 * @var Settings
	 */
	protected $settings;

	/**
	 * @var RequestData
	 */
	protected $request_data;

	public function __construct(Settings $settings, RequestData $request_data)
	{
		$this->settings = $settings;
		$this->request_data = $request_data;
	}

	public static function nonce(): string
	{
		return 'ppc-pui';
	}

	public function handle_request(): bool
	{
		try {
			$data = $this->request_data->read_request( $this->nonce() );
			$this->settings->set('ppcp-onboarding-pui', $data['checked']);
			$this->settings->persist();

		} catch (\Exception $exception) {

		}

		wp_send_json_success([
			$this->settings->get('ppcp-onboarding-pui'),
		]);
		return true;
	}
}

