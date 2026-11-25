<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Traits;

use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;

trait ReadsStoreApiUrls
{
	private function routes(): array
	{
		return array(
			'cart'                 => 'wc/store/v1/cart',
			'update_customer'      => 'wc/store/v1/cart/update-customer',
			'select_shipping_rate' => 'wc/store/v1/cart/select-shipping-rate',
		);
	}

	private function store_api_urls(): array
	{
		$manager = $this->getContainer()->get('sdk-v6.manager');
		$this->assertInstanceOf(SdkV6Manager::class, $manager);

		$store_api = $manager->script_data()['ajax']['wc_store_api'];

		return array(
			'cart'                 => $store_api['cart'],
			'update_customer'      => $store_api['update_customer'],
			'select_shipping_rate' => $store_api['select_shipping_rate'],
		);
	}

	private function plain_urls(): array
	{
		return array_map('rest_url', $this->routes());
	}

	private function assertSameUrl(string $expected, string $actual, string $message = ''): void
	{
		$this->assertSame($this->url_parts($expected), $this->url_parts($actual), $message);
	}

	private function url_parts(string $url): array
	{
		$parts = (array) wp_parse_url($url);
		wp_parse_str((string) ($parts['query'] ?? ''), $query);
		ksort($query);
		unset($parts['query']);
		$parts['query'] = $query;

		return $parts;
	}
}
