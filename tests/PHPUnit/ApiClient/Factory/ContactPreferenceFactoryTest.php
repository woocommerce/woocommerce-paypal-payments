<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\MerchantDetails;

class ContactPreferenceFactoryTest extends TestCase
{
	/**
	 * @dataProvider forStateData
	 */
	public function testFromState(
		string $paymentSourceKey,
		bool $isContactModuleActive,
		bool $isEligibleForContactModule,
		?string $expectedResult
	) {
		$md = $this->createMerchantDetails($isEligibleForContactModule);

		$testee = new ContactPreferenceFactory($isContactModuleActive, $md);

		$result = $testee->from_state($paymentSourceKey);

		self::assertEquals($expectedResult, $result);
	}

	public function forStateData()
	{
		// Ensure UPDATE_CONTACT response for paypal and venmo.
		yield [
			'paypal',
			true,
			true,
			ExperienceContext::CONTACT_PREFERENCE_UPDATE_CONTACT_INFO
		];
		yield [
			'venmo',
			true,
			true,
			ExperienceContext::CONTACT_PREFERENCE_UPDATE_CONTACT_INFO
		];

		// Ensure NO_CONTACT response when not eligible or feature is disabled.
		yield [
			'paypal',
			false,
			true,
			ExperienceContext::CONTACT_PREFERENCE_NO_CONTACT_INFO
		];
		yield [
			'paypal',
			true,
			false,
			ExperienceContext::CONTACT_PREFERENCE_NO_CONTACT_INFO
		];
		yield [
			'paypal',
			false,
			false,
			ExperienceContext::CONTACT_PREFERENCE_NO_CONTACT_INFO
		];

		// Ensure NULL response when using an unsupported payment-source.
		yield [
			'oxxo',
			true,
			true,
			null
		];
		yield [
			'oxxo',
			false,
			true,
			null
		];

		yield [
			'oxxo',
			false,
			false,
			null
		];
	}

	private function createMerchantDetails($isEligible): MerchantDetails {
		$md = Mockery::mock(MerchantDetails::class);
		$md->shouldReceive('is_eligible_for')->andReturn($isEligible);
		return $md;
	}
}
