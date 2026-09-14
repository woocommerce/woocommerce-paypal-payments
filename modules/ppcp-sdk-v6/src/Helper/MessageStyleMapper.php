<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
/**
 * Maps admin Pay Later messaging settings to v6 Web Component attributes.
 */
class MessageStyleMapper
{
    /**
     * Maps admin logo types to v6 logo-type values.
     */
    private const LOGO_TYPE_MAP = array('primary' => 'WORDMARK', 'alternative' => 'MONOGRAM', 'inline' => 'WORDMARK', 'none' => 'TEXT');
    /**
     * Maps admin logo positions to v6 logo-position values.
     *
     * INLINE is absent because the admin setting that asks for it
     * is the logo *type*, not the position, so an entry here
     * would never be reached. See styles_for_location().
     */
    private const LOGO_POSITION_MAP = array('left' => 'LEFT', 'right' => 'RIGHT', 'top' => 'TOP');
    /**
     * Maps admin text colors to v6 text-color values.
     *
     * There is no v6 "grayscale", so MONOCHROME is its nearest neighbour.
     */
    private const TEXT_COLOR_MAP = array('black' => 'BLACK', 'white' => 'WHITE', 'monochrome' => 'MONOCHROME', 'grayscale' => 'MONOCHROME');
    /**
     * The font-size range the v6 component accepts, in px.
     */
    private const FONT_SIZE_MIN = 10;
    private const FONT_SIZE_MAX = 16;
    private SettingsProvider $settings_provider;
    public function __construct(SettingsProvider $settings_provider)
    {
        $this->settings_provider = $settings_provider;
    }
    /**
     * Returns v6 message attributes for a messaging settings location.
     *
     * The v6 messaging component styles text messages only: it has no
     * equivalent for the admin "banner" (flex) layout, its flex color, or
     * its aspect ratio, so those three settings are not read
     * and a banner-configured location renders as a text message.
     *
     * @param string $location The messaging settings location (cart, checkout, product, ...).
     * @return array{logoType: string, logoPosition: string, textColor: string, fontSize: string}
     */
    public function styles_for_location(string $location): array
    {
        $style = $this->settings_provider->pay_later_messaging_style($location);
        $logo_type = (string) ($style['logo_type'] ?? '');
        $logo_position = (string) ($style['logo_position'] ?? '');
        $text_color = (string) ($style['text_color'] ?? '');
        return array('logoType' => self::LOGO_TYPE_MAP[$logo_type] ?? 'WORDMARK', 'logoPosition' => 'inline' === $logo_type ? 'INLINE' : self::LOGO_POSITION_MAP[$logo_position] ?? 'LEFT', 'textColor' => self::TEXT_COLOR_MAP[$text_color] ?? 'BLACK', 'fontSize' => $this->font_size($style['text_size'] ?? ''));
    }
    /**
     * Converts the admin text size into a CSS length for
     * --paypal-message-font-size, or an empty string when it is unusable.
     *
     * @param mixed $text_size The configured text size.
     */
    private function font_size($text_size): string
    {
        if (!is_numeric($text_size)) {
            return '';
        }
        $size = (int) $text_size;
        $size = max(self::FONT_SIZE_MIN, min(self::FONT_SIZE_MAX, $size));
        return $size . 'px';
    }
}
