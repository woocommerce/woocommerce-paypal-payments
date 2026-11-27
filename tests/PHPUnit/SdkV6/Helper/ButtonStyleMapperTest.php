<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;

class ButtonStyleMapperTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private function mapperFor(string $color, string $shape, string $label = 'pay'): ButtonStyleMapper
	{
		$dto = new LocationStylingDTO('product', true, array(), $shape, $label, $color);

		$provider = Mockery::mock(SettingsProvider::class);
		$provider
			->shouldReceive('button_styling')
			->with('product')
			->andReturn($dto);

		return new ButtonStyleMapper($provider);
	}

	/**
	 * @dataProvider colorShapeData
	 */
	public function testMapsColorsAndShapesToWebComponentStyles(
		string $color,
		string $shape,
		string $expectedClass,
		string $expectedRadius
	): void {
		$result = $this->mapperFor($color, $shape)->styles_for_context('product');

		$this->assertSame($expectedClass, $result['colorClass']);
		$this->assertSame($expectedRadius, $result['borderRadius']);
	}

	public function colorShapeData(): array
	{
		return array(
			'silver maps to white' => array('silver', 'rect', 'paypal-white', '4px'),
			'unknown color'        => array('rainbow', 'pill', 'paypal-gold', '24px'),
			'unknown shape'        => array('gold', 'hexagon', 'paypal-gold', '24px'),
		);
	}

	public function testEmptyDtoValuesFallBackToDefaults(): void
	{
		$result = $this->mapperFor('', '', '')->styles_for_context('product');

		$this->assertSame('paypal-gold', $result['colorClass']);
		$this->assertSame('24px', $result['borderRadius']);
		$this->assertSame('pay', $result['type']);
	}

	/**
	 * @dataProvider labelTypeData
	 */
	public function testMapsButtonLabelToWebComponentType(string $label, string $expectedType): void
	{
		$result = $this->mapperFor('gold', 'pill', $label)->styles_for_context('product');

		$this->assertSame($expectedType, $result['type']);
	}

	public function labelTypeData(): array
	{
		return array(
			'paypal logo-only button has no type' => array('paypal', ''),
			'checkout label'                      => array('checkout', 'checkout'),
			'buy now label'                       => array('buynow', 'buynow'),
			'pay label'                           => array('pay', 'pay'),
			'empty label defaults to pay'         => array('', 'pay'),
			'unknown label falls back to pay'     => array('installment', 'pay'),
		);
	}

	public function testDoesNotExposeAHeightStyle(): void
	{
		$result = $this->mapperFor('gold', 'pill')->styles_for_context('product');

		$this->assertArrayNotHasKey('height', $result);
	}
}
