<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Config;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Config\AgenticWebhookConfiguration
 */
class AgenticWebhookConfigurationTest extends TestCase {

	private ConnectionState $connection_state;
	private AgenticWebhookConfiguration $testee;

	public function setUp(): void {
		parent::setUp();

		$this->connection_state = Mockery::mock( ConnectionState::class );
		$this->testee           = new AgenticWebhookConfiguration( $this->connection_state );
	}

	/**
	 * GIVEN a merchant connected in sandbox or production environment
	 * WHEN the webhook URLs are requested
	 * THEN all URLs use the appropriate environment-specific base URL
	 *
	 * @dataProvider environment_specific_url_provider
	 */
	public function test_all_urls_use_environment_specific_base_url(
		bool $is_production,
		string $expected_install_url,
		string $expected_uninstall_url,
		string $expected_ingestion_url
	): void {
		$this->connection_state->allows( 'is_production' )->andReturn( $is_production );

		$this->assertSame( $expected_install_url, $this->testee->get_registration_install_url() );
		$this->assertSame( $expected_uninstall_url, $this->testee->get_registration_uninstall_url() );
		$this->assertSame( $expected_ingestion_url, $this->testee->get_product_ingestion_url() );
	}

	/**
	 * Provides environment configurations with their expected URLs.
	 *
	 * @return array<string, array{bool, string, string, string}>
	 */
	public function environment_specific_url_provider(): array {
		return array(
			'sandbox environment uses sandbox base URL' => array(
				false,
				'https://d-staging.joinhoney.com/webhooks/ws/install',
				'https://d-staging.joinhoney.com/webhooks/ws/uninstall',
				'https://d-staging.joinhoney.com/webhooks/products',
			),
			'production environment uses live base URL' => array(
				true,
				'https://d.joinhoney.com/webhooks/ws/install',
				'https://d.joinhoney.com/webhooks/ws/uninstall',
				'https://d.joinhoney.com/webhooks/products',
			),
		);
	}
}
