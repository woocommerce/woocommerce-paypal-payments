<?php
declare( strict_types=1 );

namespace PHPUnit\PpcpSettings\Service;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Service\AuthenticationManager;
use WooCommerce\PayPalCommerce\Settings\Service\InternalRestService;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\WcGateway\Helper\EnvironmentConfig;

class AuthenticationManagerValidationTest extends TestCase {
	private AuthenticationManager $sut;

	public function setUp(): void {
		parent::setUp();

		$this->sut = new AuthenticationManager(
			Mockery::mock( GeneralSettings::class ),
			Mockery::mock( EnvironmentConfig::class ),
			Mockery::mock( EnvironmentConfig::class ),
			Mockery::mock( PartnerReferralsData::class ),
			Mockery::mock( ConnectionState::class ),
			Mockery::mock( InternalRestService::class )
		);
	}

	public function test_empty_client_id_throws(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'No client ID provided.' );

		$this->sut->validate_id_and_secret( '', 'Avalid_secret_that_is_exactly_eighty_characters_long_padded_to_meet_length_needed' );
	}

	public function test_invalid_client_id_pattern_throws(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Invalid client ID provided.' );

		$this->sut->validate_id_and_secret( 'too-short', 'Avalid_secret_that_is_exactly_eighty_characters_long_padded_to_meet_length_needed' );
	}

	public function test_empty_client_secret_throws(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'No client secret provided.' );

		$valid_id = str_repeat( 'a', 80 );
		$this->sut->validate_id_and_secret( $valid_id, '' );
	}

	public function test_invalid_client_secret_pattern_throws(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Invalid client secret provided.' );

		$valid_id = str_repeat( 'a', 80 );
		$this->sut->validate_id_and_secret( $valid_id, 'invalid' );
	}

	public function test_valid_credentials_pass(): void {
		$valid_id     = str_repeat( 'a', 80 );
		$valid_secret = str_repeat( 'b', 80 );

		$this->sut->validate_id_and_secret( $valid_id, $valid_secret );
		$this->addToAssertionCount( 1 );
	}

	public function test_valid_credentials_not_starting_with_a_pass(): void {
		$valid_id     = str_repeat( 'a', 80 );
		$valid_secret = 'E' . str_repeat( 'b', 79 );

		$this->sut->validate_id_and_secret( $valid_id, $valid_secret );
		$this->addToAssertionCount( 1 );
	}
}
