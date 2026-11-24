<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Compat;

use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;
use WooCommerce\PayPalCommerce\Tests\Integration\Traits\ReadsStoreApiUrls;

class WpmlCompatTest extends TestCase
{
	use ReadsStoreApiUrls;

	private function configure_wpml(int $negotiation_type, string $current_language, string $default_language): void
	{
		add_filter(
			'wpml_setting',
			static fn ($default, $key = '') => 'language_negotiation_type' === $key ? $negotiation_type : $default,
			10,
			2
		);
		add_filter('wpml_current_language', static fn () => $current_language);
		add_filter('wpml_default_language', static fn () => $default_language);
	}

	/**
	 * GIVEN WPML negotiates the language by URL parameter
	 * AND the page is shown in German while the default language is English
	 * WHEN the SDK v6 script data is generated
	 * THEN each Store API URL is the full Store API route with lang=de as a query argument
	 */
	public function test_store_api_urls_keep_page_language_when_wpml_uses_url_parameter(): void
	{
		$this->configure_wpml(3, 'de', 'en');

		foreach ($this->store_api_urls() as $key => $url) {
			$this->assertSameUrl(add_query_arg('lang', 'de', rest_url($this->routes()[ $key ])), $url, $key);
		}
	}
}
