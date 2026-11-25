<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\SdkV6;

use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;
use WooCommerce\PayPalCommerce\Tests\Integration\Traits\ReadsStoreApiUrls;

class FrontendRestUrlTest extends TestCase
{
	use ReadsStoreApiUrls;

	/**
	 * GIVEN a callback that adds a query argument on the front-end REST URL filter
	 * WHEN the SDK v6 script data is generated
	 * THEN each Store API URL has exactly one query string, and the argument is its last argument
	 * AND the sub-route URLs are full routes, not the cart URL with the route appended after its query
	 */
	public function test_store_api_urls_put_the_added_argument_at_the_end_of_a_single_query_string(): void
	{
		add_filter(
			'woocommerce_paypal_payments_frontend_rest_url',
			static fn ($url) => add_query_arg('ppcp_test', '1', $url)
		);

		foreach ($this->store_api_urls() as $key => $url) {
			$this->assertSame(1, substr_count($url, '?'), $key);
			$this->assertSame('ppcp_test=1', substr($url, -strlen('ppcp_test=1')), $key);
			$this->assertSameUrl(add_query_arg('ppcp_test', '1', rest_url($this->routes()[ $key ])), $url, $key);
		}
	}

	/**
	 * GIVEN a callback on the front-end REST URL filter
	 * WHEN the SDK v6 script data is generated
	 * THEN the filter receives the full REST URL and its route for each of the 3 Store API URLs
	 */
	public function test_front_end_rest_url_filter_receives_the_url_and_route_of_each_store_api_url(): void
	{
		$received = array();
		add_filter(
			'woocommerce_paypal_payments_frontend_rest_url',
			static function ($url, $route = '') use (&$received) {
				$received[ $route ] = $url;
				return $url;
			},
			1,
			2
		);

		$this->store_api_urls();

		$expected = array();
		foreach ($this->routes() as $route) {
			$expected[ $route ] = rest_url($route);
		}
		ksort($expected);
		ksort($received);
		$this->assertSame($expected, $received);
	}

	/**
	 * GIVEN a callback on the front-end REST URL filter that returns an unusable value
	 * WHEN the SDK v6 script data is generated
	 * THEN the Store API URLs are the unfiltered REST URLs
	 *
	 * @dataProvider unusable_filter_result_provider
	 * @param mixed $result
	 */
	public function test_store_api_urls_fall_back_to_the_unfiltered_url_when_filter_result_is_unusable($result): void
	{
		add_filter('woocommerce_paypal_payments_frontend_rest_url', static fn () => $result, 99);

		$this->assertSame($this->plain_urls(), $this->store_api_urls());
	}

	public function unusable_filter_result_provider(): array
	{
		return array(
			'null'         => array(null),
			'empty string' => array(''),
		);
	}
}
