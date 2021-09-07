<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class RequestDataTest extends TestCase
{
	public function testReadRequestReturnsRequestData()
	{
		$nonce = 'foo';
		$stream = '{"nonce":"foo"}';
		$json = [
			'nonce' => 'foo',
		];

		$testee = new RequestData();

		when('file_get_contents')->justReturn($stream);
		when('json_decode')->justReturn($json);
		when('wp_verify_nonce')->justReturn(true);
		when('sanitize_text_field')->returnArg();

		$result = $testee->read_request($nonce);
		$this->assertSame($json, $result);
	}

	public function testReadRequestFailsBecauseNoStream()
	{
		$nonce = 'foo';
		$testee = new RequestData();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not get stream.');
		$testee->read_request($nonce);
	}

	public function testReadRequestFailsBecauseInvalidJson()
	{
		$nonce = 'foo';
		$stream = '{"nonce":"foo"}';
		$testee = new RequestData();

		when('file_get_contents')->justReturn($stream);
		when('json_decode')->justReturn(false);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not decode JSON');
		$testee->read_request($nonce);
	}

	public function testReadRequestFailsBecauseNonceNotValid()
	{
		$nonce = 'foo';
		$stream = '{"nonce":"foo"}';
		$json = [
			'nonce' => 'foo',
		];

		$testee = new RequestData();

		when('file_get_contents')->justReturn($stream);
		when('json_decode')->justReturn($json);

		expect('wp_verify_nonce')
			->once()
			->with( $json['nonce'], $nonce)
			->andReturn(false);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not validate nonce.');
		$testee->read_request($nonce);
	}
}
