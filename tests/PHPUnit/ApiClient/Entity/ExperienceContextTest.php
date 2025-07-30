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
			->with_payment_method_preference('UNRESTRICTED')
			->with_contact_preference('NO_CONTACT_INFO')
			->with_order_update_callback_config(new CallbackConfig(
				[CallbackConfig::EVENT_SHIPPING_ADDRESS, CallbackConfig::EVENT_SHIPPING_OPTIONS],
				'example.com/callback',
			));

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
			'contact_preference' => 'NO_CONTACT_INFO',
			'order_update_callback_config' => [
				'callback_events' => [CallbackConfig::EVENT_SHIPPING_ADDRESS, CallbackConfig::EVENT_SHIPPING_OPTIONS],
				'callback_url' => 'example.com/callback',
			]
		], $result->to_array());
    }
}
