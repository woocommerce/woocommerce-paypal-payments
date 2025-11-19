<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\AgenticWebhookConfiguration
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
	 * WHEN the registration install URL is requested
	 * THEN the appropriate environment-specific install endpoint is returned
	 *
	 * @dataProvider environment_specific_url_provider
	 */
	public function test_registration_install_url_returns_environment_specific_endpoint(
		bool $is_production,
		string $expected_install_url,
		string $expected_uninstall_url,
		string $expected_ingestion_url
	): void {
		$this->connection_state->allows( 'is_production' )->andReturn( $is_production );

		$result = $this->testee->get_registration_install_url();

		$this->assertSame( $expected_install_url, $result );
	}

	/**
	 * GIVEN a merchant connected in sandbox or production environment
	 * WHEN the registration uninstall URL is requested
	 * THEN the appropriate environment-specific uninstall endpoint is returned
	 *
	 * @dataProvider environment_specific_url_provider
	 */
	public function test_registration_uninstall_url_returns_environment_specific_endpoint(
		bool $is_production,
		string $expected_install_url,
		string $expected_uninstall_url,
		string $expected_ingestion_url
	): void {
		$this->connection_state->allows( 'is_production' )->andReturn( $is_production );

		$result = $this->testee->get_registration_uninstall_url();

		$this->assertSame( $expected_uninstall_url, $result );
	}

	/**
	 * GIVEN a merchant connected in sandbox or production environment
	 * WHEN the product ingestion URL is requested
	 * THEN the appropriate environment-specific ingestion endpoint is returned
	 *
	 * @dataProvider environment_specific_url_provider
	 */
	public function test_product_ingestion_url_returns_environment_specific_endpoint(
		bool $is_production,
		string $expected_install_url,
		string $expected_uninstall_url,
		string $expected_ingestion_url
	): void {
		$this->connection_state->allows( 'is_production' )->andReturn( $is_production );

		$result = $this->testee->get_product_ingestion_url();

		$this->assertSame( $expected_ingestion_url, $result );
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
				'https://d-sandbox.joinhoney.com/webhooks/ws/install',
				'https://d-sandbox.joinhoney.com/webhooks/ws/uninstall',
				'https://d-sandbox.joinhoney.com/webhooks/products',
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
