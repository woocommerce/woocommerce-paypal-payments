<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\WpmlCompat
 */
class WpmlCompatTest extends TestCase {

	private const PRETTY_URL = 'https://example.com/wp-json/wc/store/v1/cart';

	private $testee;

	public function setUp(): void {
		parent::setUp();

		when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ) {
				return parse_url( $url, $component );
			}
		);
		when( 'add_query_arg' )->alias(
			static function ( string $key, string $value, string $url ): string {
				$parts = explode( '?', $url, 2 );
				parse_str( $parts[1] ?? '', $query );
				$query[ $key ] = $value;

				return $parts[0] . '?' . http_build_query( $query );
			}
		);

		$this->testee = new WpmlCompat();
	}

	private function configure_wpml( $negotiation_type, $current_language, $default_language ): void {
		expectApplied( 'wpml_setting' )
			->zeroOrMoreTimes()
			->with( 0, 'language_negotiation_type' )
			->andReturn( $negotiation_type );
		expectApplied( 'wpml_current_language' )->zeroOrMoreTimes()->andReturn( $current_language );
		expectApplied( 'wpml_default_language' )->zeroOrMoreTimes()->andReturn( $default_language );
	}

	/**
	 * GIVEN WPML negotiates the language by URL parameter
	 * AND the page is shown in German while the default language is English
	 * WHEN a REST URL without a query is filtered
	 * THEN the URL gets lang=de as its query
	 */
	public function test_adds_current_language_to_a_url_without_query(): void {
		$this->configure_wpml( 3, 'de', 'en' );

		$this->assertSame(
			self::PRETTY_URL . '?lang=de',
			$this->testee->add_current_language_to_url( self::PRETTY_URL )
		);
	}

	/**
	 * GIVEN WPML does not use URL-parameter mode, or gives no answer
	 * AND the page is shown in German while the default language is English
	 * WHEN a REST URL is filtered
	 * THEN the URL is unchanged
	 *
	 * @dataProvider data_modes_without_url_parameter
	 */
	public function test_leaves_the_url_unchanged_when_wpml_does_not_use_url_parameter( ?int $negotiation_type ): void {
		if ( null !== $negotiation_type ) {
			$this->configure_wpml( $negotiation_type, 'de', 'en' );
		}

		$this->assertSame(
			self::PRETTY_URL,
			$this->testee->add_current_language_to_url( self::PRETTY_URL )
		);
	}

	public function data_modes_without_url_parameter(): array {
		return array(
			'directory mode'    => array( 1 ),
			'domain mode'       => array( 2 ),
			'no WPML answer'    => array( null ),
		);
	}

	/**
	 * GIVEN WPML negotiates the language by URL parameter
	 * AND the page is shown in the default language
	 * WHEN a REST URL is filtered
	 * THEN the URL is unchanged
	 */
	public function test_leaves_the_url_unchanged_when_page_uses_the_default_language(): void {
		$this->configure_wpml( 3, 'en', 'en' );

		$this->assertSame(
			self::PRETTY_URL,
			$this->testee->add_current_language_to_url( self::PRETTY_URL )
		);
	}

	/**
	 * GIVEN WPML negotiates the language by URL parameter
	 * AND the URL already carries lang=fr
	 * WHEN the URL is filtered
	 * THEN lang=fr stays, and no second lang is added
	 */
	public function test_keeps_an_existing_lang_argument(): void {
		$this->configure_wpml( 3, 'de', 'en' );
		$url = self::PRETTY_URL . '?lang=fr';

		$this->assertSame( $url, $this->testee->add_current_language_to_url( $url ) );
	}

	/**
	 * GIVEN a filter value that is not a string
	 * WHEN it is filtered
	 * THEN the same value is returned
	 */
	public function test_returns_a_non_string_value_unchanged(): void {
		$this->configure_wpml( 3, 'de', 'en' );

		$this->assertNull( $this->testee->add_current_language_to_url( null ) );
	}
}
