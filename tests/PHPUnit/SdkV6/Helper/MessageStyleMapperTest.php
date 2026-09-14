<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;

class MessageStyleMapperTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const LOCATION = 'checkout';

    private function mapperFor(array $style): MessageStyleMapper
    {
        $provider = Mockery::mock(SettingsProvider::class);
        $provider
            ->shouldReceive('pay_later_messaging_style')
            ->with(self::LOCATION)
            ->andReturn($style);

        return new MessageStyleMapper($provider);
    }

    /**
     * GIVEN an admin logo type and logo position configured for a messaging location
     * WHEN the v6 message styles are mapped for that location
     * THEN the logo type maps to its v6 Web Component value
     * AND an "inline" logo type forces the logo position to INLINE, overriding whatever
     *     logo_position was separately configured
     *
     * @dataProvider logoTypeData
     */
    public function testMapsLogoTypesAndInlinePosition(
        string $logoType,
        string $logoPosition,
        string $expectedLogoType,
        string $expectedLogoPosition
    ): void {
        $result = $this->mapperFor([
            'logo_type'     => $logoType,
            'logo_position' => $logoPosition,
            'text_color'    => 'black',
            'text_size'     => '',
        ])->styles_for_location(self::LOCATION);

        $this->assertSame($expectedLogoType, $result['logoType']);
        $this->assertSame($expectedLogoPosition, $result['logoPosition']);
    }

    public function logoTypeData(): array
    {
        return [
            'primary maps to WORDMARK, keeps configured position'      => ['primary', 'left', 'WORDMARK', 'LEFT'],
            'alternative maps to MONOGRAM, keeps configured position'  => ['alternative', 'right', 'MONOGRAM', 'RIGHT'],
            'none maps to TEXT, keeps configured position'             => ['none', 'top', 'TEXT', 'TOP'],
            'inline maps to WORDMARK and forces INLINE position'       => ['inline', 'right', 'WORDMARK', 'INLINE'],
        ];
    }

    /**
     * GIVEN an admin text color configured for a messaging location
     * WHEN the v6 message styles are mapped for that location
     * THEN the text color maps to its v6 Web Component value
     * AND the v5-only "grayscale" option maps onto v6's nearest neighbour, MONOCHROME
     *
     * @dataProvider textColorData
     */
    public function testMapsTextColors(string $textColor, string $expected): void
    {
        $result = $this->mapperFor([
            'logo_type'     => 'primary',
            'logo_position' => 'left',
            'text_color'    => $textColor,
            'text_size'     => '',
        ])->styles_for_location(self::LOCATION);

        $this->assertSame($expected, $result['textColor']);
    }

    public function textColorData(): array
    {
        return [
            'black maps to BLACK'           => ['black', 'BLACK'],
            'white maps to WHITE'           => ['white', 'WHITE'],
            'monochrome maps to MONOCHROME' => ['monochrome', 'MONOCHROME'],
            'grayscale maps to MONOCHROME'  => ['grayscale', 'MONOCHROME'],
        ];
    }

    /**
     * GIVEN an admin text size configured for a messaging location
     * WHEN the v6 message styles are mapped for that location
     * THEN the size is clamped into the v6 component's 10-16px range
     * AND a non-numeric size yields no font size at all
     *
     * @dataProvider textSizeData
     */
    public function testClampsTextSizeIntoSupportedRange(string $textSize, string $expected): void
    {
        $result = $this->mapperFor([
            'logo_type'     => 'primary',
            'logo_position' => 'left',
            'text_color'    => 'black',
            'text_size'     => $textSize,
        ])->styles_for_location(self::LOCATION);

        $this->assertSame($expected, $result['fontSize']);
    }

    public function textSizeData(): array
    {
        return [
            'below the minimum is clamped up to 10px' => ['8', '10px'],
            'within range is kept as-is'               => ['12', '12px'],
            'above the maximum is clamped down to 16px' => ['20', '16px'],
            'empty value yields no font size'          => ['', ''],
            'non-numeric value yields no font size'    => ['abc', ''],
        ];
    }

    /**
     * GIVEN a location configured with the admin "banner" (flex) layout, a flex color
     *       and an aspect ratio
     * WHEN the v6 message styles are mapped for that location
     * THEN the mapper still returns ordinary text message styles, because v6 has no
     *      flex/banner layout equivalent — the flex-only keys are never read
     */
    public function testFlexLayoutSettingsAreIgnoredAndTextStylesAreReturned(): void
    {
        $result = $this->mapperFor([
            'layout'        => 'flex',
            'flex_color'    => 'blue',
            'ratio'         => '8x1',
            'logo_type'     => 'alternative',
            'logo_position' => 'top',
            'text_color'    => 'white',
            'text_size'     => '14',
        ])->styles_for_location(self::LOCATION);

        $this->assertSame([
            'logoType'     => 'MONOGRAM',
            'logoPosition' => 'TOP',
            'textColor'    => 'WHITE',
            'fontSize'     => '14px',
        ], $result);
    }

    /**
     * GIVEN unrecognised/garbage values for every mapped setting
     * WHEN the v6 message styles are mapped for that location
     * THEN the mapper falls back to its safe defaults: WORDMARK, LEFT and BLACK
     */
    public function testUnknownValuesFallBackToDefaults(): void
    {
        $result = $this->mapperFor([
            'logo_type'     => 'bogus',
            'logo_position' => 'bogus',
            'text_color'    => 'bogus',
            'text_size'     => '',
        ])->styles_for_location(self::LOCATION);

        $this->assertSame('WORDMARK', $result['logoType']);
        $this->assertSame('LEFT', $result['logoPosition']);
        $this->assertSame('BLACK', $result['textColor']);
    }
}
