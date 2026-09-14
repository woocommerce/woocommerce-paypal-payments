<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CardFieldStylesTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private CardFieldStyles $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->sut = new CardFieldStyles();
	}

	/**
	 * GIVEN no merchant has hooked into the card field styles filter
	 * WHEN the overrides are resolved
	 * THEN no style overrides are sent to the browser
	 */
	public function test_returns_empty_array_when_no_filter_is_registered(): void
	{
		when('apply_filters')->justReturn(array());

		$this->assertSame(array(), $this->sut->overrides());
	}

	/**
	 * GIVEN a merchant supplies a map of camelCase CSS properties via the filter
	 * WHEN the overrides are resolved
	 * THEN the map is returned unchanged
	 */
	public function test_returns_the_filtered_map_as_supplied(): void
	{
		$styles = array(
			'fontSize'   => '18px',
			'color'      => '#333333',
			'fontFamily' => 'Arial',
		);

		when('apply_filters')->justReturn($styles);

		$this->assertSame($styles, $this->sut->overrides());
	}

	/**
	 * GIVEN a filter returns a map that mixes valid string values with invalid ones
	 * WHEN the overrides are resolved
	 * THEN only the string-keyed, string-valued entries reach the browser
	 *
	 * @dataProvider invalid_entry_provider
	 */
	public function test_drops_invalid_entries(array $styles, array $expected): void
	{
		when('apply_filters')->justReturn($styles);

		$this->assertSame($expected, $this->sut->overrides());
	}

	public function invalid_entry_provider(): array
	{
		return array(
			'integer value is dropped' => array(
				array('fontSize' => 18),
				array(),
			),
			'array value is dropped' => array(
				array('fontSize' => array('18px')),
				array(),
			),
			'null value is dropped' => array(
				array('fontSize' => null),
				array(),
			),
			'boolean value is dropped' => array(
				array('fontSize' => true),
				array(),
			),
			'numeric key is dropped' => array(
				array(0 => '18px'),
				array(),
			),
			'mixed valid and invalid entries keep only the valid ones' => array(
				array(
					'fontSize' => '18px',
					'color'    => 42,
					0          => 'ignored',
					'weight'   => 'bold',
				),
				array(
					'fontSize' => '18px',
					'weight'   => 'bold',
				),
			),
		);
	}

	/**
	 * GIVEN a filter returns something other than an array
	 * WHEN the overrides are resolved
	 * THEN no style overrides are sent to the browser instead of raising an error
	 *
	 * @dataProvider non_array_filter_result_provider
	 */
	public function test_returns_empty_array_when_filter_does_not_return_an_array($filter_result): void
	{
		when('apply_filters')->justReturn($filter_result);

		$this->assertSame(array(), $this->sut->overrides());
	}

	public function non_array_filter_result_provider(): array
	{
		return array(
			'string result' => array('not-an-array'),
			'null result'   => array(null),
			'object result' => array((object) array('fontSize' => '18px')),
		);
	}

	/**
	 * GIVEN the merchant's style filter changes between two requests
	 * WHEN overrides() is called twice on the same instance
	 * THEN each call reflects the filter's current value instead of a value cached
	 * at construction time, since this class exists to remove that stale-cache bug
	 */
	public function test_does_not_cache_the_result_between_calls(): void
	{
		expect('apply_filters')
			->twice()
			->with('woocommerce_paypal_payments_card_fields_styles', array())
			->andReturn(array('fontSize' => '18px'), array('fontSize' => '24px'));

		$first  = $this->sut->overrides();
		$second = $this->sut->overrides();

		$this->assertSame(array('fontSize' => '18px'), $first);
		$this->assertSame(array('fontSize' => '24px'), $second);
	}
}
