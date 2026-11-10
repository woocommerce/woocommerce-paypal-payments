<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Data\Definition;

use WooCommerce\PayPalCommerce\Settings\Data\Definition\TodosDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
use WooCommerce\PayPalCommerce\Settings\Service\TodosEligibilityService;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Data\Definition\TodosDefinition
 */
class TodosDefinitionTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		when( '__' )->returnArg();
		when( 'admin_url' )->returnArg();
	}

	/**
	 * GIVEN a merchant who is (or is not) connected to the PayPal sandbox
	 * WHEN the to-do definitions are built
	 * THEN the Pay with Crypto application link points to the matching PayPal environment
	 *
	 * @dataProvider pwc_application_url_provider
	 */
	public function test_apply_for_pwc_links_to_environment_specific_signup(
		bool $is_sandbox_merchant,
		string $expected_url
	): void {
		$eligibilities = $this->all_checks_eligible_stub();

		$settings = $this->createStub( GeneralSettings::class );
		$settings->method( 'is_sandbox_merchant' )->willReturn( $is_sandbox_merchant );

		$definition = new TodosDefinition( $eligibilities, $settings, $this->createStub( TodosModel::class ) );

		$result = $definition->get();

		$this->assertSame( $expected_url, $result['apply_for_pwc']['action']['url'] );
	}

	private function all_checks_eligible_stub(): TodosEligibilityService {
		$eligibilities = $this->createStub( TodosEligibilityService::class );
		$eligibilities->method( 'get_eligibility_checks' )->willReturn(
			array_fill_keys(
				array(
					'enable_fastlane',
					'enable_pay_later_messaging',
					'add_pay_later_messaging_product_page',
					'add_pay_later_messaging_cart',
					'add_pay_later_messaging_checkout',
					'configure_paypal_subscription',
					'add_paypal_buttons_cart',
					'add_paypal_buttons_block_checkout',
					'add_paypal_buttons_product',
					'register_domain_apple_pay',
					'add_digital_wallets',
					'add_apple_pay',
					'add_google_pay',
					'enable_apple_pay',
					'enable_google_pay',
					'enable_installments',
					'apply_for_working_capital',
					'enable_pwc',
					'apply_for_pwc',
					'enable_recaptcha_protection',
				),
				static fn(): bool => true
			)
		);

		return $eligibilities;
	}

	public function pwc_application_url_provider(): array {
		return array(
			'sandbox merchant gets the sandbox signup URL' => array(
				true,
				'https://www.sandbox.paypal.com/bizsignup/add-product?product=CRYPTO_PYMTS',
			),
			'live merchant gets the live signup URL'       => array(
				false,
				'https://www.paypal.com/bizsignup/add-product?product=CRYPTO_PYMTS',
			),
		);
	}
}
