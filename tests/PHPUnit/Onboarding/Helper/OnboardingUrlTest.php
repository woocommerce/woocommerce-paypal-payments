<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use RuntimeException;
use function Brain\Monkey\Functions\when;

class OnboardingUrlTest extends TestCase
{
	private $cache;
	private $cache_key_prefix = 'test_prefix';
	private $user_id = 123;
	private $onboardingUrl;

	public function setUp(): void
	{
		parent::setUp();

		if (!defined('MONTH_IN_SECONDS')) {
			define( 'MONTH_IN_SECONDS', 30 * 24 * 60 * 60 );
		}

		when('wp_hash')->alias(function($string) {
			return hash('md5', $string);
		});

		$this->cache = \Mockery::mock(Cache::class);
		$this->onboardingUrl = new OnboardingUrl($this->cache, $this->cache_key_prefix, $this->user_id);
	}

	public function test_validate_token_and_delete_valid()
	{
		// Prepare the data
		$cacheData = [
			'hash_check' => wp_hash(''),
			'secret'     => 'test_secret',
			'time'       => time(),
			'user_id'    => $this->user_id,
			'url'        => 'https://example.com'
		];

		$token = [
			'k' => $this->cache_key_prefix,
			'u' => $this->user_id,
			'h' => substr(wp_hash(implode( '|',  array(
				$this->cache_key_prefix,
				$cacheData['user_id'],
				$cacheData['secret'],
				$cacheData['time'],
			))), 0, 32)
		];

		$onboarding_token = UrlHelper::url_safe_base64_encode(json_encode($token));

		// Expectations
		$this->cache->shouldReceive('has')->once()->andReturn(true);
		$this->cache->shouldReceive('get')->once()->andReturn($cacheData);
		$this->cache->shouldReceive('set')->once();
		$this->cache->shouldReceive('delete')->once();

		$this->assertTrue(
			OnboardingUrl::validate_token_and_delete($this->cache, $onboarding_token, $this->user_id)
		);
	}

	public function test_load_valid()
	{
		// Expectations
		$this->cache->shouldReceive('has')->once()->andReturn(true);
		$this->cache->shouldReceive('get')->once()->andReturn([
			'hash_check' => wp_hash(''),
			'secret'     => 'test_secret',
			'time'       => time(),
			'user_id'    => $this->user_id,
			'url'        => 'https://example.com'
		]);

		$this->assertTrue($this->onboardingUrl->load());
	}

	public function test_load_invalid()
	{
		// Expectations
		$this->cache->shouldReceive('has')->once()->andReturn(false);

		$this->assertFalse($this->onboardingUrl->load());
	}

	public function test_get_not_initialized()
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Object not initialized.');

		$this->onboardingUrl->get();
	}

	public function test_token_not_initialized()
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Object not initialized.');

		$this->onboardingUrl->token();
	}

	public function test_persist_not_initialized()
	{
		// Expectations
		$this->cache->shouldReceive('set')->never();

		$this->onboardingUrl->persist();

		$this->assertTrue(true);
	}

	public function test_delete()
	{
		// Expectations
		$this->cache->shouldReceive('delete')->once();

		$this->onboardingUrl->delete();

		$this->assertTrue(true);
	}

	public function test_init()
	{
		$this->onboardingUrl->init();

		$token = $this->onboardingUrl->token();
		$this->assertNotEmpty($token);
	}

	public function test_set_and_get()
	{
		$this->onboardingUrl->init();
		$this->onboardingUrl->set('https://example.com');

		$url = $this->onboardingUrl->get();
		$this->assertEquals('https://example.com', $url);
	}

	public function test_persist()
	{
		$this->onboardingUrl->init();
		$this->onboardingUrl->set('https://example.com');

		// Expectations
		$this->cache->shouldReceive('set')->once();

		$this->onboardingUrl->persist();

		$this->assertTrue(true);
	}

	public function test_token()
	{
		$this->onboardingUrl->init();
		$this->onboardingUrl->set('https://example.com');

		$token = $this->onboardingUrl->token();
		$this->assertNotEmpty($token);
	}

	public function test_validate_token_and_delete_invalid()
	{
		// Prepare the data
		$token = [
			'k' => $this->cache_key_prefix,
			'u' => $this->user_id,
			'h' => 'invalid_hash'
		];

		$onboarding_token = UrlHelper::url_safe_base64_encode(json_encode($token));

		// Expectations
		$this->cache->shouldReceive('has')->once()->andReturn(true);
		$this->cache->shouldReceive('get')->once()->andReturn([
			'hash_check' => wp_hash(''),
			'secret'     => 'test_secret',
			'time'       => time(),
			'user_id'    => $this->user_id,
			'url'        => 'https://example.com'
		]);
		$this->cache->shouldReceive('delete')->never();

		$this->assertFalse(
			OnboardingUrl::validate_token_and_delete($this->cache, $onboarding_token, $this->user_id)
		);
	}

}
