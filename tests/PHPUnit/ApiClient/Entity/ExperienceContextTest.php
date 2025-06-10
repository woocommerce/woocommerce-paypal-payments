<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\TestCase;

class ExperienceContextTest extends TestCase
{
    public function testAllProps()
    {
		$empty = new ExperienceContext();

		$result = $empty
			->with_return_url('example.com')
			->with_cancel_url('example.com?cancelled')
			->with_brand_name('company')
			->with_locale('de_DE')
			->with_landing_page('NO_PREFERENCE')
			->with_shipping_preference('NO_SHIPPING')
			->with_user_action('CONTINUE')
			->with_payment_method_preference('UNRESTRICTED');

		$this->assertEmpty($empty->to_array());

		$this->assertEquals([
			'return_url' => 'example.com',
			'cancel_url' => 'example.com?cancelled',
			'brand_name' => 'company',
			'locale'      => 'de_DE',
			'landing_page' => 'NO_PREFERENCE',
			'shipping_preference' => 'NO_SHIPPING',
			'user_action' => 'CONTINUE',
			'payment_method_preference' => 'UNRESTRICTED',
		], $result->to_array());
    }
}
