<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use Mockery;

class TestCase extends \PHPUnit\Framework\TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		when('__')->returnArg();
		when('_x')->returnArg();
		when('esc_url')->returnArg();
		when('esc_attr')->returnArg();
		when('esc_attr__')->returnArg();
		when('esc_html')->returnArg();
		when('esc_html__')->returnArg();
		when('esc_textarea')->returnArg();
		when('sanitize_text_field')->returnArg();
		when('wp_kses_post')->returnArg();
		when('wp_unslash')->returnArg();
		when('wc_print_r')->alias(function ($value, bool $return = false) {
			return print_r($value, $return);
		});
		when('wc_string_to_bool')->alias(function ($string) {
			return is_bool( $string ) ? $string : ( 'yes' === strtolower( $string ) || 1 === $string || 'true' === strtolower( $string ) || '1' === $string );
		});
		when('get_file_data')->justReturn(['Version' => '1.0','PluginURI' => 'https://github.com/woocommerce/woocommerce-paypal-payments']);
		when('get_plugin_data')->justReturn(['Version' => '1.0']);
		when('plugin_basename')->justReturn('woocommerce-paypal-payments/woocommerce-paypal-payments.php');
		when('get_transient')->returnArg();

		when('wc_clean')->returnArg();
		when('get_transient')->returnArg();
		when('delete_transient')->returnArg();
		when('wcs_get_subscription')->returnArg();

		setUp();
	}

	public function tearDown(): void
	{
		tearDown();
		Mockery::close();
		parent::tearDown();
	}
}
